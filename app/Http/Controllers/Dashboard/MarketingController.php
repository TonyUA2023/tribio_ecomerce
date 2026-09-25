<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\OrderAttribution;
use App\Models\Store;
use App\Services\Marketing\Google\GoogleIntegrationService;
use App\Services\Marketing\Meta\CatalogFeed;
use App\Services\Marketing\Meta\MetaIntegrationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Dashboard → Marketing: overview of the store's ad channels (Meta, Google). */
class MarketingController extends Controller
{
    public function index(MetaIntegrationService $meta, GoogleIntegrationService $google, CatalogFeed $feed)
    {
        $store = Auth::user()->currentStore();
        $available = $meta->available();
        $integration = $available ? $meta->find($store) : null;
        $googleIntegration = $google->available() ? $google->find($store) : null;

        return view('dashboard.marketing.index', [
            'store'             => $store,
            'available'         => $available,
            'integration'       => $integration,
            'catalog'           => $integration ? $feed->diagnostics($store, $integration) : null,
            'metaSales'         => $integration ? $this->sales($store, OrderAttribution::CHANNEL_META) : collect(),
            'googleAvailable'   => $google->available(),
            'googleIntegration' => $googleIntegration,
            'googleCatalog'     => $googleIntegration ? $feed->diagnostics($store, $googleIntegration) : null,
            'googleSales'       => $googleIntegration ? $this->sales($store, OrderAttribution::CHANNEL_GOOGLE) : collect(),
            'hasCustomDomain'   => $store->custom_domain && str_contains($store->custom_domain, '.'),
        ]);
    }

    /** Paid orders of the last 30 days whose visit came from the channel's ads, per currency. */
    private function sales(Store $store, string $channel)
    {
        return OrderAttribution::query()
            ->join('orders', 'orders.id', '=', 'order_attributions.order_id')
            ->where('order_attributions.store_id', $store->id)
            ->where('order_attributions.channel', $channel)
            ->where('order_attributions.created_at', '>=', now()->subDays(30))
            ->whereIn('orders.payment_status', ['paid', 'partial'])
            ->whereNull('orders.deleted_at')
            ->groupBy('orders.currency')
            ->get([DB::raw('orders.currency as currency'), DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(orders.total) as revenue')]);
    }
}
