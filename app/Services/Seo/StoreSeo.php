<?php

namespace App\Services\Seo;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Everything a search engine or a link preview (WhatsApp, Facebook, Google) needs to know about
 * a storefront page: title, description, canonical address, share image, and the list of public
 * URLs for sitemaps. One canonical address per page: the store's own domain when it has one,
 * otherwise tribioshop.com/tienda/{slug} — so the same product never competes with itself.
 */
class StoreSeo
{
    public const SITEMAP_PRODUCT_LIMIT = 5000;

    /** @return array{title: string, description: string, canonical: string, image: ?string, type: string, robots: ?string, price: ?string, currency: ?string, site_name: string} */
    public function forPage(Request $request, Store $store, ?Product $product, string $pageTitle, bool $preview = false): array
    {
        $subPath = $this->subPath($request, $store);
        $isHome = $subPath === '';

        $title = $pageTitle;
        $description = null;
        $type = 'website';
        $price = $currency = null;

        if ($product) {
            $title = $product->meta_title ?: ($pageTitle ?: "{$product->name} | {$store->name}");
            $description = $product->meta_description ?: ($product->short_description ?: $product->description);
            $type = 'product';
            if ((float) $product->price > 0) {
                $price = number_format((float) $product->price, 2, '.', '');
                $currency = 'PEN';
            }
        } elseif ($isHome) {
            $title = $store->meta_title ?: ($pageTitle ?: $store->name . ' - Tienda Online');
            $description = $store->meta_description ?: ($store->tagline ?: $store->description);
        } else {
            $title = $pageTitle ?: $store->name;
            $description = $store->meta_description ?: ($store->tagline ?: $store->description);
        }

        return [
            'title'       => Str::limit(trim(strip_tags($title)), 70, ''),
            'description' => $this->clean($description ?: "Compra en {$store->name}: catálogo online, pedidos por WhatsApp y envíos."),
            'canonical'   => $this->canonical($request, $store, $subPath),
            'image'       => $this->image($store, $product),
            'type'        => $type,
            'robots'      => $preview ? 'noindex,nofollow' : null,
            'price'       => $price,
            'currency'    => $currency,
            'site_name'   => $store->name,
        ];
    }

    /** Address of the store's home page on its canonical host. */
    public function origin(Store $store): string
    {
        return rtrim($store->url, '/');
    }

    /** @return \Generator<array{loc: string, lastmod: ?string, priority: string}> */
    public function storeUrls(Store $store): \Generator
    {
        $base = $this->origin($store);
        $now = $store->updated_at?->toAtomString();

        yield ['loc' => $base, 'lastmod' => $now, 'priority' => '1.0'];
        foreach (['catalogo' => '0.8', 'galeria' => '0.4', 'contacto' => '0.5'] as $path => $priority) {
            yield ['loc' => "{$base}/{$path}", 'lastmod' => $now, 'priority' => $priority];
        }

        foreach ($store->categories()->whereNull('parent_id')->get(['slug', 'updated_at']) as $category) {
            yield ['loc' => "{$base}/catalogo?category=" . rawurlencode($category->slug), 'lastmod' => $category->updated_at?->toAtomString(), 'priority' => '0.7'];
        }

        $products = $store->products()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
            ->orderByDesc('updated_at')
            ->limit(self::SITEMAP_PRODUCT_LIMIT)
            ->get(['slug', 'updated_at']);
        foreach ($products as $product) {
            yield ['loc' => "{$base}/producto/" . rawurlencode($product->slug), 'lastmod' => $product->updated_at?->toAtomString(), 'priority' => '0.9'];
        }
    }

    /** Path inside the store: '' for the home page, 'catalogo', 'producto/polo'… */
    private function subPath(Request $request, Store $store): string
    {
        $path = trim($request->path(), '/');
        $prefix = 'tienda/' . $store->slug;
        if ($path === $prefix) {
            return '';
        }
        if (str_starts_with($path, $prefix . '/')) {
            return substr($path, strlen($prefix) + 1);
        }

        return $path === '/' ? '' : $path;
    }

    private function canonical(Request $request, Store $store, string $subPath): string
    {
        $url = $this->origin($store) . ($subPath === '' ? '' : '/' . $subPath);
        // Only parameters that change which products the page lists; sorting and filters would create duplicates.
        $keep = array_filter([
            'category' => $request->query('category'),
            'page'     => ((int) $request->query('page', 1)) > 1 ? (int) $request->query('page') : null,
        ], fn ($v) => is_string($v) ? $v !== '' : !empty($v));

        return $keep ? $url . '?' . http_build_query($keep) : $url;
    }

    private function image(Store $store, ?Product $product): ?string
    {
        if ($product && $product->image_path) {
            return $product->image_url;
        }
        if ($store->cover_path) {
            return $store->cover_url;
        }

        return $store->logo_path ? $store->logo_url : null;
    }

    private function clean(?string $text): string
    {
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text)), 160, '…');
    }
}
