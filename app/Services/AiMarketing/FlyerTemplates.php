<?php

namespace App\Services\AiMarketing;

use App\Models\BrandKit;
use App\Models\Product;
use App\Models\Store;
use App\Services\Pricing\WholesalePricing;

/**
 * Flyer templates of Marketing → Contenido. The browser draws them (resources/js/flyer-studio.js)
 * from the data built here: every price comes from the product, never typed in the flyer.
 */
class FlyerTemplates
{
    public const TEMPLATES = [
        'oferta'    => 'Oferta',
        'destacado' => 'Producto destacado',
        'mayorista' => 'Precio por mayor',
    ];

    public const FORMATS = [
        'square'   => ['label' => 'Post cuadrado', 'width' => 1080, 'height' => 1080],
        'portrait' => ['label' => 'Post vertical', 'width' => 1080, 'height' => 1350],
        'story'    => ['label' => 'Historia / Estado', 'width' => 1080, 'height' => 1920],
    ];

    /** Templates that make sense for this product (an offer needs a previous price, etc.). */
    public static function availableFor(Product $product): array
    {
        return array_filter(self::TEMPLATES, fn ($label, $key) => match ($key) {
            'oferta'    => self::onSale($product),
            'mayorista' => WholesalePricing::tiers($product) !== [],
            default     => true,
        }, ARRAY_FILTER_USE_BOTH);
    }

    public static function data(Store $store, Product $product, BrandKit $kit): array
    {
        $onSale = self::onSale($product);

        return [
            'id'           => $product->id,
            'name'         => $product->name,
            'image'        => $product->image_url,
            'price'        => CopyGenerator::money((float) $product->price),
            'compare'      => $onSale ? CopyGenerator::money((float) $product->compare_price) : null,
            'discount'     => $onSale ? $product->discount_percent . '%' : null,
            'tiers'        => collect(WholesalePricing::tiers($product))
                ->map(fn ($tier) => ['label' => "Desde {$tier['min_qty']} und.", 'price' => CopyGenerator::money($tier['price'])])
                ->values()->all(),
            'templates'    => array_keys(self::availableFor($product)),
        ];
    }

    public static function brand(Store $store, BrandKit $kit): array
    {
        $domain = $store->custom_domain && str_contains($store->custom_domain, '.')
            ? $store->custom_domain
            : preg_replace('#^https?://#', '', route('store.show', $store->slug));

        return [
            'name'      => $store->name,
            'logo'      => $store->logo_path ? $store->logo_url : null,
            'primary'   => $kit->primary_color ?: '#0f766e',
            'secondary' => $kit->secondary_color ?: '#111827',
            'slogan'    => $kit->slogan,
            'whatsapp'  => $kit->show_whatsapp && $store->whatsapp_phone ? trim($store->whatsapp_phone) : null,
            'domain'    => $domain,
        ];
    }

    private static function onSale(Product $product): bool
    {
        return $product->compare_price && (float) $product->compare_price > (float) $product->price;
    }
}
