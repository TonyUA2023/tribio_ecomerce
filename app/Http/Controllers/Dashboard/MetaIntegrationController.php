<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateMetaIntegrationRequest;
use App\Models\MarketingEventLog;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\Meta\CatalogFeed;
use App\Services\Marketing\Meta\MetaIntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Dashboard → Marketing → Meta: Pixel, Conversions API token and the catalog feed. */
class MetaIntegrationController extends Controller
{
    public function edit(MetaIntegrationService $meta, CatalogFeed $feed)
    {
        $store = Auth::user()->currentStore();
        $available = $meta->available();
        $saved = $available ? $meta->find($store) : null;

        return view('dashboard.marketing.meta', [
            'store'        => $store,
            'available'    => $available,
            'saved'        => $saved,
            'integration'  => $saved ?? new StoreMarketingIntegration(['is_active' => true, 'default_condition' => 'new']),
            'feedUrl'      => CatalogFeed::url($store, StoreMarketingIntegration::PROVIDER_META),
            'catalog'      => $saved ? $feed->diagnostics($store, $saved) : null,
            'recentEvents' => $saved
                ? MarketingEventLog::where('store_id', $store->id)->where('provider', StoreMarketingIntegration::PROVIDER_META)
                    ->with('order:id,order_number')->latest('id')->limit(15)->get()
                : collect(),
        ]);
    }

    public function update(UpdateMetaIntegrationRequest $request, MetaIntegrationService $meta): RedirectResponse
    {
        if (!$meta->available()) {
            return back()->with('error', 'El módulo de Marketing aún no está activo en el servidor. Inténtalo más tarde.');
        }

        $integration = $meta->save($request->user()->currentStore(), $request->validated());

        return redirect()->route('dashboard.marketing.meta.edit')->with('success', $integration->is_active
            ? 'Listo. Tu tienda quedó conectada con Meta.'
            : 'Guardado. La conexión con Meta está en pausa: tu tienda no envía datos a Meta.');
    }

    public function testEvent(Request $request, MetaIntegrationService $meta): RedirectResponse
    {
        $store = $request->user()->currentStore();
        $integration = $meta->active($store);

        if (!$integration?->hasConversionsApi()) {
            return back()->with('error', 'Primero guarda tu ID de Píxel y tu token de la API de conversiones.');
        }
        if (!$integration->test_event_code) {
            return back()->with('error', 'Pega el código de prueba (empieza con TEST) y guarda antes de enviar la prueba.');
        }

        $result = $meta->sendTestEvent($store, $integration, $request);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

}
