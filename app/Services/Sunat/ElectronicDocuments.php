<?php

namespace App\Services\Sunat;

use App\Jobs\SendElectronicDocument;
use App\Mail\ElectronicDocumentMail;
use App\Models\DocumentSeries;
use App\Models\ElectronicDocument;
use App\Models\Order;
use App\Models\Store;
use App\Models\SunatSetting;
use App\Services\Pos\SalesDocuments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Boletas and facturas on their way to SUNAT.
 *
 * Every boleta and factura is numbered when the sale is rung up (that number never changes). Sending it to
 * SUNAT is a separate act on that comprobante: the «Enviar a SUNAT» button builds, signs and sends the
 * electronic document with the same series and number. A business that prefers can switch on "send all
 * automatically at checkout", and a series can be marked "solo interna" so its comprobantes are never reported
 * (keeping the series that are reported free of gaps).
 */
class ElectronicDocuments
{
    /** Days SUNAT still takes a comprobante after its issue date (facturas 3; boletas 7). Confirm with your accountant. */
    public const DEADLINE_DAYS = [SalesDocuments::FACTURA => 3, SalesDocuments::BOLETA => 7];

    public const STATE_INTERNAL = 'internal';
    public const STATE_UNSENT = 'unsent';

    public function __construct(
        private SunatSchema $schema,
        private SunatSettings $settings,
        private InvoiceBuilder $builder,
        private XmlSigner $signer,
        private SunatGateway $gateway,
        private Branches $branches,
    ) {
    }

    /** The mode ("manual" button, "auto" at checkout, "never") the business chose for this kind of comprobante. */
    public function mode(Store $store, string $type): string
    {
        $setting = $this->settings->for($store);

        return ($type === SalesDocuments::FACTURA ? $setting->factura_mode : $setting->boleta_mode) ?: 'manual';
    }

    /** Is this series reported to SUNAT? Series marked "solo interna" never are. */
    public function reported(Order $order): bool
    {
        if (!$this->schema->flagReady()) {
            return true;
        }
        $series = DocumentSeries::where('store_id', $order->store_id)->where('type', $order->document_type)->where('series', $order->document_series)->first();

        return $series ? $series->isElectronic() : true;
    }

    public function deadline(Order $order): ?Carbon
    {
        $days = self::DEADLINE_DAYS[$order->document_type] ?? null;

        return $days ? $order->created_at->copy()->setTimezone('America/Lima')->startOfDay()->addDays($days)->endOfDay() : null;
    }

    /**
     * Where a comprobante stands for the screens: `internal` (never reported), `unsent` (numbered, waiting for the
     * button) or the electronic document's own status. `can` says whether the button works right now, `why` not.
     *
     * @return array{state: string, doc: ?ElectronicDocument, can: bool, why: ?string, deadline: ?Carbon}
     */
    public function state(Order $order): array
    {
        $none = ['state' => self::STATE_INTERNAL, 'doc' => null, 'can' => false, 'why' => null, 'deadline' => null];
        if (!$this->schema->ready() || !isset(InvoiceBuilder::TYPE_CODES[$order->document_type])) {
            return $none;
        }
        $store = Store::find($order->store_id);
        $doc = $order->relationLoaded('electronicDocument') ? $order->electronicDocument : $order->electronicDocument()->first();
        if ($doc) {
            return ['state' => $doc->status, 'doc' => $doc, 'can' => $doc->canRetry() && $this->sendable($order, $store) === null, 'why' => $doc->canRetry() ? $this->sendable($order, $store) : null, 'deadline' => $this->deadline($order)];
        }
        if (!$this->reported($order) || $this->mode($store, $order->document_type) === 'never') {
            return $none;
        }
        $why = $this->sendable($order, $store);

        return ['state' => self::STATE_UNSENT, 'doc' => null, 'can' => $why === null, 'why' => $why, 'deadline' => $this->deadline($order)];
    }

    /** Why this sale cannot be sent right now (null = it can). */
    private function sendable(Order $order, Store $store): ?string
    {
        $setting = $this->settings->for($store);
        if (!$setting->enabled) {
            return 'La facturación electrónica está desactivada: actívala en Comprobantes → SUNAT.';
        }
        if ($problems = $this->settings->problems($store, $setting)) {
            return $problems[0];
        }
        if ($order->status === 'cancelled') {
            return 'La venta está anulada.';
        }
        if (($deadline = $this->deadline($order)) && $deadline->isPast()) {
            return 'Venció el plazo para enviarla (hasta el ' . $deadline->format('d/m/Y') . ').';
        }

        return null;
    }

