<?php

namespace App\Services\Storefront;

use App\Models\Store;

/**
 * Destinations a template `link` field can point to. Owners never type URLs: they pick
 * from this list (catalog, new arrivals, one of their categories…), which is stored as a
 * short token such as "category:polos" and resolved to a real route at render time — so
 * a renamed store slug or a moved route never leaves a banner pointing nowhere.
 */
class StorefrontLinks
{
    public const PATTERN = '/^(catalog|new|price_asc|contact|none|category:[A-Za-z0-9_-]{1,120})$/';

    public static function isValid(mixed $token): bool
    {
        return is_string($token) && preg_match(self::PATTERN, $token) === 1;
    }

    /** `['catalog' => 'Todo el catálogo', …, 'category:polos' => 'Categoría · Polos']` for the customizer. */
    public static function options(Store $store): array
    {
        $options = [
            'catalog' => 'Todo el catálogo',
            'new' => 'Novedades (lo más reciente)',
            'price_asc' => 'Precios más bajos primero',
        ];
        foreach ($store->categories()->whereNull('parent_id')->orderBy('name')->get(['id', 'name', 'slug']) as $category) {
            if ($category->slug) {
                $options['category:' . $category->slug] = 'Categoría · ' . $category->name;
            }
        }

        return $options + ['contact' => 'Página de contacto', 'none' => 'Sin enlace'];
    }

    /** Real URL for a token, or null for "none" / anything unrecognized. */
    public static function resolve(Store $store, ?string $token): ?string
    {
        if (!self::isValid($token) || $token === 'none') {
            return null;
        }
        if (str_starts_with($token, 'category:')) {
            return route('store.catalog', ['slug' => $store->slug, 'category' => substr($token, 9)]);
        }

        return match ($token) {
            'new' => route('store.catalog', ['slug' => $store->slug, 'sort' => 'newest']),
            'price_asc' => route('store.catalog', ['slug' => $store->slug, 'sort' => 'price_asc']),
            'contact' => route('store.contact', $store->slug),
            default => route('store.catalog', $store->slug),
        };
    }
}
