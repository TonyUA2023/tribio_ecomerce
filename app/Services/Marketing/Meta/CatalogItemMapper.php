<?php

namespace App\Services\Marketing\Meta;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreMarketingIntegration;

/**
 * Product → catalog items for Meta (Commerce Manager) and Google (Merchant Center).
 * Single source of truth for the XML feeds and the dashboard's "why isn't my product
 * in the catalog?" list, so they never disagree. The channel is the integration's
 * provider; both use the same RSS 2.0 "g:" format and differ only in a few values:
 *
 *                        Meta                       Google
 *   availability         "in stock"                 "in_stock"
 *   made to order        "available for order"      in_stock + max_handling_time
 *   backorder, no stock  "available for order"      in_stock (backorder needs a date we don't have)
 *   WebP photos          JPEG copy via /feed-img     as-is (Google accepts WebP)
 *   identifiers          —                          identifier_exists = no (no GTIN field yet)
 *
 * Item ids: `{product_id}` or `{product_id}-{variant_id}`; variants share
 * item_group_id = product id. The browser tags (Meta Pixel, gtag) and the Conversions
 * API send these same ids (resources/js/marketing.js, PurchaseEvent), which is what
 * lets a catalog/remarketing ad show the exact product someone looked at.
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
            (bool) $product->exclude_from_ads => 'Lo sacaste de los catálogos de anuncios',
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

        $google = $integration->provider === StoreMarketingIntegration::PROVIDER_GOOGLE;
        $base = $this->baseFields($product, $store, $integration, $google);
        $variants = $product->has_variants
            ? $product->variants->filter(fn (ProductVariant $v) => $v->is_active)
            : collect();

        if ($variants->isEmpty()) {
            return [array_merge($base, [
                'id' => self::itemId($product->id),
            ], $this->priceFields((float) $product->price, (float) $product->compare_price),
                $this->availabilityFields($product, null, $store, $google))];
        }

        return $variants->values()->map(function (ProductVariant $variant) use ($product, $store, $base, $google) {
            $price = (float) $variant->price > 0 ? (float) $variant->price : (float) $product->price;
            $compare = (float) $variant->compare_price > 0 ? (float) $variant->compare_price : (float) $product->compare_price;
            $options = is_array($variant->getAttribute('attributes')) ? $variant->getAttribute('attributes') : [];

            $item = array_merge($base, [
                'id'            => self::itemId($product->id, $variant->id),
                'item_group_id' => (string) $product->id,
                'title'         => $this->clip($product->name . ($variant->title ? ' - ' . $variant->title : ''), 150),
            ], $this->priceFields($price, $compare), $this->availabilityFields($product, $variant, $store, $google));
            if ($variant->image_path) {
                $item['image_link'] = $this->imageUrl($variant->image_path, $google);
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

    private function baseFields(Product $product, Store $store, StoreMarketingIntegration $integration, bool $google): array
    {
        $category = $product->category?->name;
        $gallery = array_slice(array_values(array_filter((array) $product->gallery_images)), 0, self::MAX_ADDITIONAL_IMAGES);

        return array_filter([
            // 150 fits both limits (Google 150, Meta 200).
            'title'                 => $this->clip($product->name, 150),
            'description'           => $this->description($product),
            'link'                  => rtrim($store->url, '/') . '/producto/' . rawurlencode($product->slug),
            'image_link'            => $this->imageUrl($product->image_path, $google),
            'additional_image_link' => array_map(fn ($path) => $this->imageUrl($path, $google), $gallery),
            'brand'                 => $this->clip($product->brand?->name ?: $store->name, 70),
            'condition'             => $product->condition ?: ($integration->default_condition ?: 'new'),
            // Products have no GTIN/barcode field yet: declare it instead of leaving Google to guess.
            'identifier_exists'     => $google ? 'no' : null,
            'product_type'          => $category ? $this->clip($category, 750) : null,
            // Free-form labels for building product sets / campaigns.
            'custom_label_0'        => $product->is_featured ? 'destacado' : null,
            'custom_label_1'        => $product->is_new ? 'nuevo' : null,
            'custom_label_2'        => $category ? $this->clip($category, 100) : null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** A crossed-out compare price becomes the regular price, the real one the sale price. */
    private function priceFields(float $price, float $compare): array
    {
        if ($compare > $price) {
            return ['price' => $this->money($compare), 'sale_price' => $this->money($price)];
        }

        return ['price' => $this->money($price)];
    }

    private function availabilityFields(Product $product, ?ProductVariant $variant, Store $store, bool $google): array
    {
        if ($product->isMadeToOrder($store)) {
            if (!$google) {
                return ['availability' => 'available for order'];
            }
            // Orders are taken now; the production time is the handling time.
            return array_filter([
                'availability'      => 'in_stock',
                'max_handling_time' => $product->lead_time_days ? (string) (int) $product->lead_time_days : null,
            ]);
        }

        $available = self::isAvailable($product, $variant, $store);
        if ($google) {
            return ['availability' => $available ? 'in_stock' : 'out_of_stock'];
        }

        $stock = $variant ? (int) $variant->stock : (int) $product->stock;
        $onBackorder = $product->track_stock && $stock <= 0 && $product->allow_backorder;

        return ['availability' => $onBackorder ? 'available for order' : ($available ? 'in stock' : 'out of stock')];
    }

    /**
     * Whether the store takes orders for it right now: made to order, stock not tracked,
     * in stock, or backorders allowed. Shared with the product page's schema.org data
     * (Google\ProductStructuredData) so the feed and the page never disagree.
     */
    public static function isAvailable(Product $product, ?ProductVariant $variant, Store $store): bool
    {
        if ($product->isMadeToOrder($store) || !$product->track_stock || $product->allow_backorder) {
            return true;
        }

        return ($variant ? (int) $variant->stock : (int) $product->stock) > 0;
    }

    /** Meta rejects WebP (served as a JPEG copy); Google accepts it as-is. */
    private function imageUrl(string $path, bool $google): string
    {
        return $google ? asset('storage/' . ltrim($path, '/')) : $this->images->url($path);
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