    /** At checkout, only when the business asked for "all automatic". Call inside the sale's transaction, once the order has its number. */
    public function queue(Order $order, string $type): ?ElectronicDocument
    {
        $store = $order->store;
        if (!$this->schema->ready() || !isset(InvoiceBuilder::TYPE_CODES[$type]) || !$this->settings->for($store)->enabled
            || $this->mode($store, $type) !== 'auto' || !$this->reported($order)) {
            return null;
        }
        $setting = $this->settings->for($store);
        $problems = $this->settings->problems($store, $setting);

        return ElectronicDocument::create([
            'store_id'    => $order->store_id,
            'order_id'    => $order->id,
            'type'        => $type,
            'series'      => $order->document_series,
            'number'      => $order->document_number,
            'environment' => $setting->environment,
            'status'      => $problems ? ElectronicDocument::ERROR : ElectronicDocument::PENDING,
            'sunat_message' => $problems ? implode(' ', $problems) : null,
        ]);
    }

    /** After the sale is saved: send in the background of the same request (no queue worker needed). */
    public function dispatch(Order $order): void
    {
        $doc = ElectronicDocument::where('order_id', $order->id)->where('status', ElectronicDocument::PENDING)->first();
        if ($doc) {
            SendElectronicDocument::dispatchAfterResponse($doc->id);
        }
    }

    /**
     * The «Enviar a SUNAT» button: the electronic comprobante is built, signed and sent now, with the series
     * and number the sale already has. Runs in the request, so the cashier sees SUNAT's answer.
     *
     * @throws ValidationException when it cannot be sent (with the reason)
     */
    public function submit(Order $order): ElectronicDocument
    {
        $store = Store::findOrFail($order->store_id);
        if (!$this->schema->ready() || $order->source !== 'pos' || !isset(InvoiceBuilder::TYPE_CODES[$order->document_type])) {
            throw ValidationException::withMessages(['send' => 'Solo las boletas y facturas se envían a SUNAT.']);
        }
        if (!$this->reported($order) || $this->mode($store, $order->document_type) === 'never') {
            throw ValidationException::withMessages(['send' => 'Este comprobante es de una serie interna: no se envía a SUNAT.']);
        }
        if ($why = $this->sendable($order, $store)) {
            throw ValidationException::withMessages(['send' => $why]);
        }

        $doc = ElectronicDocument::where('order_id', $order->id)->first();
        if ($doc?->isDelivered()) {
            throw ValidationException::withMessages(['send' => "SUNAT ya recibió {$doc->code()}."]);
        }
        if ($doc && !$doc->canRetry()) {
            throw ValidationException::withMessages(['send' => 'SUNAT rechazó este comprobante: no se puede reenviar tal cual.']);
        }
        $doc ??= ElectronicDocument::create([
            'store_id' => $order->store_id, 'order_id' => $order->id, 'type' => $order->document_type, 'series' => $order->document_series,
            'number' => $order->document_number, 'environment' => $this->settings->for($store)->environment, 'status' => ElectronicDocument::PENDING,
        ]);

        return $this->send($doc);
    }

    /**
     * Sends, oldest first, the comprobantes still waiting for the button (up to $limit): "enviar todo lo pendiente".
     *
     * @return array{sent: int, failed: int}
     */
    public function submitPending(Store $store, int $limit = 20): array
    {
        $orders = Order::where('store_id', $store->id)->where('source', 'pos')->where('status', '!=', 'cancelled')->whereIn('document_type', array_keys(InvoiceBuilder::TYPE_CODES))
            ->where(fn ($q) => $q->whereDoesntHave('electronicDocument')->orWhereHas('electronicDocument', fn ($d) => $d->whereIn('status', [ElectronicDocument::PENDING, ElectronicDocument::ERROR])))
            ->orderBy('id')->limit($limit * 3)->get();

        $result = ['sent' => 0, 'failed' => 0];
        foreach ($orders as $order) {
            if ($result['sent'] + $result['failed'] >= $limit) {
                break;
            }
            if ($this->state($order)['can']) {
                try {
                    $this->submit($order)->isDelivered() ? $result['sent']++ : $result['failed']++;
                } catch (ValidationException $e) {
                    $result['failed']++;
                }
            }
        }

        return $result;
    }

