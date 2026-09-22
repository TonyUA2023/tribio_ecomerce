<?php

namespace App\Services\Storefront;

use App\Models\Store;

/**
 * Everything a template's home page (templates.{key}.store) receives. Shared by the
 * public storefront (StoreController::show) and the dashboard's template preview, so a
 * preview is rendered from exactly the same data a real visitor would get — without
 * counting as a store visit.
 */
class StorefrontHomeData
{
    public function build(Store $store, bool $isEditor = false): array
    {
        $featuredProducts = $store->featuredProducts()
            ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
            ->with(['categories', 'category'])
            ->limit(8)
            ->get();
        // Cargar categorias principales con sus hijos, y 1 producto para la mega-imagen
        $categories       = $store->categories()->whereNull('parent_id')
                                ->with([
                                    'children',
                                    'products' => function($q) {
                                        $q->where(fn($sq) => $sq->where('price', '>', 0)->orWhere('price_usd', '>', 0))->latest()->limit(1);
                                    }
                                ])
                                ->withCount([
                                    'activeProducts' => function($q) {
                                        $q->where(fn($sq) => $sq->where('price', '>', 0)->orWhere('price_usd', '>', 0));
                                    }
                                ])
                                ->get();

        $galleryItems     = $store->galleryItems()->where('is_active', true)->limit(12)->get();
        $allProducts      = $store->activeProducts()
            ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
            ->with(['categories', 'category'])
            ->orderByDesc('is_featured')->orderBy('sort_order')->paginate(12);

        // Usar el layout publicado si existe y no estamos en el editor, sino usar las secciones del borrador
        if (!$isEditor && is_array($store->published_layout)) {
            $sections = collect($store->published_layout)->map(function($section) {
                return (object) $section;
            })->filter(function($section) {
                return $section->is_active ?? true;
            });
        } else {
            // Si es editor o no hay publicado, mostramos el borrador
            $query = $store->sections()->orderBy('order');
            if (!$isEditor) {
                $query->where('is_active', true);
            }
            $sections = $query->get();
        }

        // Videos destacados para la portada (después del Hero)
        // 1. Primero los seleccionados manualmente con show_video_on_home = true (máximo 3)
        $homeVideoProducts = $store->activeProducts()
            ->whereNotNull('video_path')
            ->where('show_video_on_home', true)
            ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
            ->with(['categories', 'category'])
            ->limit(3)
            ->get();

        // 2. Si hay menos de 3 fijados, completar aleatoriamente con otros productos con video
        if ($homeVideoProducts->count() < 3) {
            $needed = 3 - $homeVideoProducts->count();
            $additional = $store->activeProducts()
                ->whereNotNull('video_path')
                ->whereNotIn('id', $homeVideoProducts->pluck('id'))
                ->where(fn($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
                ->with(['categories', 'category'])
                ->inRandomOrder()
                ->limit($needed)
                ->get();
            $homeVideoProducts = $homeVideoProducts->concat($additional);
        }

        return compact('store', 'featuredProducts', 'categories', 'galleryItems', 'allProducts', 'sections', 'homeVideoProducts');
    }
}
