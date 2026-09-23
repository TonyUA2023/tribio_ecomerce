<?php

namespace App\Services\Marketing\Meta;

use App\Helpers\CurrencyHelper;
use App\Helpers\TranslationHelper;
use App\Models\Product;
use App\Models\Store;

/**
 * Data for components/marketing/head.blade.php, included in the <head> of every
 * storefront template. Returns null — and the partial prints nothing at all — for any
 * store that hasn't configured Meta, which is what keeps untouched stores (Maetek's
 * frozen template included) byte-for-byte identical.
 */
class StorefrontTracking
{
    public function __construct(private MetaIntegrationService $meta) {}

    /** @return array{domain_verification: ?string, config: ?array}|null */
    public function forView(mixed $store, mixed $product = null): ?array
    {
        if (!$store instanceof Store) {
            return null;
        }

        try {
            $integration = $this->meta->active($store);
        } catch (\Throwable $e) {
            report($e); // a marketing problem must never break the storefront
            return null;
        }
        if (!$integration || (!$integration->hasPixel() && !$integration->domain_verification)) {
            return null;
        }

        $config = null;
        if ($integration->hasPixel()) {
            $currency = CurrencyHelper::currentCurrency();
            $config = [
                'pixelId'     => (string) $integration->pixel_id,
                'currency'    => $currency,
                'lang'        => TranslationHelper::isEn() ? 'en' : 'es',
                'cookiePath'  => request()->attributes->has('store') ? '/' : '/tienda/' . $store->slug,
                'privacyUrl'  => route('legal.privacy') . '#cookies',
                'viewContent' => $this->viewContent($product, $currency),
            ];
        }

        return [
            'domain_verification' => $integration->domain_verification,
            'config'              => $config,
        ];
    }

    /**
     * Only on the product page itself. A layout also receives loop variables leaked
     * from its child view (a catalog's `@foreach($products as $product)`), so an
     * `isset($product)` alone would fire ViewContent on the catalog too.
     */
    private function viewContent(mixed $product, string $currency): ?array
    {
        if (!$product instanceof Product || request()->route()?->getActionMethod() !== 'product') {
            return null;
        }

        return [
            'content_ids'  => [CatalogItemMapper::itemId($product->id)],
            'content_type' => $product->has_variants ? 'product_group' : 'product',
            'content_name' => mb_substr((string) $product->name, 0, 150),
            'value'        => round($product->resolvePrice($currency), 2),
            'currency'     => $currency,
        ];
    }
}
