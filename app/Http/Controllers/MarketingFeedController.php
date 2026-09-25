<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\Google\GoogleIntegrationService;
use App\Services\Marketing\Google\MarketplaceFeed;
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

    /**
     * The store's own Google Merchant Center account ("Uso mi propia cuenta"). Stores
     * published by Tribio are in the marketplace feed below instead — serving both
     * would list the same products twice.
     */
    public function google(string $slug, StorefrontStoreResolver $stores, GoogleIntegrationService $google, CatalogFeed $feed): Response
    {
        $store = $stores->resolve($slug);
        $integration = $google->active($store);

        return $this->feed($feed, $store, $integration?->usesOwnMerchantCenter() ? $integration : null);
    }

    /** Tribio's own Merchant Center (marketplace) feed: every store published via Tribio. */
    public function googleMarketplace(string $token, MarketplaceFeed $marketplace): BinaryFileResponse
    {
        abort_unless($marketplace->enabled() && $marketplace->tokenMatches($token), 404);

        return response()->file($marketplace->file(), [
            'Content-Type'  => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag'  => 'noindex',
        ]);
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
