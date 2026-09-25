<?php

namespace App\Services\Marketing\Meta;

use App\Helpers\CurrencyHelper;
use App\Helpers\TranslationHelper;
use App\Models\Product;
use App\Models\Store;
use App\Services\Marketing\Google\GoogleIntegrationService;
use App\Services\Marketing\Google\ProductStructuredData;

/**
 * Data for components/marketing/head.blade.php, included in the <head> of every
 * storefront template: site-verification tags, the browser-tag config read by
 * resources/js/marketing.js (Meta Pixel and/or Google gtag) and, for stores on
 * Google, the product's schema.org data. Returns null — and the partial prints nothing
 * at all — for any store that hasn't configured a channel, which is what keeps
 * untouched stores (Maetek's frozen template included) byte-for-byte identical.
 */
class StorefrontTracking
{
    public function __construct(
        private MetaIntegrationService $meta,
        private GoogleIntegrationService $google,
        private ProductStructuredData $structuredData,
    ) {}

    /** @return array{meta_verification: ?string, google_verification: ?string, config: ?array, structured_data: ?array}|null */
    public function forView(mixed $store, mixed $product = null): ?array
    {
        if (!$store instanceof Store) {
            return null;
        }

        try {
            $meta = $this->meta->active($store);
            $google = $this->google->active($store);
            $productPage = $this->productPage($product);

            $metaTag = $meta?->hasPixel() ? ['pixelId' => (string) $meta->pixel_id] : null;
            $googleTag = $google?->hasGoogleTag() ? array_filter([
                'measurementId' => $google->measurement_id,
                'adsId'         => $google->ads_conversion_id,
                'adsLabel'      => $google->ads_conversion_label,
            ]) : null;

            $result = [
                'meta_verification'   => $meta?->domain_verification,
                'google_verification' => $google?->domain_verification,
                'config'              => $metaTag || $googleTag ? $this->config($store, $productPage, $metaTag, $googleTag) : null,
                'structured_data'     => $google && $productPage ? $this->structuredData->forProduct($productPage, $store, $google) : null,
            ];
        } catch (\Throwable $e) {
            report($e); // a marketing problem must never break the storefront
            return null;
        }

        return array_filter($result) ? $result : null;
    }

    private function config(Store $store, ?Product $product, ?array $metaTag, ?array $googleTag): array
    {
        $currency = CurrencyHelper::currentCurrency();

        return [
            'meta'        => $metaTag,
            'google'      => $googleTag,
            'currency'    => $currency,
            'lang'        => TranslationHelper::isEn() ? 'en' : 'es',
            'cookiePath'  => request()->attributes->has('store') ? '/' : '/tienda/' . $store->slug,
            'privacyUrl'  => route('legal.privacy') . '#cookies',
            'viewContent' => $product ? [
                'content_ids'  => [CatalogItemMapper::itemId($product->id)],
                'content_type' => $product->has_variants ? 'product_group' : 'product',
                'content_name' => mb_substr((string) $product->name, 0, 150),
                'value'        => round($product->resolvePrice($currency), 2),
                'currency'     => $currency,
            ] : null,
        ];
    }

    /**
     * The product, only on the product page itself. A layout also receives loop
     * variables leaked from its child view (a catalog's `@foreach($products as
     * $product)`), so an `isset($product)` alone would fire on the catalog too.
     */
    private function productPage(mixed $product): ?Product
    {
        return $product instanceof Product && request()->route()?->getActionMethod() === 'product' ? $product : null;
    }
}
