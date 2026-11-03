<?php

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Store;
use App\Services\Inventory\InventorySchema;
use App\Services\Inventory\StockService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Compras: draft → ordered → received (or cancelled).
 *
 * Receiving is the only step that touches stock: every line that arrived enters through
 * StockService as an 'in' movement with its own lot, at its landed cost — the price paid
 * plus its share of the order's extra costs (freight, loading), spread by the value of
 * what was received. That landed cost is what Rentabilidad later charges each sale.
 *
 * A line may be bought by packaging (Fase 1e: "3 cajas" of 100): quantities and costs on the
 * order are per packaging; stock and lot costs are always per base unit.
 */
class PurchaseOrderService
{
    public function __construct(private StockService $stock, private InventorySchema $schema)
    {
    }

    /**
     * Creates or updates a draft/ordered purchase.
     *
     * @param array{supplier_id: ?int, expected_at: ?string, extra_costs: ?float, notes: ?string, mark_ordered: bool,
     *              items: list<array{product_id: int, quantity: int, unit_cost: float, unit_id?: ?int}>} $data
     */
    public function save(Store $store, array $data, ?PurchaseOrder $order, ?int $userId): PurchaseOrder
    {
        if ($order && !$order->isEditable()) {
            throw ValidationException::withMessages(['items' => 'Esta compra ya fue recibida o cancelada: no se puede cambiar.']);
        }

        $products = $this->stockProducts($store, array_column($data['items'], 'product_id'));
        $subtotal = round(array_sum(array_map(fn ($line) => $line['quantity'] * $line['unit_cost'], $data['items'])), 2);
        $extra = round((float) ($data['extra_costs'] ?? 0), 2);

        return DB::transaction(function () use ($store, $data, $order, $userId, $products, $subtotal, $extra) {
            $attributes = [
                'supplier_id' => $data['supplier_id'] ?? null,
                'expected_at' => $data['expected_at'] ?? null,
                'extra_costs' => $extra,
                'subtotal'    => $subtotal,
                'total'       => $subtotal + $extra,
                'notes'       => $data['notes'] ?? null,
            ];
            if (!empty($data['mark_ordered']) && (!$order || $order->status === 'draft')) {
                $attributes += ['status' => 'ordered', 'ordered_at' => now()];
            }

            $order = $order ? tap($order)->update($attributes) : $this->create($store, $attributes + ['user_id' => $userId]);

            $order->items()->delete();
            foreach ($data['items'] as $line) {
                $unit = $this->packaging($products[$line['product_id']], $line['unit_id'] ?? null);
                $order->items()->create([
                    'product_id'       => $products[$line['product_id']]->id,
                    'quantity_ordered' => $line['quantity'],
                    'unit_cost'        => round($line['unit_cost'], 4),
                ] + ($unit === false ? [] : ['unit_name' => $unit?->name, 'unit_factor' => $unit?->factor ?? 1]));
            }

            return $order->load('items');
        });
    }

    /**
     * Receives the order. $received is keyed by item id: ['quantity' => n, 'lot_code' => ?, 'expires_at' => ?].
     * Lines received at 0 are closed without stock. Extra costs go to what arrived.
     */
    public function receive(PurchaseOrder $order, array $received, ?int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $received, $userId) {
            $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!$order->isEditable()) {
                throw ValidationException::withMessages(['items' => 'Esta compra ya fue recibida o cancelada.']);
            }

            $items = $order->items()->with('product')->get();
            $quantities = $items->mapWithKeys(fn ($item) => [$item->id => max(0, (int) ($received[$item->id]['quantity'] ?? 0))]);
            if ($quantities->sum() === 0) {
                throw ValidationException::withMessages(['items' => 'Indica cuánto llegó de al menos un producto.']);
            }

            $shares = $this->extraCostShares($items, $quantities->all(), (float) $order->extra_costs);
            $label = 'Compra ' . $order->number . ($order->supplier ? ' · ' . $order->supplier->name : '');

