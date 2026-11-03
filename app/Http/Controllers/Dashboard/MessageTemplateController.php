<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveMessageTemplateRequest;
use App\Models\Contact;
use App\Services\Crm\CrmSchema;
use App\Services\Crm\MessageTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Dashboard → Clientes → Plantillas de WhatsApp (MessageTemplates). */
class MessageTemplateController extends Controller
{
    public function __construct(private CrmSchema $schema)
    {
    }

    public function index(MessageTemplates $templates)
    {
        abort_unless($this->schema->templatesReady(), 404);
        $store = Auth::user()->currentStore();

        // A sample customer so the owner sees how each message will read.
        $sample = new Contact(['name' => 'Rosa Quispe', 'phone' => '51987654321']);
        $sample->setRelation('store', $store);

        return view('dashboard.contacts.templates', [
            'templates'    => $templates->all($store),
            'placeholders' => MessageTemplates::PLACEHOLDERS,
            'preview'      => fn (string $body) => strtr($body, [
                '{nombre}' => 'Rosa', '{nombre_completo}' => 'Rosa Quispe', '{tienda}' => $store->name,
                '{producto}' => 'Batido de vainilla', '{pedido}' => '#NV-123', '{saldo}' => 'S/ 120.00',
                '{link_tienda}' => route('store.show', $store->slug),
            ]),
        ]);
    }

    public function store(SaveMessageTemplateRequest $request, MessageTemplates $templates): RedirectResponse
    {
        abort_unless($this->schema->templatesReady(), 404);
        $templates->createOwn($request->user()->currentStore(), trim($request->input('name')), trim($request->input('body')), $request->boolean('is_promo'));

        return back()->with('success', 'Creamos tu plantilla.');
    }

    public function update(SaveMessageTemplateRequest $request, MessageTemplates $templates, string $key): RedirectResponse
    {
        abort_unless($this->schema->templatesReady(), 404);
        $store = $request->user()->currentStore();

        if (isset(MessageTemplates::BUILTIN[$key])) {
            $templates->saveBuiltin($store, $key, trim($request->input('body')));
        } else {
            $templates->own($store, $key)->update(array_filter([
                'name' => $request->filled('name') ? trim($request->input('name')) : null,
                'body' => trim($request->input('body')),
            ]) + ['is_promo' => $request->boolean('is_promo')]);
        }

        return back()->with('success', 'Guardamos la plantilla.');
    }

    public function destroy(MessageTemplates $templates, string $key): RedirectResponse
    {
        abort_unless($this->schema->templatesReady(), 404);
        $store = Auth::user()->currentStore();

        if (isset(MessageTemplates::BUILTIN[$key])) {
            $templates->resetBuiltin($store, $key);

            return back()->with('success', 'Volvimos al mensaje original de Tribio.');
        }
        $templates->own($store, $key)->delete();

        return back()->with('success', 'Eliminamos la plantilla.');
    }
}
