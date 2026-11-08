<?php

namespace App\Services\Pos;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Inventory\InventorySchema;
use App\Services\Pricing\WholesalePricing;

/**
 * The register's product search (ERP Fase 4a). A barcode scanner types the code and Enter:
 * an exact SKU or packaging barcode comes back as `exact`, so the line is added at once.
 */
class PosCatalog
{
    public const LIMIT = 20;

    public const OFFLINE_LIMIT = 3000;

    public function __construct(private InventorySchema $inventory, private PriceListService $lists)
    {
    }

    /** @return array{exact: ?array{product_id: int, variant_id: ?int, unit_id: ?int}, products: list<array>} */
    public function search(Store $store, string $term): array
    {
        $term = trim($term);
        $exact = $term !== '' ? $this->exact($store, $term) : null;

        $products = Product::where('store_id', $store->id)->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "{$term}%")->orWhere('origin_code', 'like', "{$term}%")))
            ->when($term === '', fn ($q) => $q->orderByDesc('sold_count'))
            ->orderBy('name')->limit(self::LIMIT)->get();
        if ($exact && !$products->contains('id', $exact['product_id'])) {
            $products->prepend(Product::find($exact['product_id']));
        }

        $products->load(['variants' => fn ($q) => $q->where('is_active', true)]);
        if ($this->inventory->unitsReady()) {
            $products->load('units');
        }

        $prices = $this->lists->prices($products->pluck('id')->all());

        return ['exact' => $exact, 'products' => $products->reject(fn (Product $p) => $p->isMadeToOrder($store))
            ->map(fn (Product $p) => $this->present($p, $prices[$p->id] ?? []))->values()->all()];
    }

    /**
     * Every sellable product of the store (up to OFFLINE_LIMIT), for the register's offline
     * copy: SKUs and packaging barcodes ride along, so scanning works without connection.
     *
     * @return list<array>
     */
    public function all(Store $store): array
    {
        $products = Product::where('store_id', $store->id)->where('is_active', true)->orderByDesc('sold_count')->orderBy('name')
            ->limit(self::OFFLINE_LIMIT)->with(['variants' => fn ($q) => $q->where('is_active', true)])->get()
            ->reject(fn (Product $p) => $p->isMadeToOrder($store));
        if ($this->inventory->unitsReady()) {
            $products->load('units');
        }
        $prices = $this->lists->prices($products->pluck('id')->all());

        return $products->map(fn (Product $p) => $this->present($p, $prices[$p->id] ?? []))->values()->all();
    }

    /** @param  array<string, float>  $lists  price per list (mayorista, distribuidor) */
    public function present(Product $product, array $lists = []): array
    {
        $units = $this->inventory->unitsReady() ? $product->units : collect();

        return [
            'id'          => $product->id,
            'name'        => $product->name,
            'sku'         => $product->sku,
            'origin_code' => $product->origin_code,
            'price'       => (float) $product->price,
            'image'       => $product->image_path ? $product->image_url : null,
            'stock'       => (int) $product->stock,
            'track_stock' => (bool) $product->track_stock,
            'unit'        => $product->unit ?: 'und.',
            'tiers'       => WholesalePricing::ready() ? WholesalePricing::tiers($product) : [],
            'lists'       => (object) $lists,
            'variants'    => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id, 'title' => $v->title, 'sku' => $v->sku, 'price' => (float) ($v->price ?: $product->price), 'stock' => (int) $v->stock,
            ])->values()->all(),
            'units'       => $units->map(fn (ProductUnit $u) => ['id' => $u->id, 'name' => $u->name, 'factor' => (int) $u->factor, 'barcode' => $u->barcode])->values()->all(),
        ];
    }

    /** A code that names exactly one sellable thing: packaging barcode, variant SKU or product SKU. */
    private function exact(Store $store, string $code): ?array
    {
        if ($this->inventory->unitsReady()) {
            $unit = ProductUnit::where('barcode', $code)
                ->whereHas('product', fn ($q) => $q->where('store_id', $store->id)->where('is_active', true))->first();
            if ($unit) {
                return ['product_id' => $unit->product_id, 'variant_id' => null, 'unit_id' => $unit->id];
            }
        }

        $variant = ProductVariant::where('sku', $code)->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('store_id', $store->id)->where('is_active', true))->first();
        if ($variant) {
            return ['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'unit_id' => null];
        }

        $product = Product::where('store_id', $store->id)->where('is_active', true)->where('sku', $code)->first()
            ?? Product::where('store_id', $store->id)->where('is_active', true)->where('origin_code', $code)->first(); // código del fabricante / repuesto

        return $product ? ['product_id' => $product->id, 'variant_id' => null, 'unit_id' => null] : null;
    }
}
