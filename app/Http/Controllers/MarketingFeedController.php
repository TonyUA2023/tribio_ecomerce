<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\Google\GoogleIntegrationService;
use App\Services\Marketing\Meta\CatalogFeed;
use App\Services\Marketing\Meta\FeedImage;
use App\Services\Marketing\Meta\MetaIntegrationService;
use App\Services\Storefront\StorefrontStoreResolver;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public URLs Meta's Commerce Manager and Google Merchant Center read on a schedule:
 * the product catalog of a store that turned the channel on in Dashboard → Marketing,
 * and JPEG copies of WebP photos (for Meta).
 */
class MarketingFeedController extends Controller
{
    public function meta(string $slug, StorefrontStoreResolver $stores, MetaIntegrationService $meta, CatalogFeed $feed): Response
    {
        $store = $stores->resolve($slug);

        return $this->feed($feed, $store, $meta->active($store));
    }

    /** Google Merchant Center ("Agregar productos desde un archivo" → vínculo, diario). */
    public function google(string $slug, StorefrontStoreResolver $stores, GoogleIntegrationService $google, CatalogFeed $feed): Response
    {
        $store = $stores->resolve($slug);

        return $this->feed($feed, $store, $google->active($store));
    }

    private function feed(CatalogFeed $feed, Store $store, ?StoreMarketingIntegration $integration): Response
    {
        abort_unless($integration, 404);

        return response($feed->xml($store, $integration), 200, [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
            'X-Robots-Tag'  => 'noindex',
        ]);
    }

    public function image(string $path, FeedImage $images): BinaryFileResponse
    {
        $file = str_ends_with($path, '.jpg') ? $images->jpeg(substr($path, 0, -4)) : null;
        abort_unless($file, 404);

        return response()->file($file, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