            foreach ($items as $item) {
                $quantity = $quantities[$item->id];
                $lot = $received[$item->id] ?? [];
                $landed = $quantity > 0 ? round(((float) $item->unit_cost * $quantity + $shares[$item->id]) / $quantity, 4) : null;

                $item->update([
                    'quantity_received' => $quantity,
                    'landed_unit_cost'  => $landed,
                    'lot_code'          => filled($lot['lot_code'] ?? null) ? trim($lot['lot_code']) : null,
                    'expires_at'        => filled($lot['expires_at'] ?? null) ? $lot['expires_at'] : null,
                ]);

                $factor = max(1, (int) ($item->unit_factor ?? 1));
                if ($quantity > 0 && $item->product && !$item->product->trashed()) {
                    $this->stock->adjust($item->product, 'in', $quantity * $factor, $label, $userId, [
                        'unit_cost'  => round($landed / $factor, 4),
                        'code'       => $item->lot_code,
                        'expires_at' => $item->expires_at?->toDateString(),
                    ], $order->number);
                }
            }

            $order->update(['status' => 'received', 'received_at' => now(), 'ordered_at' => $order->ordered_at ?? now()]);

            return $order->load('items.product');
        });
    }

    public function cancel(PurchaseOrder $order): void
    {
        if (!$order->isEditable()) {
            throw ValidationException::withMessages(['items' => 'Esta compra ya fue recibida o cancelada.']);
        }
        $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    /**
     * Extra costs spread over the received lines by value (quantity × cost); by quantity
     * when every received line cost 0. The last line takes the rounding remainder so the
     * shares add up exactly to the extra costs.
     *
     * @return array<int, float> item id => share in soles
     */
    public function extraCostShares($items, array $quantities, float $extra): array
    {
        $shares = array_fill_keys($items->pluck('id')->all(), 0.0);
        $lines = $items->filter(fn ($item) => ($quantities[$item->id] ?? 0) > 0)->values();
        if ($extra <= 0 || $lines->isEmpty()) {
            return $shares;
        }

        $weights = $lines->mapWithKeys(fn ($item) => [$item->id => $quantities[$item->id] * (float) $item->unit_cost]);
        if ($weights->sum() <= 0) {
            $weights = $lines->mapWithKeys(fn ($item) => [$item->id => $quantities[$item->id]]);
        }

        $total = $weights->sum();
        $assigned = 0.0;
        foreach ($lines as $index => $item) {
            $share = $index === $lines->count() - 1
                ? round($extra - $assigned, 2)
                : round($extra * $weights[$item->id] / $total, 2);
            $shares[$item->id] = $share;
            $assigned += $share;
        }

        return $shares;
    }

    /**
     * The packaging bought on a line: null = base unit, false = packagings not available yet
     * (columns missing). A unit id that is not this product's is a validation error.
     */
    private function packaging(Product $product, ?int $unitId)
    {
        if (!$this->schema->unitsReady()) {
            return false;
        }
        if (!$unitId) {
            return null;
        }
        $unit = ProductUnit::where('product_id', $product->id)->find($unitId);
        if (!$unit) {
            throw ValidationException::withMessages(['items' => "Esa presentación no es de {$product->name}."]);
        }

        return $unit;
    }

    /** @return \Illuminate\Support\Collection<int, Product> only stock-tracked products of this store (combos are never bought) */
    private function stockProducts(Store $store, array $ids)
    {
        $products = $store->products()->whereIn('id', $ids)->where('track_stock', true)
            ->when($this->schema->unitsReady(), fn ($q) => $q->whereDoesntHave('components'))
            ->get()->keyBy('id');
        $unknown = array_diff(array_unique(array_map('intval', $ids)), $products->keys()->all());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['items' => 'Solo puedes comprar productos de tu tienda que tengan control de stock (los combos se arman con sus productos).']);
        }

        return $products;
    }

    private function create(Store $store, array $attributes): PurchaseOrder
    {
        for ($attempt = 0; ; $attempt++) {
            $next = (int) PurchaseOrder::where('store_id', $store->id)->count() + 1 + $attempt;
            try {
                return PurchaseOrder::create($attributes + [
                    'store_id' => $store->id,
                    'number'   => 'OC-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT),
                    'status'   => $attributes['status'] ?? 'draft',
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }
}
