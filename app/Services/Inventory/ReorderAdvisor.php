<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\ProductComponent;
use App\Models\PurchaseOrderItem;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Inventario → Reposición: what to buy and what is not moving.
 *
 * Daily rate = units sold in the last `window` days / window (from 'sale' movements).
 * A product needs buying when its stock would not last the supplier's lead time plus a
 * safety margin, or when it is at/below its own low-stock alert. The suggestion fills it
 * up to cover lead time + `cover` days. Products without sales only show when they are
 * at their alert, and then the suggestion refills to twice the alert.
 */
class ReorderAdvisor
{
    public const DEFAULTS = ['window' => 30, 'lead' => 7, 'cover' => 30, 'safety' => 3, 'stale' => 90];

    public function __construct(private InventorySchema $schema)
    {
    }

    /** @return list<array{product_id: int, name: string, sku: ?string, stock: int, sold: int, daily: float, days_left: ?int, suggested: int, unit_cost: ?float, supplier_id: ?int, supplier: ?string}> */
    public function suggestions(Store $store, array $params = []): array
    {
        $p = $this->params($params);
        $sold = $this->soldSince($store->id, now()->subDays($p['window']));
        $lastPurchase = $this->lastPurchases($store->id);

        $rows = [];
        $products = $store->products()->where('track_stock', true)->where('is_active', true)
            ->whereNotIn('id', $this->kitIds($store->id))
            ->get(['id', 'name', 'sku', 'stock', 'low_stock_alert', 'cost_price']);
        foreach ($products as $product) {
            $stock = (int) $product->stock;
            $units = (int) ($sold[$product->id] ?? 0);
            $daily = $units / $p['window'];
            $alert = (int) ($product->low_stock_alert ?? 0);

            if ($daily > 0) {
                $reorderPoint = (int) ceil($daily * ($p['lead'] + $p['safety']));
                if ($stock > max($reorderPoint, $alert)) {
                    continue;
                }
                $suggested = (int) ceil($daily * ($p['lead'] + $p['cover'])) - $stock;
            } else {
                if ($alert <= 0 || $stock > $alert) {
                    continue;
                }
                $suggested = $alert * 2 - $stock;
            }

            $purchase = $lastPurchase[$product->id] ?? null;
            $rows[] = [
                'product_id'  => $product->id,
                'name'        => $product->name,
                'sku'         => $product->sku,
                'stock'       => $stock,
                'sold'        => $units,
                'daily'       => round($daily, 2),
                'days_left'   => $daily > 0 ? (int) floor($stock / $daily) : null,
                'suggested'   => max(1, $suggested),
                'unit_cost'   => $purchase?->unit_cost !== null ? (float) $purchase->unit_cost : ($product->cost_price !== null ? (float) $product->cost_price : null),
                'supplier_id' => $purchase?->supplier_id,
                'supplier'    => $purchase?->supplier_name,
            ];
        }

        // Most urgent first: fewest days left (no sales = least urgent), then most sold.
        usort($rows, fn ($a, $b) => [$a['days_left'] ?? PHP_INT_MAX, -$a['sold']] <=> [$b['days_left'] ?? PHP_INT_MAX, -$b['sold']]);

        return $rows;
    }

    /** Products with stock and no sale in `stale` days, with the money tied up in them. */
    public function stagnant(Store $store, array $params = []): array
    {
        $p = $this->params($params);
        $sold = $this->soldSince($store->id, now()->subDays($p['stale']));
        $lotValues = $this->schema->lotsReady()
            ? DB::table('stock_lots')->where('store_id', $store->id)->where('quantity_remaining', '>', 0)->whereNotNull('unit_cost')
                ->groupBy('product_id')->pluck(DB::raw('SUM(quantity_remaining * unit_cost)'), 'product_id')
            : collect();

        return $store->products()->where('track_stock', true)->where('stock', '>', 0)
            ->whereNotIn('id', array_keys($sold))
            ->whereNotIn('id', $this->kitIds($store->id))
            ->get(['id', 'name', 'stock', 'cost_price', 'created_at'])
            ->filter(fn ($product) => $product->created_at->lt(now()->subDays($p['stale'])))
            ->map(fn ($product) => [
                'product_id' => $product->id,
                'name'       => $product->name,
                'stock'      => (int) $product->stock,
                'value'      => round((float) ($lotValues[$product->id] ?? ((float) $product->cost_price * $product->stock)), 2),
            ])
            ->sortByDesc('value')->values()->all();
    }

    public function params(array $input): array
    {
        $clamp = fn ($key, $min, $max) => min($max, max($min, (int) ($input[$key] ?? self::DEFAULTS[$key])));

        return [
            'window' => $clamp('window', 7, 180),
            'lead'   => $clamp('lead', 0, 90),
            'cover'  => $clamp('cover', 7, 180),
            'safety' => self::DEFAULTS['safety'],
            'stale'  => self::DEFAULTS['stale'],
        ];
    }

    /** @return list<int> combos of the store (their stock is derived from their components) */
    private function kitIds(int $storeId): array
    {
        if (!$this->schema->unitsReady()) {
            return [];
        }

        return ProductComponent::whereHas('kit', fn ($q) => $q->where('store_id', $storeId))
            ->distinct()->pluck('kit_product_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<int, int> product id => units sold since $from. A component taken out
     * because its combo was sold ('out' with the order as reference) is a sale too.
     */
    private function soldSince(int $storeId, $from): array
    {
        return InventoryMovement::where('store_id', $storeId)
            ->where(fn ($q) => $q->where('type', 'sale')->orWhere(fn ($q) => $q->where('type', 'out')->whereNotNull('reference')))
            ->where('created_at', '>=', $from)
            ->groupBy('product_id')->pluck(DB::raw('SUM(-quantity)'), 'product_id')
            ->map(fn ($units) => (int) $units)->all();
    }

    /** Last received purchase line per product, with its supplier. */
    private function lastPurchases(int $storeId): array
    {
        if (!DB::getSchemaBuilder()->hasTable('purchase_order_items')) {
            return [];
        }

        return PurchaseOrderItem::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.store_id', $storeId)
            ->where('purchase_orders.status', 'received')
            ->orderBy('purchase_order_items.id')
            ->get(['purchase_order_items.product_id', DB::raw($this->schema->unitsReady()
                ? 'purchase_order_items.unit_cost / CASE WHEN purchase_order_items.unit_factor > 0 THEN purchase_order_items.unit_factor ELSE 1 END as unit_cost'
                : 'purchase_order_items.unit_cost'), 'purchase_orders.supplier_id', 'suppliers.name as supplier_name'])
            ->keyBy('product_id')   // ordered by id: the last one wins
            ->all();
    }
}
