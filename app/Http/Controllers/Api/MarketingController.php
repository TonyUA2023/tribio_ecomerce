<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateGoogleIntegrationRequest;
use App\Http\Requests\Dashboard\UpdateMetaIntegrationRequest;
use App\Models\MarketingEventLog;
use App\Models\OrderAttribution;
use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\Google\GoogleIntegrationService;
use App\Services\Marketing\Google\MarketplaceFeed;
use App\Services\Marketing\Meta\CatalogFeed;
use App\Services\Marketing\Meta\MetaIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Marketing: Meta (Pixel, Conversions API, catalog) and Google (Shopping, GA4, Ads).
 * The two Form Requests are the web dashboard's own, so pasted snippets are parsed the
 * same way on both surfaces. The Conversions API token is write-only here too.
 */
class MarketingController extends Controller
{
    use ResolvesCurrentStore;

    public function __construct(
        private MetaIntegrationService $meta,
        private GoogleIntegrationService $google,
        private CatalogFeed $feed,
        private MarketplaceFeed $marketplace,
    ) {
    }

    public function overview(): JsonResponse
    {
        $store = $this->currentStore();
        $meta = $this->meta->available() ? $this->meta->find($store) : null;
        $google = $this->google->available() ? $this->google->find($store) : null;

        return response()->json([
            'available'          => $this->meta->available(),
            'has_custom_domain'  => $this->hasCustomDomain($store),
            'marketplace_enabled' => $this->marketplace->enabled(),
            'meta'   => $meta ? $this->present($meta) + ['catalog' => $this->catalog($store, $meta), 'sales' => $this->sales($store, OrderAttribution::CHANNEL_META)] : null,
            'google' => $google ? $this->present($google) + ['catalog' => $this->catalog($store, $google), 'sales' => $this->sales($store, OrderAttribution::CHANNEL_GOOGLE)] : null,
        ]);
    }

    public function showMeta(): JsonResponse
    {
        $store = $this->currentStore();
        $saved = $this->meta->available() ? $this->meta->find($store) : null;

        return response()->json([
            'available'    => $this->meta->available(),
            'integration'  => $saved ? $this->present($saved) : null,
            'feed_url'     => CatalogFeed::url($store, StoreMarketingIntegration::PROVIDER_META),
            'catalog'      => $saved ? $this->catalog($store, $saved) : null,
            'recent_events' => $saved
                ? MarketingEventLog::where('store_id', $store->id)->where('provider', StoreMarketingIntegration::PROVIDER_META)
                    ->with('order:id,order_number')->latest('id')->limit(15)->get()->map(fn (MarketingEventLog $e) => [
                        'id' => $e->id, 'event_name' => $e->event_name, 'status' => $e->status, 'is_test' => (bool) $e->is_test,
                        'order_number' => $e->order?->order_number, 'error' => $e->error, 'created_at' => $e->created_at?->toISOString(),
                    ])
                : [],
        ]);
    }

    public function updateMeta(UpdateMetaIntegrationRequest $request): JsonResponse
    {
        if (!$this->meta->available()) {
            return $this->unavailable();
        }
        $integration = $this->meta->save($this->currentStore(), $request->validated());

        return response()->json([
            'message'     => $integration->is_active ? 'Listo. Tu tienda quedó conectada con Meta.' : 'Guardado. La conexión con Meta está en pausa.',
            'integration' => $this->present($integration),
        ]);
    }

