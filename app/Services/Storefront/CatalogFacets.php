<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Variant options a store actually sells (Talla → 38, 39, 40…; Color → Negro, Blanco…),
 * collected from its active products' `variant_options`. Templates use it to build the
 * catalog's size/color filters (StoreController::catalog reads them as ?attr[Name]=value)
 * and product cards use colorCount() for the "3 colores" line.
 */
class CatalogFacets
{
    public const COLOR_NAMES = ['color', 'colores', 'colour', 'colors'];
    public const SIZE_NAMES = ['talla', 'tallas', 'size', 'sizes', 'numero', 'número', 'medida'];

    /** @return array<string, list<string>> option name => sorted distinct values */
    public static function forStore(Store $store): array
    {
        $facets = [];
        $store->activeProducts()
            ->where('has_variants', true)
            ->whereNotNull('variant_options')
            ->pluck('variant_options')
            ->each(function ($options) use (&$facets) {
                foreach ((array) $options as $option) {
                    $name = trim((string) ($option['name'] ?? ''));
                    if ($name === '' || !preg_match('/^[\pL\pN _-]{1,40}$/u', $name)) {
                        continue;
                    }
                    foreach ((array) ($option['values'] ?? []) as $value) {
                        $value = trim((string) $value);
                        if ($value !== '') {
                            $facets[$name][$value] = true;
                        }
                    }
                }
            });

        return collect($facets)
            // array_keys() turns numeric sizes ("40") into ints; filters compare strings.
            ->map(fn (array $values) => self::sortValues(array_map('strval', array_keys($values))))
            ->sortBy(fn ($values, $name) => self::isSize($name) ? 0 : (self::isColor($name) ? 1 : 2))
            ->all();
    }

    public static function isColor(string $name): bool
    {
        return in_array(mb_strtolower(trim($name)), self::COLOR_NAMES, true);
    }

    public static function isSize(string $name): bool
    {
        return in_array(mb_strtolower(trim($name)), self::SIZE_NAMES, true);
    }

    /** How many colors a product comes in (0 when it has no color option). */
    public static function colorCount(Product $product): int
    {
        foreach ((array) ($product->has_variants ? $product->variant_options : []) as $option) {
            if (self::isColor((string) ($option['name'] ?? ''))) {
                return count((array) ($option['values'] ?? []));
            }
        }

        return 0;
    }

    /** Numeric sizes ascending (38, 38.5, 39…), then text values in their natural order. */
    private static function sortValues(array $values): array
    {
        usort($values, function ($a, $b) {
            $na = is_numeric(str_replace(',', '.', $a));
            $nb = is_numeric(str_replace(',', '.', $b));
            if ($na && $nb) {
                return (float) str_replace(',', '.', $a) <=> (float) str_replace(',', '.', $b);
            }
            if ($na !== $nb) {
                return $na ? -1 : 1;
            }

            return strnatcasecmp($a, $b);
        });

        return $values;
    }
}
