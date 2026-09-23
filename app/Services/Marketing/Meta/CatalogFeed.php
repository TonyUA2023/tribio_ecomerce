<?php

namespace App\Services\Marketing\Meta;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The store's product catalog as an RSS 2.0 feed with the Google "g:" namespace — the
 * format Meta's Commerce Manager reads on a schedule (and Google Merchant Center too).
 * Served at /feed/facebook.xml (custom domain) and /tienda/{slug}/feed/facebook.xml.
 */
class CatalogFeed
{
    private const CACHE_MINUTES = 15;

    public function __construct(private CatalogItemMapper $mapper) {}

    /**
     * Cached briefly: Meta fetches hourly at most, and the cache keeps someone hammering
     * the public URL from rebuilding it. Keyed by host because image URLs follow it.
     */
    public function xml(Store $store, StoreMarketingIntegration $integration): string
    {
        $key = sprintf('marketing.feed.meta.%d.%s.%s', $store->id, sha1(request()->getSchemeAndHttpHost()), $integration->updated_at?->timestamp);

        return Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), fn () => $this->build($store, $integration));
    }

    public function build(Store $store, StoreMarketingIntegration $integration): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $xml->startElement('channel');
        $xml->writeElement('title', $this->clean($store->name));
        $xml->writeElement('link', $store->url);
        $xml->writeElement('description', $this->clean($store->tagline ?: $store->description ?: $store->name));

        $this->products($store)->chunkById(200, function ($products) use ($xml, $store, $integration) {
            foreach ($products as $product) {
                try {
                    $items = $this->mapper->items($product, $store, $integration);
                } catch (\Throwable $e) {
                    // One malformed product must never take the whole catalog down.
                    Log::warning('Producto omitido del catálogo de Meta.', ['product_id' => $product->id, 'error' => $e->getMessage()]);
                    continue;
                }
                foreach ($items as $item) {
                    $this->writeItem($xml, $item);
                }
            }
        });

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /**
     * For the dashboard: how many products/items go to Meta and why the rest don't.
     *
     * @return array{products_total: int, products_included: int, items: int, excluded: list<array{product: Product, reason: string}>}
     */
    public function diagnostics(Store $store, StoreMarketingIntegration $integration): array
    {
        $result = ['products_total' => 0, 'products_included' => 0, 'items' => 0, 'excluded' => []];

        $this->products($store)->chunkById(200, function ($products) use (&$result, $store, $integration) {
            foreach ($products as $product) {
                $result['products_total']++;
                $reason = $this->mapper->exclusionReason($product);
                if ($reason !== null) {
                    $result['excluded'][] = ['product' => $product, 'reason' => $reason];
                    continue;
                }
                $result['products_included']++;
                $result['items'] += count($this->mapper->items($product, $store, $integration));
            }
        });

        return $result;
    }

    private function products(Store $store)
    {
        return Product::query()
            ->where('store_id', $store->id)
            ->with(['brand', 'category', 'variants'])
            ->orderBy('id');
    }

    /** @param array<string, string|list<string>> $item */
    private function writeItem(\XMLWriter $xml, array $item): void
    {
        $xml->startElement('item');
        foreach ($item as $field => $value) {
            foreach ((array) $value as $single) {
                $xml->writeElement('g:' . $field, $this->clean((string) $single));
            }
        }
        $xml->endElement();
    }

    /** Control characters are invalid in XML 1.0 and would make Meta reject the file. */
    private function clean(?string $value): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value);
    }
}
