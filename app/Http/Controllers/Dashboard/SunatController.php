<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ElectronicDocument;
use App\Models\Store;
use App\Models\SunatSetting;
use App\Services\Pos\SalesDocuments;
use App\Services\Sunat\ComprobantePdf;
use App\Services\Sunat\ElectronicDocuments;
use App\Services\Sunat\SunatGateway;
use App\Services\Sunat\SunatSchema;
use App\Services\Sunat\SunatSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'factura_mode'   => ['required', Rule::in(['auto', 'manual'])],
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

    public function documents(Request $request)
    {
        $store = $this->store($request);
        $status = array_key_exists((string) $request->query('estado'), ElectronicDocument::STATUSES) ? $request->query('estado') : null;

        return view('dashboard.pos.electronic', [
            'store'  => $store,
            'status' => $status,
            'docs'   => ElectronicDocument::where('store_id', $store->id)->with('order:id,customer_name,total,order_number')
                ->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(25)->withQueryString(),
            'counts' => ElectronicDocument::where('store_id', $store->id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'setting' => $this->settings->for($store),
        ]);
    }

    public function retry(Request $request, ElectronicDocuments $documents, int $document): RedirectResponse
    {
        $doc = $this->owned($request, $document);
        if (!$doc->canRetry()) {
            return back()->with('error', 'Este comprobante ya no se puede reenviar.');
        }
        $doc = $documents->send($doc);

        return back()->with($doc->isDelivered() ? 'success' : 'error', $doc->isDelivered() ? "SUNAT recibió {$doc->code()}." : 'No se pudo enviar: ' . ($doc->sunat_message ?: 'inténtalo de nuevo en unos minutos.'));
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