    public function testMetaEvent(Request $request): JsonResponse
    {
        $store = $this->currentStore();
        $integration = $this->meta->active($store);

        if (!$integration?->hasConversionsApi()) {
            return response()->json(['message' => 'Primero guarda tu ID de Píxel y tu token de la API de conversiones.'], 422);
        }
        if (!$integration->test_event_code) {
            return response()->json(['message' => 'Pega el código de prueba (empieza con TEST) y guarda antes de enviar la prueba.'], 422);
        }

        $result = $this->meta->sendTestEvent($store, $integration, $request);

        return response()->json(['ok' => $result['ok'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    }

    public function showGoogle(): JsonResponse
    {
        $store = $this->currentStore();
        $saved = $this->google->available() ? $this->google->find($store) : null;

        return response()->json([
            'available'           => $this->google->available(),
            'integration'         => $saved ? $this->present($saved) : null,
            'has_custom_domain'   => $this->hasCustomDomain($store),
            'marketplace_enabled' => $this->marketplace->enabled(),
            'feed_url'            => CatalogFeed::url($store, StoreMarketingIntegration::PROVIDER_GOOGLE),
            'catalog'             => $saved ? $this->catalog($store, $saved) : null,
        ]);
    }

    public function updateGoogle(UpdateGoogleIntegrationRequest $request): JsonResponse
    {
        if (!$this->google->available()) {
            return $this->unavailable();
        }
        $integration = $this->google->save($this->currentStore(), $request->validated());

        return response()->json([
            'message'     => $integration->is_active ? 'Listo. Tu tienda quedó conectada con Google.' : 'Guardado. La conexión con Google está en pausa.',
            'integration' => $this->present($integration),
        ]);
    }

    public function publishGoogle(): JsonResponse
    {
        if (!$this->google->available()) {
            return $this->unavailable();
        }
        $integration = $this->google->publishViaTribio($this->currentStore());

        return response()->json([
            'message'     => 'Listo. Tus productos se publicarán en Google Shopping a través de Tribio. Google los revisa antes de mostrarlos (puede tardar unos días).',
            'integration' => $this->present($integration),
        ]);
    }

    private function hasCustomDomain(Store $store): bool
    {
        return (bool) ($store->custom_domain && str_contains($store->custom_domain, '.'));
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'El módulo de Marketing aún no está activo en el servidor. Inténtalo más tarde.'], 503);
    }

    /** Never includes the Conversions API token: only whether one is saved and a masked hint. */
    private function present(StoreMarketingIntegration $i): array
    {
        return [
            'provider'             => $i->provider,
            'is_active'            => (bool) $i->is_active,
            'default_condition'    => $i->default_condition ?: 'new',
            'domain_verification'  => $i->domain_verification,
            'pixel_id'             => $i->pixel_id,
            'has_capi_token'       => filled($i->capi_token),
            'capi_token_hint'      => $i->maskedToken(),
            'test_event_code'      => $i->test_event_code,
            'measurement_id'       => $i->measurement_id,
            'ads_conversion_id'    => $i->ads_conversion_id,
            'ads_conversion_label' => $i->ads_conversion_label,
            'shopping_mode'        => $i->shopping_mode,
            'last_event_at'        => $i->last_event_at?->toISOString(),
            'last_error'           => $i->last_error,
            'last_error_at'        => $i->last_error_at?->toISOString(),
        ];
    }

    private function catalog(Store $store, StoreMarketingIntegration $integration): array
    {
        $d = $this->feed->diagnostics($store, $integration);

        return [
            'products_total'    => $d['products_total'],
            'products_included' => $d['products_included'],
            'items'             => $d['items'],
            'excluded'          => collect($d['excluded'])->take(30)->map(fn (array $e) => [
                'product_id' => $e['product']->id, 'name' => $e['product']->name, 'reason' => $e['reason'],
            ])->values(),
            'excluded_count'    => count($d['excluded']),
        ];
    }

    /** Paid orders of the last 30 days that came from the channel's ads, per currency. */
    private function sales(Store $store, string $channel): array
    {
        return OrderAttribution::query()
            ->join('orders', 'orders.id', '=', 'order_attributions.order_id')
            ->where('order_attributions.store_id', $store->id)
            ->where('order_attributions.channel', $channel)
            ->where('order_attributions.created_at', '>=', now()->subDays(30))
            ->whereIn('orders.payment_status', ['paid', 'partial'])
            ->whereNull('orders.deleted_at')
            ->groupBy('orders.currency')
            ->get([DB::raw('orders.currency as currency'), DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(orders.total) as revenue')])
            ->map(fn ($r) => ['currency' => $r->currency, 'orders_count' => (int) $r->orders_count, 'revenue' => (float) $r->revenue])
            ->all();
    }
}