    /** Signs (once) and sends one document; records SUNAT's answer and mails the result. */
    public function send(ElectronicDocument $doc): ElectronicDocument
    {
        $lock = Cache::lock('sunat:send:' . $doc->id, 120);
        if (!$lock->get()) {
            return $doc; // another request is already sending it
        }

        try {
            $doc = $doc->fresh();
            if (!$doc->canRetry()) {
                return $doc;
            }
            $store = Store::findOrFail($doc->store_id);
            $setting = $this->settings->for($store);
            $doc->forceFill(['attempts' => $doc->attempts + 1, 'last_attempt_at' => now(), 'environment' => $setting->environment])->save();

            if ($problems = $this->settings->problems($store, $setting)) {
                return $this->record($doc, SunatResult::error(implode(' ', $problems)));
            }
            $order = Order::with(['items', 'installments'])->findOrFail($doc->order_id);
            if (($deadline = $this->deadline($order)) && $deadline->isPast()) {
                return $this->record($doc, SunatResult::error('Venció el plazo para enviarla (hasta el ' . $deadline->format('d/m/Y') . '): SUNAT ya no la recibe.'));
            }

            try {
                if (!$doc->xml) {
                    $branch = ($order->branch_id ? \App\Models\Branch::find($order->branch_id) : null) ?? $this->branches->main($store);
                    $signed = $this->signer->sign($setting, $this->builder->build($order, $store, $branch));
                    $doc->forceFill(['xml' => $signed['xml'], 'hash' => $signed['hash']])->save();
                }
            } catch (\Throwable $e) {
                report($e);

                return $this->record($doc, SunatResult::error('No pudimos armar el comprobante: ' . mb_substr($e->getMessage(), 0, 300)));
            }

            return $this->record($doc, $this->gateway->send($setting, $store, $doc->xml));
        } finally {
            $lock->release();
        }
    }

    /** Retries what never reached SUNAT: spaced out (2, 8, 18… minutes) and only while SUNAT would still take it. */
    public function retryDue(int $limit = 50): int
    {
        $sent = 0;
        $query = ElectronicDocument::whereIn('status', [ElectronicDocument::PENDING, ElectronicDocument::ERROR])
            ->where('created_at', '>=', now()->subDays(max(self::DEADLINE_DAYS)))->orderBy('id')->limit($limit * 3);

        foreach ($query->get() as $doc) {
            if ($sent >= $limit) {
                break;
            }
            $max = $this->settings->for($doc->store)->max_attempts ?: 5;
            $wait = max(1, $doc->attempts) ** 2 * 2;
            if ($doc->attempts >= $max || ($doc->last_attempt_at && $doc->last_attempt_at->gt(now()->subMinutes($wait)))) {
                continue;
            }
            $this->send($doc);
            $sent++;
        }

        return $sent;
    }

    private function record(ElectronicDocument $doc, SunatResult $result): ElectronicDocument
    {
        $doc->forceFill([
            'status'        => $result->outcome,
            'sunat_code'    => $result->code,
            'sunat_message' => $result->message ? mb_substr($result->message, 0, 1000) : null,
            'cdr_zip'       => $result->cdrZip ?? $doc->cdr_zip,
            'sent_at'       => $result->outcome === ElectronicDocument::ERROR ? $doc->sent_at : now(),
        ])->save();

        if ($doc->isDelivered() && !$doc->emailed_at) {
            $this->email($doc);
        }

        return $doc;
    }

    /** The comprobante by mail: to the business, and to the customer when the store asks for it. A mail failure never undoes the sale. */
    public function email(ElectronicDocument $doc): void
    {
        try {
            $store = Store::find($doc->store_id);
            $setting = $this->settings->for($store);
            $order = Order::find($doc->order_id);
            $to = [];
            if ($setting->email_company) {
                $to[] = $setting->email_to ?: ($store->contact_email ?: $store->user?->email);
            }
            if ($setting->email_customer && filled($order?->customer_email)) {
                $to[] = $order->customer_email;
            }
            $to = array_values(array_unique(array_filter($to, fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))));
            if (!$to) {
                return;
            }

            Mail::to($to)->send(new ElectronicDocumentMail($doc->fresh(), $store, $order));
            $doc->forceFill(['emailed_at' => now()])->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function settingsFor(Store $store): SunatSetting
    {
        return $this->settings->for($store);
    }
}
