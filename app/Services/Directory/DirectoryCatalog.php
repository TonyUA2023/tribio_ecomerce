<?php

namespace App\Services\Directory;

use App\Models\Product;
use App\Models\Store;
use App\Services\Storefront\ColorScale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Everything the public business directory (/negocios) shows: every active store and
 * its active products, a product search across all of them, and the daily "vitrina".
 *
 * Fairness is part of the contract — the directory is advertising for *every* client:
 * featured stores go first, stores with a catalog before empty ones, then an order that changes each day (seeded by
 * the date, so it is stable within a day and cacheable), and the showcase deals its
 * slots round-robin across stores so no single big catalog takes the whole page.
 */
class DirectoryCatalog
{
    public const SORTS = ['relevancia', 'precio_asc', 'precio_desc', 'ofertas'];

    private const STOPWORDS = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'o', 'en', 'con', 'para', 'por', 'un', 'una', 'al', 'a'];
    private const MAX_TERMS = 6;
    private const CANDIDATES = 300;

    private ?bool $hasRatings = null;
    private ?bool $hasSaleMode = null;

    // ─── Stores ──────────────────────────────────────────────────

    /**
     * Active stores, each with `directoryProducts` (its visible products, best first) and
     * `directory_accent` set, in fair order: featured first, then a daily rotation.
     */
    public function stores(?string $category = null): Collection
    {
        $stores = Store::active()
            ->when($category, fn (Builder $q) => $q->where('category', $category))
            ->get(['id', 'name', 'slug', 'custom_domain', 'category', 'tagline', 'description', 'logo_path',
                'template_name', 'template_settings', 'accent_color', 'is_featured', 'city', 'whatsapp_phone']);

        $products = $this->visibleProducts($stores->pluck('id'))->groupBy('store_id');

        return $stores
            ->each(function (Store $store) use ($products) {
                $ranked = $this->rankWithinStore($products->get($store->id, collect()));
                $ranked->each(fn (Product $p) => $p->setRelation('store', $store));
                $store->setRelation('directoryProducts', $ranked);
                $store->setAttribute('directory_accent', $this->accent($store));
            })
            ->sortBy(fn (Store $store) => [
                $store->is_featured ? 0 : 1,
                $store->directoryProducts->isNotEmpty() ? 0 : 1,
                $this->dailySeed('store', $store->id),
            ])
            ->values();
    }

    /** Business categories that actually have stores, with how many. */
    public function categories(Collection $stores): array
    {
        $counts = $stores->countBy('category');

        return collect(config('tribio.business_categories'))
            ->filter(fn ($meta, $key) => $counts->has($key))
            ->map(fn ($meta, $key) => ['key' => $key, 'label' => $meta['label'], 'icon' => $meta['icon'], 'count' => $counts[$key]])
            ->values()
            ->all();
    }

    /**
     * The daily showcase: products dealt round-robin across stores (in the stores' fair
     * order), so every store with a presentable product gets a slot before anyone gets two.
     */
    public function showcase(Collection $stores, int $limit = 12, bool $withPhotoOnly = true): Collection
    {
        $queues = $stores->map(fn (Store $store) => $store->directoryProducts
            ->filter(fn (Product $p) => $withPhotoOnly ? $this->hasPrice($p) && $p->image_path : true)
            ->values())
            ->filter(fn (Collection $queue) => $queue->isNotEmpty())
            ->values();

        $picked = collect();
        for ($round = 0; $picked->count() < $limit && $queues->contains(fn ($q) => $q->has($round)); $round++) {
            foreach ($queues as $queue) {
                if ($queue->has($round) && $picked->count() < $limit) {
                    $picked->push($queue[$round]);
                }
            }
        }

        return $picked;
    }

    /** Products on sale (compare price above price), biggest discount first, at most 2 per store. */
    public function deals(Collection $stores, int $limit = 10): Collection
    {
        return $stores->flatMap(fn (Store $store) => $store->directoryProducts
                ->filter(fn (Product $p) => $p->discount_percent !== null && $this->isAvailable($p))
                ->sortByDesc('discount_percent')
                ->take(2))
            ->sortByDesc(fn (Product $p) => [$p->discount_percent, -$this->dailySeed('deal', $p->id)])
            ->take($limit)
            ->values();
    }

    // ─── Search ──────────────────────────────────────────────────

    /**
     * Products and stores matching every term of $query (accent-insensitive through the
     * MySQL collation; naive Spanish plural folding: "tortas" finds "torta").
     *
     * @return array{terms: list<string>, products: Collection<Product>, stores: Collection<Store>}
     */
    public function search(string $query, ?string $category = null, string $sort = 'relevancia', int $limit = 48): array
    {
        $terms = $this->terms($query);
        $stores = $this->stores($category);
        if ($terms === []) {
            // Browsing without words: a category (its stores and products, dealt fairly) or
            // every deal ("Ver todas las ofertas"). A bare search with nothing returns nothing.
            $browseDeals = $sort === 'ofertas';
            if (!$category && !$browseDeals) {
                return ['terms' => [], 'products' => collect(), 'stores' => collect()];
            }
            $products = $this->showcase($stores, 500, withPhotoOnly: false)
                ->when($browseDeals, fn (Collection $c) => $c->filter(fn (Product $p) => $p->discount_percent !== null))
                ->each(fn (Product $p) => $p->setAttribute('directory_score', 0));

            return ['terms' => [], 'products' => $this->sortResults($products, $sort)->take($limit)->values(), 'stores' => $category ? $stores : collect()];
        }

        $storesById = $stores->keyBy('id');
        $candidates = Product::query()
            ->whereIn('store_id', $storesById->keys())
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('is_sold')->orWhere('is_sold', false))
            ->with('category:id,name')
            ->where(function (Builder $q) use ($terms) {
                foreach ($terms as $term) {
                    $like = "%{$term}%";
                    $q->where(fn (Builder $t) => $t->where('products.name', 'like', $like)
                        ->orWhere('products.tags', 'like', $like)
                        ->orWhere('products.short_description', 'like', $like)
                        ->orWhere('products.description', 'like', $like)
                        ->orWhereHas('store', fn (Builder $s) => $s->where('name', 'like', $like))
                        ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like)));
                }
            })
            ->limit(self::CANDIDATES)
            ->get();

        $products = $candidates
            ->each(function (Product $p) use ($storesById, $terms) {
                $p->setRelation('store', $storesById[$p->store_id]);
                $p->setAttribute('directory_score', $this->score($p, $terms));
            })
            ->pipe(fn (Collection $c) => $this->sortResults($c, $sort))
            ->take($limit)
            ->values();

        $matchingStores = $stores->filter(fn (Store $store) => $this->storeMatches($store, $terms))->values();

        return ['terms' => $terms, 'products' => $products, 'stores' => $matchingStores];
    }

    /** @return list<string> normalized, de-duplicated, plural-folded search terms */
    public function terms(string $query): array
    {
        $clean = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', Str::limit($query, 80, ''))));
        $words = array_values(array_filter(preg_split('/[\s\-]+/u', $clean) ?: [], fn ($w) => mb_strlen($w) >= 2));
        $meaningful = array_values(array_diff($words, self::STOPWORDS));
        $words = $meaningful !== [] ? $meaningful : $words;

        return array_slice(array_values(array_unique(array_map(fn ($w) => $this->fold($w), $words))), 0, self::MAX_TERMS);
    }

    // ─── Links ───────────────────────────────────────────────────

    public function storeUrl(Store $store, string $campaign = 'vitrina'): string
    {
        return $this->withUtm($store->url, $campaign);
    }

    public function productUrl(Product $product, string $campaign = 'vitrina'): string
    {
        $store = $product->store;
        $base = $store->custom_domain && str_contains($store->custom_domain, '.')
            ? rtrim($store->url, '/') . '/producto/' . $product->slug
            : route('store.product', [$store->slug, $product->slug]);

        return $this->withUtm($base, $campaign);
    }

    /** Same-origin storefront URL for the live preview iframe; `vitrina=1` keeps it out of the store's visit count. */
    public function previewUrl(Store $store): string
    {
        return route('store.show', $store->slug) . '?vitrina=1';
    }

    // ─── Product helpers (shared with the resources) ─────────────

    public function hasPrice(Product $product): bool
    {
        return (float) $product->price > 0 || (float) $product->price_usd > 0;
    }

    public function priceLabel(Product $product): string
    {
        if ((float) $product->price > 0) {
            return 'S/ ' . number_format((float) $product->price, 2);
        }

        return (float) $product->price_usd > 0 ? 'US$ ' . number_format((float) $product->price_usd, 2) : 'Consultar precio';
    }

    public function isAvailable(Product $product): bool
    {
        return !$product->track_stock || $product->stock > 0 || $product->allow_backorder
            || !empty($product->out_of_stock_message) || $this->isMadeToOrder($product);
    }

    public function isMadeToOrder(Product $product): bool
    {
        return ($product->getAttributes()['sale_mode'] ?? null) === Product::SALE_MADE_TO_ORDER;
    }

    public function rating(Product $product): ?float
    {
        $count = (int) ($product->getAttributes()['reviews_count'] ?? 0);

        return $count > 0 ? round((float) $product->getAttributes()['average_rating'], 1) : null;
    }

    /** Store rating = reviews-weighted average of its products; null without reviews. */
    public function storeRating(Store $store): ?array
    {
        $reviewed = $store->directoryProducts->filter(fn (Product $p) => $this->rating($p) !== null);
        $count = $reviewed->sum(fn (Product $p) => (int) $p->getAttributes()['reviews_count']);
        if ($count === 0) {
            return null;
        }
        $sum = $reviewed->sum(fn (Product $p) => (float) $p->getAttributes()['average_rating'] * (int) $p->getAttributes()['reviews_count']);

        return ['value' => round($sum / $count, 1), 'count' => $count];
    }

    // ─── Internals ───────────────────────────────────────────────

    private function visibleProducts(Collection $storeIds): Collection
    {
        if ($storeIds->isEmpty()) {
            return collect();
        }

        $columns = ['id', 'store_id', 'category_id', 'name', 'slug', 'price', 'compare_price', 'price_usd', 'image_path',
            'is_featured', 'track_stock', 'stock', 'allow_backorder', 'out_of_stock_message', 'is_sold', 'sort_order', 'created_at'];
        if ($this->hasSaleMode ??= Schema::hasColumn('products', 'sale_mode')) {
            $columns[] = 'sale_mode';
        }
        if ($this->hasRatings ??= Schema::hasColumn('products', 'average_rating')) {
            array_push($columns, 'average_rating', 'reviews_count');
        }

        return Product::query()
            ->whereIn('store_id', $storeIds)
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('is_sold')->orWhere('is_sold', false))
            ->get($columns);
    }

    /** Presentable first: with photo, in stock, featured by the owner, then a daily rotation. */
    private function rankWithinStore(Collection $products): Collection
    {
        return $products->sortBy(fn (Product $p) => [
            $p->image_path ? 0 : 1,
            $this->isAvailable($p) ? 0 : 1,
            $this->hasPrice($p) ? 0 : 1,
            $p->is_featured ? 0 : 1,
            $this->dailySeed('product', $p->id),
        ])->values();
    }

    private function score(Product $product, array $terms): float
    {
        $name = $this->plain($product->name);
        $fields = [
            'tags' => $this->plain((string) $product->tags),
            'short' => $this->plain((string) $product->short_description),
            'description' => $this->plain(strip_tags((string) $product->description)),
            'store' => $this->plain((string) $product->store?->name),
            'category' => $this->plain((string) $product->category?->name),
        ];

        $score = 0.0;
        $inName = 0;
        foreach ($terms as $term) {
            $plain = $this->plain($term);
            if (str_contains($name, $plain)) {
                $inName++;
                $score += preg_match('/(^|\s)' . preg_quote($plain, '/') . '/u', $name) ? 14 : 9;
            }
            $score += str_contains($fields['tags'], $plain) ? 5 : 0;
            $score += str_contains($fields['category'], $plain) ? 4 : 0;
            $score += str_contains($fields['store'], $plain) ? 3 : 0;
            $score += str_contains($fields['short'], $plain) ? 3 : 0;
            $score += str_contains($fields['description'], $plain) ? 1.5 : 0;
        }
        if ($inName === count($terms)) {
            $score += 10;
        }

        return $score
            + ($product->image_path ? 2 : 0)
            + ($this->isAvailable($product) ? 2 : -4)
            + ($product->discount_percent ? 1 : 0)
            + ($this->rating($product) ?? 0) * 0.4;
    }

    private function sortResults(Collection $products, string $sort): Collection
    {
        $price = fn (Product $p) => (float) $p->price > 0 ? (float) $p->price : PHP_FLOAT_MAX;

        return match ($sort) {
            'precio_asc' => $products->sortBy(fn (Product $p) => [$price($p), -$p->directory_score]),
            'precio_desc' => $products->sortBy(fn (Product $p) => [$price($p) === PHP_FLOAT_MAX ? 1 : 0, -(float) $p->price, -$p->directory_score]),
            'ofertas' => $products->sortBy(fn (Product $p) => [-($p->discount_percent ?? 0), -$p->directory_score]),
            default => $products->sortBy(fn (Product $p) => [-$p->directory_score, $this->dailySeed('result', $p->id)]),
        };
    }

    private function storeMatches(Store $store, array $terms): bool
    {
        $category = config("tribio.business_categories.{$store->category}.label", '');
        $haystack = $this->plain(implode(' ', [$store->name, $store->tagline, $store->description, $category]));

        foreach ($terms as $term) {
            if (!str_contains($haystack, $this->plain($term))) {
                return false;
            }
        }

        return true;
    }

    private function accent(Store $store): string
    {
        return ColorScale::normalize(data_get($store->template_settings, "{$store->template_name}.colors.primary"))
            ?? ColorScale::normalize($store->accent_color)
            ?? ColorScale::normalize(config("storefront.templates.{$store->template_name}.default_accent"))
            ?? '#1675DC';
    }

    /** "tortas" → "torta", "relojes" → "reloj", "postres" → "postr" (still matches "postre"). */
    private function fold(string $word): string
    {
        $length = mb_strlen($word);
        if ($length >= 5 && str_ends_with($word, 'es') && !preg_match('/[aeiouáéíóú]$/u', mb_substr($word, 0, -2))) {
            return mb_substr($word, 0, -2);
        }

        return $length >= 4 && str_ends_with($word, 's') ? mb_substr($word, 0, -1) : $word;
    }

    private function plain(string $text): string
    {
        return mb_strtolower(Str::ascii($text));
    }

    private function dailySeed(string $salt, int $id): int
    {
        return crc32(today()->toDateString() . "|{$salt}|{$id}");
    }

    private function withUtm(string $url, string $campaign): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query([
            'utm_source' => 'tribio', 'utm_medium' => 'directorio', 'utm_campaign' => $campaign,
        ]);
    }
}
