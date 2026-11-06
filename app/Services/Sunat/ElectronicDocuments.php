<?php

namespace App\Services\Sunat;

use App\Jobs\SendElectronicDocument;
use App\Mail\ElectronicDocumentMail;
use App\Models\ElectronicDocument;
use App\Models\Order;
use App\Models\Store;
use App\Models\SunatSetting;
use App\Services\Pos\SalesDocuments;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Boletas and facturas on their way to SUNAT. Not every sale goes: the business chooses (per sale, or
 * "all boletas" / "all facturas" in its settings). A sale marked for SUNAT is saved with its document
 * `pending`; right after the answer goes back to the cashier it is signed and sent, and whatever could
 * not be delivered is retried (by the scheduler, or by hand from Comprobantes electrónicos).
 */
class ElectronicDocuments
{
    /** SUNAT only takes comprobantes up to 3 days after the issue date. */
    public const MAX_AGE_DAYS = 3;

    public function __construct(
        private SunatSchema $schema,
        private SunatSettings $settings,
        private InvoiceBuilder $builder,
        private XmlSigner $signer,
        private SunatGateway $gateway,
        private Branches $branches,
    ) {
    }

    /** Does this sale go to SUNAT? Nota de venta never; factura by default; boleta only when asked (unless the store says "all"). */
    public function wants(Store $store, string $type, ?bool $requested): bool
    {
        if (!$this->schema->ready() || !isset(InvoiceBuilder::TYPE_CODES[$type])) {
            return false;
        }
        $setting = $this->settings->for($store);
        if (!$setting->enabled) {
            return false;
        }

        return match ($type) {
            SalesDocuments::FACTURA => $setting->factura_mode === 'auto' ? true : (bool) $requested,
            default                 => match ($setting->boleta_mode) {
                'auto'  => true,
                'never' => false,
                default => (bool) $requested,
            },
        };
    }

    /** Call inside the sale's transaction, once the order has its number. */
    public function queue(Order $order, string $type, ?bool $requested): ?ElectronicDocument
    {
        $store = $order->store;
        if (!$this->wants($store, $type, $requested)) {
            return null;
        }
        $setting = $this->settings->for($store);

        $problems = $this->settings->problems($store, $setting);
        if ($problems && $requested) {
            throw ValidationException::withMessages(['send_sunat' => 'No se puede enviar a SUNAT: ' . implode(' ', array_slice($problems, 0, 2))]);
        }

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
            if ($doc->created_at->lt(now()->subDays(self::MAX_AGE_DAYS)->subHours(12)) && !$doc->xml) {
                return $this->record($doc, SunatResult::error('Pasaron más de ' . self::MAX_AGE_DAYS . ' días desde la venta: SUNAT ya no recibe este comprobante.'));
            }

            try {
                if (!$doc->xml) {
                    $order = Order::with(['items', 'installments'])->findOrFail($doc->order_id);
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
            ->where('created_at', '>=', now()->subDays(self::MAX_AGE_DAYS))->orderBy('id')->limit($limit * 3);

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
