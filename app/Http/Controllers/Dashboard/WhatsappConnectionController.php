<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SaveWhatsappConnectionRequest;
use App\Services\Whatsapp\ConnectionService;
use App\Services\Whatsapp\WhatsappSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dashboard → WhatsApp → Conexión: the store's Cloud API number. */
class WhatsappConnectionController extends Controller
{
    public function __construct(private WhatsappSchema $schema)
    {
    }

    public function edit(Request $request)
    {
        $store = $request->user()->currentStore();

        return view('dashboard.whatsapp.connection', [
            'ready'      => $this->schema->ready(),
            'connection' => $this->schema->ready() ? $store->whatsappConnection()->first() : null,
        ]);
    }

    public function update(SaveWhatsappConnectionRequest $request, ConnectionService $connections): RedirectResponse
    {
        abort_unless($this->schema->ready(), 404);
        $connection = $connections->save($request->user()->currentStore(), $request->validated());

        return $connection->isConnected()
            ? redirect()->route('dashboard.whatsapp.connection.edit')->with('success', "Conectamos {$connection->display_phone_number}. Falta un paso en Meta: el webhook.")
            : redirect()->route('dashboard.whatsapp.connection.edit')->with('error', "Guardamos los datos, pero Meta respondió: {$connection->last_error}");
    }

    public function test(Request $request, ConnectionService $connections): RedirectResponse
    {
        $connection = $this->owned($request);
        abort_if($connection->status === 'disconnected', 404);

        return $connections->verify($connection)
            ? back()->with('success', 'La conexión con Meta funciona.')
            : back()->with('error', "Meta respondió: {$connection->last_error}");
    }

    public function destroy(Request $request, ConnectionService $connections): RedirectResponse
    {
        $connections->disconnect($this->owned($request));

        return back()->with('success', 'Desconectamos el número. Tus conversaciones se conservan.');
    }

    private function owned(Request $request)
    {
        abort_unless($this->schema->ready(), 404);

        return $request->user()->currentStore()->whatsappConnection()->firstOrFail();
    }
}
