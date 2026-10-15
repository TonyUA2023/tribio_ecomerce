<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Seo\StoreSeo;
use App\Services\Storefront\StorefrontStoreResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** sitemap.xml and robots.txt for the platform and for every store (also on custom domains). */
class SitemapController extends Controller
{
    public function __construct(private readonly StoreSeo $seo)
    {
    }

    /** `/sitemap.xml`: a store's sitemap on its own domain, otherwise the platform index. */
    public function index(Request $request): Response
    {
        if ($store = $request->attributes->get('store')) {
            return $this->storeSitemap($store);
        }

        // Stores on their own domain are listed on that domain, not here: a sitemap may only
        // reference URLs of the host that serves it.
        $stores = Store::active()->where(fn ($q) => $q->whereNull('custom_domain')->orWhere('custom_domain', ''))
            ->orderBy('id')->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $xml .= $this->indexEntry(url('/sitemap-pages.xml'), null);
        foreach ($stores as $store) {
            $xml .= $this->indexEntry(route('store.sitemap', $store->slug), $store->updated_at?->toAtomString());
        }

        return $this->xml($xml . '</sitemapindex>');
    }

    /** Public pages of the platform itself (landing, directory, legal). */
    public function pages(): Response
    {
        $urls = [
            ['loc' => url('/'), 'priority' => '1.0'],
            ['loc' => route('directory'), 'priority' => '0.8'],
        ];
        foreach (['legal.terms', 'legal.privacy'] as $name) {
            if (\Illuminate\Support\Facades\Route::has($name)) {
                $urls[] = ['loc' => route($name), 'priority' => '0.3'];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>' . e($u['loc']) . '</loc><priority>' . $u['priority'] . '</priority></url>';
        }

        return $this->xml($xml . '</urlset>');
    }

    /** `/tienda/{slug}/sitemap.xml` */
    public function store(string $slug, StorefrontStoreResolver $resolver): Response
    {
        return $this->storeSitemap($resolver->resolve($slug));
    }

    public function robots(Request $request): Response
    {
        $origin = $request->getSchemeAndHttpHost();
        $lines = [
            'User-agent: *',
            'Allow: /',
            // Private or useless to a search engine: back office, buyer account, payments, order pages, APIs.
            'Disallow: /dashboard',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /logout',
            'Disallow: /api/',
            'Disallow: /customer/',
            'Disallow: /plan/',
            'Disallow: /adjuntos/',
            'Disallow: /checkout',
            'Disallow: /pedido/',
            'Disallow: /tienda/*/checkout',
            'Disallow: /tienda/*/pedido/',
            'Disallow: /tienda/*/saldo/',
            '',
            "Sitemap: {$origin}/sitemap.xml",
        ];

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    private function storeSitemap(Store $store): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($this->seo->storeUrls($store) as $u) {
            $xml .= '<url><loc>' . e($u['loc']) . '</loc>'
                . ($u['lastmod'] ? '<lastmod>' . $u['lastmod'] . '</lastmod>' : '')
                . '<priority>' . $u['priority'] . '</priority></url>';
        }

        return $this->xml($xml . '</urlset>');
    }

    private function indexEntry(string $loc, ?string $lastmod): string
    {
        return '<sitemap><loc>' . e($loc) . '</loc>' . ($lastmod ? '<lastmod>' . $lastmod . '</lastmod>' : '') . '</sitemap>';
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
