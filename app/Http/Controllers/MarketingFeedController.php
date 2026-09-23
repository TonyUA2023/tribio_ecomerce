<?php

namespace App\Http\Controllers;

use App\Services\Marketing\Meta\CatalogFeed;
use App\Services\Marketing\Meta\FeedImage;
use App\Services\Marketing\Meta\MetaIntegrationService;
use App\Services\Storefront\StorefrontStoreResolver;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public URLs Meta's Commerce Manager reads on a schedule: the product catalog of a
 * store that turned Meta on in Dashboard → Marketing, and JPEG copies of WebP photos.
 */
class MarketingFeedController extends Controller
{
    public function meta(string $slug, StorefrontStoreResolver $stores, MetaIntegrationService $meta, CatalogFeed $feed): Response
    {
        $store = $stores->resolve($slug);
        $integration = $meta->active($store);
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
