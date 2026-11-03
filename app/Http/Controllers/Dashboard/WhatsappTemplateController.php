<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnection;
use App\Models\WhatsappTemplate;
use App\Services\Crm\MessageTemplates;
use App\Services\Whatsapp\TemplateService;
use App\Services\Whatsapp\WhatsappSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Dashboard → WhatsApp → Plantillas de Meta: review status, sync, send Tribio's templates to review. */
class WhatsappTemplateController extends Controller
{
    public function __construct(private WhatsappSchema $schema)
    {
    }

    public function index(Request $request, MessageTemplates $local, TemplateService $templates)
    {
        abort_unless($this->schema->templatesReady(), 404);
        $store = $request->user()->currentStore();
        $connection = $store->whatsappConnection()->first();
        $remote = $connection ? WhatsappTemplate::where('connection_id', $connection->id)->orderByRaw("CASE status WHEN 'APPROVED' THEN 0 WHEN 'PENDING' THEN 1 ELSE 2 END")->orderBy('name')->get() : collect();
        $submitted = $remote->whereNotNull('source_key')->keyBy('source_key');

        return view('dashboard.whatsapp.templates', [
            'connection' => $connection,
            'remote'     => $remote,
            'local'      => $local->all($store)->map(function (array $template) use ($store, $templates, $submitted) {
                try {
                    [$text, $params] = $templates->convert($store, $template['body']);
                    $problem = null;
                } catch (\Illuminate\Validation\ValidationException $e) {
                    [$text, $params, $problem] = [$template['body'], [], $e->validator->errors()->first()];
                }

                return $template + ['meta_text' => $text, 'params' => $params, 'problem' => $problem, 'submitted' => $submitted[$template['key']] ?? null];
            }),
        ]);
    }

    public function sync(Request $request, TemplateService $templates): RedirectResponse
    {
        $count = $templates->sync($this->connection($request));

        return back()->with('success', $count === 1 ? 'Meta tiene 1 plantilla en tu cuenta.' : "Meta tiene {$count} plantillas en tu cuenta.");
    }

    public function submit(Request $request, TemplateService $templates): RedirectResponse
    {
        $data = $request->validate([
            'key'      => ['required', 'string', 'regex:/^([a-z_]+|propia-\d+)$/'],
            'category' => ['required', Rule::in(['UTILITY', 'MARKETING'])],
        ]);
        $template = $templates->submit($this->connection($request), $request->user()->currentStore(), $data['key'], $data['category']);

        return back()->with('success', "Enviamos «{$template->name}» a revisión. Meta suele responder en minutos (a veces hasta 24 h).");
    }

    public function destroy(Request $request, TemplateService $templates, int $template): RedirectResponse
    {
        $template = WhatsappTemplate::where('connection_id', $this->connection($request)->id)->findOrFail($template);
        $templates->delete($template);

        return back()->with('success', 'Eliminamos la plantilla en Meta y aquí.');
    }

    private function connection(Request $request): WhatsappConnection
    {
        abort_unless($this->schema->templatesReady(), 404);
        $connection = $request->user()->currentStore()->whatsappConnection()->first();
        abort_unless($connection?->isConnected(), 404);

        return $connection;
    }
}
