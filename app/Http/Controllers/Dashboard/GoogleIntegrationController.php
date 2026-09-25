<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateGoogleIntegrationRequest;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\Google\GoogleIntegrationService;
use App\Services\Marketing\Google\MarketplaceFeed;
use App\Services\Marketing\Meta\CatalogFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Dashboard → Marketing → Google: Merchant Center feed, site verification, Analytics 4, Google Ads. */
class GoogleIntegrationController extends Controller
{
    public function edit(GoogleIntegrationService $google, CatalogFeed $feed, MarketplaceFeed $marketplace)
    {
        $store = Auth::user()->currentStore();
        $available = $google->available();
        $saved = $available ? $google->find($store) : null;

        return view('dashboard.marketing.google', [
            'store'              => $store,
            'available'          => $available,
            'saved'              => $saved,
            // New connections default to "Tribio publica mis productos": no domain, no Google account.
            'integration'        => $saved ?? new StoreMarketingIntegration([
                'provider' => StoreMarketingIntegration::PROVIDER_GOOGLE, 'is_active' => true, 'default_condition' => 'new',
                'shopping_mode' => StoreMarketingIntegration::SHOPPING_VIA_TRIBIO,
            ]),
            'hasCustomDomain'    => $store->custom_domain && str_contains($store->custom_domain, '.'),
            'marketplaceEnabled' => $marketplace->enabled(),
            'feedUrl'            => CatalogFeed::url($store, StoreMarketingIntegration::PROVIDER_GOOGLE),
            'catalog'            => $saved ? $feed->diagnostics($store, $saved) : null,
        ]);
    }

    /** One click from Marketing → Resumen: Tribio lists the store on Google Shopping. */
    public function publishViaTribio(GoogleIntegrationService $google): RedirectResponse
    {
        if (!$google->available()) {
            return back()->with('error', 'El módulo de Marketing aún no está activo en el servidor. Inténtalo más tarde.');
        }

        $google->publishViaTribio(Auth::user()->currentStore());

        return redirect()->route('dashboard.marketing.index')->with('success',
            'Listo. Tus productos se publicarán en Google Shopping a través de Tribio. Google los revisa antes de mostrarlos (puede tardar unos días).');
    }

    public function update(UpdateGoogleIntegrationRequest $request, GoogleIntegrationService $google): RedirectResponse
    {
        if (!$google->available()) {
            return back()->with('error', 'El módulo de Marketing aún no está activo en el servidor. Inténtalo más tarde.');
        }

        $integration = $google->save($request->user()->currentStore(), $request->validated());

        return redirect()->route('dashboard.marketing.google.edit')->with('success', $integration->is_active
            ? 'Listo. Tu tienda quedó conectada con Google.'
            : 'Guardado. La conexión con Google está en pausa: tu catálogo y tus etiquetas no se publican.');
    }
}
