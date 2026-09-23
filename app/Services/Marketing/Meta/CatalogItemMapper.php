<?php

namespace App\Services\Marketing\Meta;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreMarketingIntegration;

/**
 * Product → Meta catalog items. Single source of truth for both the XML feed and the
 * dashboard's "why isn't my product in the catalog?" list, so the two never disagree.
 *
 * Item ids: `{product_id}` or `{product_id}-{variant_id}`; variants share
 * item_group_id = product id. The browser Pixel and the Conversions API send these
 * same ids (resources/js/marketing.js, PurchaseEvent), which is what lets a catalog ad
 * show the exact product someone looked at.
 */
class CatalogItemMapper
{
    public const CURRENCY = 'PEN'; // prices are stored in PEN (see Multi-Currency-Pricing)
    private const MAX_ADDITIONAL_IMAGES = 10;

    public function __construct(private FeedImage $images) {}

    public static function itemId(int $productId, ?int $variantId = null): string
    {
        return $variantId ? "{$productId}-{$variantId}" : (string) $productId;
    }

    /** Null when the product can go in the catalog, otherwise the owner-facing reason. */
    public function exclusionReason(Product $product): ?string
    {
        return match (true) {
            !$product->is_active          => 'Está desactivado en tu tienda',
            (bool) $product->exclude_from_ads => 'Lo sacaste del catálogo de anuncios',
            (bool) $product->is_sold      => 'Ya se vendió (unidad única)',
            !$product->image_path         => 'No tiene foto',
            (float) $product->price <= 0  => 'No tiene precio',
            default                       => null,
        };
    }

    /**
     * @return list<array<string, string|list<string>>> field => value(s), without the "g:" prefix
     */
    public function items(Product $product, Store $store, StoreMarketingIntegration $integration): array
    {
        if ($this->exclusionReason($product) !== null) {
            return [];
        }

        $base = $this->baseFields($product, $store, $integration);
        $variants = $product->has_variants
            ? $product->variants->filter(fn (ProductVariant $v) => $v->is_active)
            : collect();

        if ($variants->isEmpty()) {
            return [array_merge($base, [
                'id' => self::itemId($product->id),
            ], $this->priceFields((float) $product->price, (float) $product->compare_price), [
                'availability' => $this->availability($product, null, $store),
            ])];
        }

        return $variants->values()->map(function (ProductVariant $variant) use ($product, $store, $base) {
            $price = (float) $variant->price > 0 ? (float) $variant->price : (float) $product->price;
            $compare = (float) $variant->compare_price > 0 ? (float) $variant->compare_price : (float) $product->compare_price;
            $options = is_array($variant->getAttribute('attributes')) ? $variant->getAttribute('attributes') : [];

            $item = array_merge($base, [
                'id'            => self::itemId($product->id, $variant->id),
                'item_group_id' => (string) $product->id,
                'title'         => $this->clip($product->name . ($variant->title ? ' - ' . $variant->title : ''), 200),
            ], $this->priceFields($price, $compare), [
                'availability' => $this->availability($product, $variant, $store),
            ]);
            if ($variant->image_path) {
                $item['image_link'] = $this->images->url($variant->image_path);
            }
            foreach ($options as $name => $value) {
                $key = mb_strtolower((string) $name);
                if (str_contains($key, 'color')) {
                    $item['color'] = $this->clip((string) $value, 100);
                } elseif (str_contains($key, 'talla') || str_contains($key, 'size') || str_contains($key, 'tamaño')) {
                    $item['size'] = $this->clip((string) $value, 100);
                }
            }

            return $item;
        })->all();
    }

    private function baseFields(Product $product, Store $store, StoreMarketingIntegration $integration): array
    {
        $category = $product->category?->name;
        $gallery = array_slice(array_values(array_filter((array) $product->gallery_images)), 0, self::MAX_ADDITIONAL_IMAGES);

        return array_filter([
            'title'                 => $this->clip($product->name, 200),
            'description'           => $this->description($product),
            'link'                  => rtrim($store->url, '/') . '/producto/' . rawurlencode($product->slug),
            'image_link'            => $this->images->url($product->image_path),
            'additional_image_link' => array_map(fn ($path) => $this->images->url($path), $gallery),
            'brand'                 => $this->clip($product->brand?->name ?: $store->name, 100),
            'condition'             => $product->condition ?: ($integration->default_condition ?: 'new'),
            'product_type'          => $category ? $this->clip($category, 750) : null,
            // Free-form labels for building product sets in Ads Manager.
            'custom_label_0'        => $product->is_featured ? 'destacado' : null,
            'custom_label_1'        => $product->is_new ? 'nuevo' : null,
            'custom_label_2'        => $category ? $this->clip($category, 100) : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** A crossed-out compare price becomes Meta's regular price, the real one the sale price. */
    private function priceFields(float $price, float $compare): array
    {
        if ($compare > $price) {
            return ['price' => $this->money($compare), 'sale_price' => $this->money($price)];
        }

        return ['price' => $this->money($price)];
    }

    private function availability(Product $product, ?ProductVariant $variant, Store $store): string
    {
        if ($product->isMadeToOrder($store)) {
            return 'available for order';
        }
        if (!$product->track_stock) {
            return 'in stock';
        }
        $stock = $variant ? (int) $variant->stock : (int) $product->stock;
        if ($stock > 0) {
            return 'in stock';
        }

        return $product->allow_backorder ? 'available for order' : 'out of stock';
    }

    private function description(Product $product): string
    {
        $text = $product->description ?: $product->short_description ?: $product->name;
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', (string) $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->clip(trim(preg_replace('/\s+/u', ' ', $text)), 5000);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '') . ' ' . self::CURRENCY;
    }

    private function clip(string $value, int $max): string
    {
        return mb_substr(trim($value), 0, $max);
    }
}
