<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DocumentSeries;
use App\Models\ElectronicDocument;
use App\Models\Order;
use App\Models\Store;
use App\Models\SunatSetting;
use App\Services\Pos\SalesDocuments;
use App\Services\Sunat\ComprobantePdf;
use App\Services\Sunat\ElectronicDocuments;
use App\Services\Sunat\InvoiceBuilder;
use App\Services\Sunat\SunatGateway;
use App\Services\Sunat\SunatSchema;
use App\Services\Sunat\SunatSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Caja → Comprobantes → SUNAT: certificate and SOL keys, what gets sent, and the comprobantes electrónicos already sent. */
class SunatController extends Controller
{
    public function __construct(private SunatSchema $schema, private SunatSettings $settings, private SalesDocuments $documents)
    {
    }

    public function edit(Request $request)
    {
        $store = $this->store($request);
        $setting = $this->settings->for($store);

        return view('dashboard.pos.sunat', [
            'store'     => $store,
            'setting'   => $setting,
            'checklist' => $this->settings->checklist($store, $setting),
            'problems'  => $this->settings->problems($store, $setting),
            'test'      => session('sunat_test'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $this->store($request);
        $data = $request->validate([
            'enabled'        => ['nullable', 'boolean'],
            'environment'    => ['required', Rule::in([SunatSetting::BETA, SunatSetting::PRODUCTION])],
            'factura_mode'   => ['required', Rule::in(['manual', 'auto', 'never'])],
            'boleta_mode'    => ['required', Rule::in(['manual', 'auto', 'never'])],
            'sol_user'       => ['nullable', 'string', 'max:30'],
            'sol_password'   => ['nullable', 'string', 'max:60'],
            'endpoint'       => ['nullable', 'url:https', 'max:255'],
            'email_company'  => ['nullable', 'boolean'],
            'email_to'       => ['nullable', 'email', 'max:160'],
            'email_customer' => ['nullable', 'boolean'],
            'max_attempts'   => ['nullable', 'integer', 'min:1', 'max:10'],
        ], ['endpoint.url' => 'La dirección del servicio debe empezar con https://.', 'email_to.email' => 'Escribe un correo válido.']);

        $this->settings->save($store, $data + ['enabled' => $request->boolean('enabled'), 'email_company' => $request->boolean('email_company'), 'email_customer' => $request->boolean('email_customer')]);

        return redirect()->route('dashboard.pos.documents.sunat')->with('success', 'Guardamos tu configuración de SUNAT.');
    }

    public function certificate(Request $request): RedirectResponse
    {
        $store = $this->store($request);
        $request->validate(['certificate' => ['required', 'file', 'max:64'], 'certificate_password' => ['nullable', 'string', 'max:120']], [
            'certificate.required' => 'Elige el archivo de tu certificado (.pfx, .p12 o .pem).',
            'certificate.max'      => 'Ese archivo es demasiado grande para ser un certificado.',
        ]);
        $setting = $this->settings->storeCertificate($store, $request->file('certificate'), $request->input('certificate_password'));

        return redirect()->route('dashboard.pos.documents.sunat')->with('success', 'Guardamos tu certificado (vence el ' . $setting->certificate_valid_to->format('d/m/Y') . ').');
    }

    public function removeCertificate(Request $request): RedirectResponse
    {
        $this->settings->removeCertificate($this->store($request));

        return redirect()->route('dashboard.pos.documents.sunat')->with('success', 'Quitamos el certificado. La facturación electrónica quedó desactivada.');
    }

    /** "Probar configuración": the checks, and whether SUNAT's service answers from this server. */
    public function test(Request $request, SunatGateway $gateway): RedirectResponse
    {
        $store = $this->store($request);
        $setting = $this->settings->for($store);
        $ping = $gateway->ping($setting);
        if (!$this->settings->problems($store, $setting) && $ping['ok']) {
            $setting->update(['verified_at' => now()]);
        }

        return redirect()->route('dashboard.pos.documents.sunat')->with('sunat_test', $ping);
    }

    /** Every boleta and factura of the business with where it stands: waiting for the button, sent, with problems or internal. */
    public function documents(Request $request, ElectronicDocuments $documents)
    {
        $store = $this->store($request);
        $filter = in_array($request->query('estado'), ['por_enviar', 'problemas', 'enviados', 'internos'], true) ? $request->query('estado') : 'por_enviar';

        $query = fn (string $name) => $this->scope($store, $name);
        $counts = collect(['por_enviar', 'problemas', 'enviados', 'internos'])->mapWithKeys(fn ($name) => [$name => $query($name)->count()]);

        $docColumns = 'id,order_id,store_id,type,series,number,environment,status,sunat_code,sunat_message,attempts,sent_at,emailed_at,created_at';
        $orders = $query($filter)->with("electronicDocument:{$docColumns}")->orderByDesc('id')->paginate(25)->withQueryString();

        return view('dashboard.pos.electronic', [
            'store'   => $store,
            'filter'  => $filter,
            'counts'  => $counts,
            'orders'  => $orders,
            'states'  => $orders->getCollection()->mapWithKeys(fn ($order) => [$order->id => $documents->state($order)]),
            'setting' => $this->settings->for($store),
            'numbering' => $this->numbering($store),
        ]);
    }

    /** The button on a comprobante: builds, signs and sends it now, with the number it already has. */
    public function submit(Request $request, ElectronicDocuments $documents, int $order): RedirectResponse
    {
        $store = $this->store($request);
        $sale = Order::where('store_id', $store->id)->where('source', 'pos')->findOrFail($order);

        try {
            $doc = $documents->submit($sale);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return back()->with($doc->isDelivered() ? 'success' : 'error', $doc->isDelivered()
            ? "SUNAT recibió {$doc->code()}" . ($doc->emailed_at ? ' y te enviamos el comprobante por correo.' : '.')
            : "No se pudo enviar {$doc->code()}: " . ($doc->sunat_message ?: 'inténtalo de nuevo en unos minutos.'));
    }

    /** "Enviar todo lo pendiente": the oldest comprobantes still waiting, up to 20 at a time. */
    public function submitPending(Request $request, ElectronicDocuments $documents): RedirectResponse
    {
        $result = $documents->submitPending($this->store($request), 20);
        if (!$result['sent'] && !$result['failed']) {
            return back()->with('error', 'No hay comprobantes pendientes que se puedan enviar ahora.');
        }

        return back()->with($result['failed'] ? 'error' : 'success', "Enviados a SUNAT: {$result['sent']}" . ($result['failed'] ? ". Con problema: {$result['failed']} (revisa «Con problemas»)." : '.'));
    }

    public function download(Request $request, ComprobantePdf $pdf, int $document, string $format)
    {
        $doc = $this->owned($request, $document);
        $base = $doc->store->ruc . '-' . ($doc->type === 'factura' ? '01' : '03') . '-' . $doc->series . '-' . $doc->number;

        return match ($format) {
            'pdf'   => response($pdf->pdf($doc), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $base . '.pdf"']),
            'xml'   => $doc->xml ? response($doc->xml, 200, ['Content-Type' => 'application/xml', 'Content-Disposition' => 'attachment; filename="' . $base . '.xml"']) : abort(404),
            'cdr'   => $doc->cdr_zip ? response(base64_decode($doc->cdr_zip), 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="R-' . $base . '.zip"']) : abort(404),
            default => abort(404),
        };
    }

    /** The sales of one tab. "Internos" = a series marked solo interna, or boletas/facturas the business set to "no se envían". */
    private function scope(Store $store, string $name)
    {
        $types = array_keys(InvoiceBuilder::TYPE_CODES);
        $internalSeries = $this->schema->flagReady() ? DocumentSeries::where('store_id', $store->id)->where('electronic', false)->get(['type', 'series']) : collect();
        $never = collect($types)->filter(fn ($type) => ($type === 'factura' ? $this->settings->for($store)->factura_mode : $this->settings->for($store)->boleta_mode) === 'never')->values()->all();

        $internal = function ($q) use ($internalSeries, $never) {
            $q->where(function ($w) use ($internalSeries, $never) {
                $w->whereRaw('1 = 0');
                foreach ($internalSeries as $row) {
                    $w->orWhere(fn ($p) => $p->where('document_type', $row->type)->where('document_series', $row->series));
                }
                if ($never) {
                    $w->orWhereIn('document_type', $never);
                }
            });
        };

        $base = Order::where('store_id', $store->id)->where('source', 'pos')->where('status', '!=', 'cancelled')->whereIn('document_type', $types);

        return match ($name) {
            'enviados'  => $base->whereHas('electronicDocument', fn ($d) => $d->whereIn('status', [ElectronicDocument::ACCEPTED, ElectronicDocument::OBSERVED])),
            'problemas' => $base->whereHas('electronicDocument', fn ($d) => $d->whereIn('status', [ElectronicDocument::ERROR, ElectronicDocument::REJECTED])),
            'internos'  => $base->whereDoesntHave('electronicDocument')->where($internal),
            default     => $base->whereNot($internal)->where(fn ($q) => $q->whereDoesntHave('electronicDocument')->orWhereHas('electronicDocument', fn ($d) => $d->where('status', ElectronicDocument::PENDING))),
        };
    }

    /**
     * Control of the numbering, per series: how many were issued, up to which number, how many SUNAT already has.
     * A series that is reported should end up with everything it issued sent: this is where the gaps show.
     *
     * @return list<array{type: string, series: string, emitted: int, last: int, delivered: int, waiting: int, oldest: ?int, internal: bool}>
     */
    private function numbering(Store $store): array
    {
        $rows = Order::where('store_id', $store->id)->where('source', 'pos')->where('status', '!=', 'cancelled')->whereIn('document_type', array_keys(InvoiceBuilder::TYPE_CODES))
            ->whereNotNull('document_series')->selectRaw('document_type as type, document_series as series, COUNT(*) as emitted, MAX(document_number) as last_number')
            ->groupBy('document_type', 'document_series')->orderBy('document_type')->orderBy('document_series')->get();
        $delivered = ElectronicDocument::where('store_id', $store->id)->whereIn('status', [ElectronicDocument::ACCEPTED, ElectronicDocument::OBSERVED])
            ->selectRaw('type, series, COUNT(*) as total')->groupBy('type', 'series')->get()->mapWithKeys(fn ($r) => [$r->type . '|' . $r->series => (int) $r->total]);
        $internal = $this->schema->flagReady() ? DocumentSeries::where('store_id', $store->id)->where('electronic', false)->get(['type', 'series'])->map(fn ($r) => $r->type . '|' . $r->series)->all() : [];

        return $rows->map(function ($row) use ($delivered, $internal, $store) {
            $key = $row->type . '|' . $row->series;
            $isInternal = in_array($key, $internal, true);
            $sent = (int) ($delivered[$key] ?? 0);
            $oldest = $isInternal ? null : Order::where('store_id', $store->id)->where('source', 'pos')->where('status', '!=', 'cancelled')->where('document_type', $row->type)->where('document_series', $row->series)
                ->whereDoesntHave('electronicDocument', fn ($d) => $d->whereIn('status', [ElectronicDocument::ACCEPTED, ElectronicDocument::OBSERVED]))->min('document_number');

            return ['type' => $row->type, 'series' => $row->series, 'emitted' => (int) $row->emitted, 'last' => (int) $row->last_number, 'delivered' => $sent,
                'waiting' => $isInternal ? 0 : max(0, (int) $row->emitted - $sent), 'oldest' => $oldest ? (int) $oldest : null, 'internal' => $isInternal];
        })->all();
    }

    private function owned(Request $request, int $id): ElectronicDocument
    {
        return ElectronicDocument::where('store_id', $this->store($request)->id)->with('store')->findOrFail($id);
    }

    private function store(Request $request): Store
    {
        abort_unless($this->schema->ready() && $this->documents->ready(), 404);
        $store = $request->user()->currentStore();
        abort_unless($store, 404);

        return $store;
    }
}
