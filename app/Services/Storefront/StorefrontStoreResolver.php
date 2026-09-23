<?php

namespace App\Services\Storefront;

use App\Models\Store;

/**
 * Which active store a public storefront request is for: the store a custom-domain
 * middleware already resolved, a custom domain passed as the slug, or a plain slug.
 * Single definition shared by StoreController and the other storefront controllers.
 */
class StorefrontStoreResolver
{
    public function resolve(string $slug): Store
    {
        if (request()->attributes->has('store')) {
            return request()->attributes->get('store');
        }

        // Si el slug tiene formato de dominio, buscar por dominio propio
        if (str_contains($slug, '.')) {
            return Store::where('custom_domain', $slug)
                ->active()
                ->firstOrFail();
        }

        return Store::where('slug', $slug)
            ->active()
            ->firstOrFail();
    }
}
