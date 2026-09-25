<?php

namespace App\Services\Marketing\Google;

use App\Helpers\CurrencyHelper;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\Meta\CatalogItemMapper;

/**
 * schema.org Product markup (JSON-LD) for a product page of a store connected to
 * Google. Merchant Center reads it to confirm price and availability against the feed
 * (a mismatch disapproves the product), and Search can show price, stock and the
 * verified-review stars in organic results. Price is in the currency the page shows:
 * PEN unless the visitor chose another — Googlebot has no cookies, so it sees PEN,
 * the same as the feed.
 */
class ProductStructuredData
{
    private const CONDITIONS = [
        'new'         => 'https://schema.org/NewCondition',
        'refurbished' => 'https://schema.org/RefurbishedCondition',
        'used'        => 'https://schema.org/UsedCondition',
    ];

    public function forProduct(Product $product, Store $store, StoreMarketingIntegration $integration): array
    {
        $currency = CurrencyHelper::currentCurrency();
        // The page Google is looking at: tribio.pe/tienda/{slug}/… (listed by Tribio) or the
        // store's own domain. Never the "other" address, or the offer would point elsewhere.
        $url = request()->url();
        $variants = $product->has_variants
            ? $product->variants->filter(fn (ProductVariant $v) => $v->is_active)->values()
            : collect();
        $condition = self::CONDITIONS[$product->condition ?: $integration->default_condition] ?? self::CONDITIONS['new'];

        if ($variants->isNotEmpty()) {
            $prices = $variants->map(fn (ProductVariant $v) => $v->resolvePrice($currency));
            $inStock = $variants->contains(fn (ProductVariant $v) => CatalogItemMapper::isAvailable($product, $v, $store));
            $offers = [
                '@type'         => 'AggregateOffer',
                'priceCurrency' => $currency,
                'lowPrice'      => $this->money($prices->min()),
                'highPrice'     => $this->money($prices->max()),
                'offerCount'    => $variants->count(),
                'availability'  => $this->availability($inStock),
                'itemCondition' => $condition,
                'url'           => $url,
            ];
        } else {
            $offers = [
                '@type'         => 'Offer',
                'priceCurrency' => $currency,
                'price'         => $this->money($product->resolvePrice($currency)),
                'availability'  => $this->availability(CatalogItemMapper::isAvailable($product, null, $store)),
                'itemCondition' => $condition,
                'url'           => $url,
            ];
        }

        $images = array_map(fn ($path) => asset('storage/' . ltrim($path, '/')),
            array_values(array_filter([$product->image_path, ...(array) $product->gallery_images])));
        $description = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($product->description ?: $product->short_description)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return array_filter([
            '@context'        => 'https://schema.org/',
            '@type'           => 'Product',
            'name'            => $product->name,
            'description'     => $description !== '' ? mb_substr($description, 0, 5000) : null,
            'image'           => $images ?: null,
            'sku'             => $product->sku ?: CatalogItemMapper::itemId($product->id),
            'brand'           => ['@type' => 'Brand', 'name' => $product->brand?->name ?: $store->name],
            'offers'          => $offers,
            // Only verified-purchase reviews exist (see Product-Reviews), so the stars are real.
            'aggregateRating' => (int) $product->reviews_count > 0 && (float) $product->average_rating > 0 ? [
                '@type'       => 'AggregateRating',
                'ratingValue' => round((float) $product->average_rating, 1),
                'reviewCount' => (int) $product->reviews_count,
            ] : null,
        ], fn ($v) => $v !== null);
    }

    private function availability(bool $inStock): string
    {
        return $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
