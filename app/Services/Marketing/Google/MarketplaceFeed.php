<?php

namespace App\Services\Marketing\Google;

use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\MarketingSchema;
use App\Services\Marketing\Meta\CatalogFeed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Google Shopping for every store, with no domain and no Google account of their own:
 * Tribio's Merchant Center account is a *marketplace* (multi-seller account) and this
 * is its single feed — the products of every store whose owner chose "Tribio publica
 * mis productos" (shopping_mode = 'tribio'). Google requirements this follows:
 *   - one feed for the multi-seller account, each item tagged with external_seller_id;
 *   - links on the domain Tribio verified and claimed (APP_URL/tienda/{slug}/…), which
 *     also covers stores that later buy their own domain — nothing changes for them;
 *   - shipping per offer (each store's flat national rate); the return policy is set
 *     once for the whole account in Merchant Center.
 *
 * Served at /feeds/google-marketplace/{token}.xml, token from
 * config('services.google_marketplace.feed_token'); no token = feature off.
 */
class MarketplaceFeed
{
    private const FILE = 'feeds/google-marketplace.xml';
    private const MAX_AGE_MINUTES = 60;

    public function __construct(
        private CatalogFeed $feed,
        private MarketingSchema $schema,
    ) {}

    /** Tribio configured its marketplace account (and the Google columns exist). */
    public function enabled(): bool
    {
        return filled(config('services.google_marketplace.feed_token')) && $this->schema->googleReady();
    }

    public function tokenMatches(string $token): bool
    {
        $expected = (string) config('services.google_marketplace.feed_token');

        return $expected !== '' && hash_equals($expected, $token);
    }

    public function url(): ?string
    {
        $token = (string) config('services.google_marketplace.feed_token');

        return $token === '' ? null : rtrim((string) config('app.url'), '/') . "/feeds/google-marketplace/{$token}.xml";
    }

    /** Google's external_seller_id: [A-Za-z0-9_~.-]{1,50}, stable per store (ids never change, slugs might). */
    public static function sellerId(Store $store): string
    {
        return 'tribio-store-' . $store->id;
    }

    /** Active stores whose Google connection is on and published through Tribio. */
    public function stores()
    {
        return Store::active()
            ->whereHas('googleIntegration', fn ($q) => $q->where('is_active', true)
                ->where('shopping_mode', StoreMarketingIntegration::SHOPPING_VIA_TRIBIO))
            ->with('googleIntegration');
    }

    /** Absolute path of the feed file, rebuilt when older than an hour (Google fetches daily). */
    public function file(): string
    {
        $disk = Storage::disk('local');
        $fresh = $disk->exists(self::FILE)
            && $disk->lastModified(self::FILE) > now()->subMinutes(self::MAX_AGE_MINUTES)->timestamp;

        if (!$fresh) {
            $lock = Cache::lock('marketing.google-marketplace-feed', 600);
            if ($lock->get()) {
                try {
                    $this->rebuild();
                } finally {
                    $lock->release();
                }
            } elseif (!$disk->exists(self::FILE)) {
                $this->rebuild(); // first build ever and someone else holds the lock: don't serve a 404
            }
        }

        return $disk->path(self::FILE);
    }

    /**
     * Writes to a temp file and swaps it in, so Google never downloads half a file and a
     * large catalog is never held in memory.
     *
     * @return array{stores: int, items: int}
     */
    public function rebuild(): array
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('feeds');
        $tmp = $disk->path(self::FILE . '.tmp');
        $platform = rtrim((string) config('app.url'), '/');

        $xml = $this->feed->open('Tribio', $platform, 'Productos de las tiendas creadas con Tribio', $tmp);
        $stats = ['stores' => 0, 'items' => 0];
        $this->stores()->chunkById(50, function ($stores) use ($xml, $platform, &$stats) {
            foreach ($stores as $store) {
                $written = $this->feed->writeStore($xml, $store, $store->googleIntegration, $platform, array_filter([
                    'external_seller_id' => self::sellerId($store),
                    'shipping'           => $this->shipping($store),
                ]));
                $stats['stores'] += $written > 0 ? 1 : 0;
                $stats['items'] += $written;
            }
        });
        $this->feed->close($xml);

        rename($tmp, $disk->path(self::FILE));

        return $stats;
    }

    /** The store's flat national rate; zone-based rates fall back to the account's default shipping. */
    private function shipping(Store $store): ?array
    {
        if ($store->national_shipping_cost === null) {
            return null;
        }

        return ['country' => 'PE', 'price' => number_format((float) $store->national_shipping_cost, 2, '.', '') . ' PEN'];
    }
}
