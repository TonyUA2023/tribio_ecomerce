<?php

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use App\Services\Inventory\InventorySchema;
use Illuminate\Support\Collection;

/**
 * Type-ahead behind "Compras → Producto": looks products up on the server so the form never ships
 * the whole catalog (a ferretería can have thousands). Only what can be bought: stock-tracked,
 * non-combo products of the store.
 */
class PurchaseProductSearch
{
    public const LIMIT = 20;

    public function __construct(private InventorySchema $inventory)
    {
    }

    /** @return list<array{id:int,name:string,sku:?string,cost:float,stock:int,units:list<array>,hint:?string}> */
    public function search(Store $store, string $term): array
    {
        $term = trim(preg_replace('/\s+/u', ' ', $term) ?? '');
        $units = $this->inventory->unitsReady();

        $query = $this->buyable($store, $units);
        if ($term === '') {
            return $this->present($this->suggestions($store, $query), $units);
        }

        $words = array_slice(explode(' ', $term), 0, 6);
        foreach ($words as $word) {
            $like = '%' . $this->escape($word) . '%';
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("sku LIKE ? ESCAPE '!'", [$like])
                ->when($units, fn ($q) => $q->orWhereHas('units', fn ($u) => $u->where('barcode', $word))));
        }

        // Exact SKU / barcode first, then names starting with the text, then alphabetical.
        $lower = mb_strtolower($term);
        $found = $query->orderBy('name')->limit(200)->get()
            ->sortBy(fn (Product $p) => [
                mb_strtolower((string) $p->sku) === $lower || ($units && $p->units->contains('barcode', $term)) ? 0 : (str_starts_with(mb_strtolower($p->name), $lower) ? 1 : 2),
                mb_strtolower($p->name),
            ])->take(self::LIMIT)->values();

        return $this->present($found, $units);
    }

    /** Products already in an order being edited: the form needs their data without a search. */
    public function byIds(Store $store, array $ids): Collection
    {
        $units = $this->inventory->unitsReady();

        // No "buyable" filter: a saved line must still show its product even if it was changed since.
        return Product::where('store_id', $store->id)->whereIn('id', $ids)
            ->select(['id', 'name', 'sku', 'cost_price', 'stock', 'low_stock_alert', 'track_stock'])
            ->when($units, fn ($q) => $q->with('units'))->get();
    }

    /** @return list<array> */
    public function present(Collection $products, ?bool $units = null): array
    {
        $units ??= $this->inventory->unitsReady();

        return $products->map(fn (Product $p) => [
            'id'    => $p->id,
            'name'  => $p->name,
            'sku'   => $p->sku,
            'cost'  => (float) $p->cost_price,
            'stock' => (int) $p->stock,
            'low'   => $p->track_stock && $p->stock <= (int) $p->low_stock_alert,
            'units' => $units ? $p->units->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'factor' => $u->factor])->values()->all() : [],
        ])->values()->all();
    }

    private function buyable(Store $store, bool $units)
    {
        return Product::where('store_id', $store->id)->where('track_stock', true)
            ->select(['id', 'name', 'sku', 'cost_price', 'stock', 'low_stock_alert', 'track_stock'])
            ->when($units, fn ($q) => $q->whereDoesntHave('components')->with('units'));
    }

    /** With nothing typed: what is running out first, then what was bought last. */
    private function suggestions(Store $store, $query): Collection
    {
        $low = (clone $query)->whereColumn('stock', '<=', 'low_stock_alert')->orderBy('stock')->orderBy('name')->limit(self::LIMIT)->get();
        if ($low->count() >= self::LIMIT) {
            return $low;
        }

        $recentIds = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->where('store_id', $store->id))
            ->latest('id')->limit(60)->pluck('product_id')->unique()->diff($low->pluck('id'))->take(self::LIMIT - $low->count())->values();
        $recent = $recentIds->isEmpty() ? collect() : (clone $query)->whereIn('id', $recentIds)->get()->sortBy(fn ($p) => $recentIds->search($p->id));

        return $low->concat($recent)->values();
    }

    private function escape(string $value): string
    {
        // "!" as escape char: portable (SQLite has no default one, MySQL treats a backslash specially).
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
