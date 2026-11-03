<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductVariant;
use App\Models\StockLot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only way stock changes: manual adjustments (web and mobile), sales (checkout) and
 * the product form (reconcile()). Each change locks the product row, reads the stock
 * again under the lock and writes the movement in the same transaction, so two cashiers
 * or two buyers can never both act on the same stale number.
 *
 * Lots (Fase 1, once InventorySchema::lotsReady()): products.stock stays the total the
 * storefront, app and catalogs read; stock_lots are the receipts behind it, each with
 * its cost and expiry. Stock leaves the lot that expires first (FEFO), else the oldest
 * (FIFO), and the movement records what it cost — that is the margin per sale.
 *
 * Combos (Fase 1e, once InventorySchema::unitsReady()): a product with components has a
 * derived stock — how many combos its components allow — kept in products.stock so the
 * storefront needs nothing new. Selling a combo takes its components out ('out' movements
 * with the order as reference) and writes the combo's own 'sale' movement at their cost.
 */
class StockService
{
    public const MANUAL_TYPES = ['in', 'out', 'adjustment', 'loss'];

    public function __construct(private InventorySchema $schema)
    {
    }

    /**
     * Owner's manual change. `adjustment` sets the absolute stock; the others add or remove.
     * Stock never goes below 0 (the column is unsigned). An entry may carry its lot data:
     * ['unit_cost' => 12.5, 'code' => 'L-2311', 'expires_at' => '2027-05-31'].
     * $reference names the document behind it (a purchase order number).
     */
    public function adjust(Product $product, string $type, int $quantity, ?string $reason, ?int $userId, array $lot = [], ?string $reference = null): InventoryMovement
    {
        if (!in_array($type, self::MANUAL_TYPES, true)) {
            throw new \InvalidArgumentException("Tipo de movimiento no válido: {$type}");
        }
        if ($this->isKit($product)) {
            throw ValidationException::withMessages(['type' => 'Es un combo: su stock se calcula con el de sus productos. Mueve el stock de cada producto.']);
        }

        return DB::transaction(function () use ($product, $type, $quantity, $reason, $userId, $lot, $reference) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->stock;
            $after = match ($type) {
                'in'          => $before + $quantity,
                'out', 'loss' => max(0, $before - $quantity),
                'adjustment'  => max(0, $quantity),
            };

            $locked->update(['stock' => $after]);
            $movement = InventoryMovement::create([
                'product_id'   => $locked->id,
                'store_id'     => $locked->store_id,
                'type'         => $type,
                'quantity'     => $after - $before,
                'stock_before' => $before,
                'stock_after'  => $after,
                'reason'       => $reason ?: 'Ajuste manual',
                'reference'    => $reference,
                'user_id'      => $userId,
            ]);

            if ($this->schema->lotsReady() && $locked->track_stock) {
                $this->alignLots($locked, $before);
                if ($after > $before) {
                    $this->receiveLot($locked, $movement, $after - $before, $lot, $type === 'in' ? 'receipt' : 'adjustment');
                } elseif ($after < $before) {
                    $this->consumeLots($locked, $movement, $before - $after);
                }
            }

            $this->refreshKitsUsing([$locked->id]);
            $product->setRawAttributes($locked->getAttributes(), true);

            return $movement;
        });
    }

    /**
     * A count correction: adds or removes $delta from whatever the stock is now (a count
     * measures the difference at the moment of counting; sales made since then stay
     * valid). Missing units leave the soonest-expiring lots and are valued at their cost;
     * extra units enter as an 'adjustment' lot at the product's cost.
     */
    public function correct(Product $product, int $delta, string $reason, ?int $userId, ?string $reference = null): ?InventoryMovement
    {
        if ($delta === 0) {
            return null;
        }

        return DB::transaction(function () use ($product, $delta, $reason, $userId, $reference) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->stock;
            $after = max(0, $before + $delta);

            $locked->update(['stock' => $after]);
            $movement = InventoryMovement::create([
                'product_id'   => $locked->id,
                'store_id'     => $locked->store_id,
                'type'         => 'adjustment',
                'quantity'     => $after - $before,
                'stock_before' => $before,
                'stock_after'  => $after,
                'reason'       => $reason,
                'reference'    => $reference,
                'user_id'      => $userId,
            ]);

            if ($this->schema->lotsReady() && $locked->track_stock) {
                $this->alignLots($locked, $before);
                if ($after > $before) {
                    $this->receiveLot($locked, $movement, $after - $before, [], 'adjustment');
                } elseif ($after < $before) {
                    $this->consumeLots($locked, $movement, $before - $after);
                }
            }
            $this->refreshKitsUsing([$locked->id]);

            return $movement;
        });
    }

    /**
     * A confirmed sale. Clamped to the stock on hand: a backorder sale (checkout allows
     * buying at 0 when the product has an out-of-stock message) must never make the
     * unsigned column negative — that would throw and roll back an order a gateway may
     * already have charged. The shortfall is written in the movement for the owner.
     */
    public function sell(Product $product, ?ProductVariant $variant, int $quantity, string $reference): ?InventoryMovement
    {
        if (!$product->track_stock || $quantity <= 0) {
            return null;
        }
        if ($this->isKit($product)) {
            return $this->sellKit($product, $quantity, $reference);
        }

        return DB::transaction(function () use ($product, $variant, $quantity, $reference) {
            if ($variant) {
                $lockedVariant = ProductVariant::whereKey($variant->id)->lockForUpdate()->first();
                if ($lockedVariant) {
                    $lockedVariant->update(['stock' => max(0, (int) $lockedVariant->stock - $quantity)]);
                }
            }

            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->stock;
            $taken = min($quantity, $before);
            $after = $before - $taken;
            $locked->update(['stock' => $after]);

            $missing = $quantity - $taken;
            $movement = InventoryMovement::create([
                'product_id'   => $locked->id,
                'store_id'     => $locked->store_id,
                'type'         => 'sale',
                'quantity'     => -$taken,
                'stock_before' => $before,
                'stock_after'  => $after,
                'reason'       => $missing > 0
                    ? "Venta ({$quantity} und.) con stock insuficiente: faltaron {$missing}"
                    : "Venta ({$quantity} und.)",
                'reference'    => $reference,
            ]);

            if ($this->schema->lotsReady() && $taken > 0) {
                $this->alignLots($locked, $before);
                $this->consumeLots($locked, $movement, $taken);
            }
            $this->refreshKitsUsing([$locked->id]);

            return $movement;
        });
    }

    /**
     * Units a customer brought back (ERP Fase 4c): a 'return' movement and a lot at the
     * product's cost. A combo puts back its components; a variant its own stock too.
     */
    public function restock(Product $product, ?ProductVariant $variant, int $quantity, string $reference, ?int $userId): ?InventoryMovement
    {
        if (!$product->track_stock || $quantity <= 0) {
            return null;
        }
        if ($this->isKit($product)) {
            return DB::transaction(function () use ($product, $quantity, $reference, $userId) {
                $last = null;
                foreach (ProductComponent::where('kit_product_id', $product->id)->get() as $component) {
                    if ($part = Product::find($component->component_product_id)) {
                        $last = $this->restock($part, null, $quantity * $component->quantity, $reference, $userId) ?? $last;
                    }
                }
                $this->refreshKit($product->id);

                return $last;
            });
        }

        return DB::transaction(function () use ($product, $variant, $quantity, $reference, $userId) {
            if ($variant && ($lockedVariant = ProductVariant::whereKey($variant->id)->lockForUpdate()->first())) {
                $lockedVariant->update(['stock' => (int) $lockedVariant->stock + $quantity]);
            }

            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->stock;
            $locked->update(['stock' => $before + $quantity]);
            $movement = InventoryMovement::create([
                'product_id'   => $locked->id,
                'store_id'     => $locked->store_id,
                'type'         => 'return',
                'quantity'     => $quantity,
                'stock_before' => $before,
                'stock_after'  => $before + $quantity,
                'reason'       => "Devolución del cliente ({$quantity} und.)",
                'reference'    => $reference,
                'user_id'      => $userId,
            ]);

            if ($this->schema->lotsReady()) {
                $this->alignLots($locked, $before);
                $this->receiveLot($locked, $movement, $quantity, [], 'return');
            }
            $this->refreshKitsUsing([$locked->id]);

            return $movement;
        });
    }

    /**
     * After products.stock was written outside this service (the product form): bring the
     * lots back in line — a new lot at the product's cost for an increase, the oldest /
     * soonest-expiring stock for a decrease.
     */
    public function reconcile(Product $product): void
    {
        if (!$this->schema->lotsReady() || !$product->track_stock) {
            return;
        }

        if ($this->isKit($product)) {
            // A combo's stock is never typed: the form value is replaced by what its components allow.
            $this->refreshKit($product->id);

            return;
        }

        DB::transaction(function () use ($product) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $this->alignLots($locked, (int) $locked->stock);
            $this->refreshKitsUsing([$locked->id]);
        });
    }

    // ─── Combos ──────────────────────────────────────────────────

    public function isKit(Product $product): bool
    {
        return $this->schema->unitsReady() && ProductComponent::where('kit_product_id', $product->id)->exists();
    }

    /** Recomputes a combo's stock from its components (no movement: it is a derived number). */
    public function refreshKit(int $kitId): void
    {
        $components = ProductComponent::where('kit_product_id', $kitId)->with('component:id,stock,deleted_at')->get();
        if ($components->isEmpty()) {
            return;
        }

        $available = $components->map(fn (ProductComponent $c) => $c->component && !$c->component->trashed() && $c->quantity > 0
            ? intdiv((int) $c->component->stock, $c->quantity)
            : 0)->min();

        Product::whereKey($kitId)->update(['stock' => max(0, (int) $available)]);
    }

    /** @param list<int> $productIds products whose stock just changed */
    public function refreshKitsUsing(array $productIds): void
    {
        if (!$this->schema->unitsReady() || $productIds === []) {
            return;
        }
        ProductComponent::whereIn('component_product_id', $productIds)->distinct()->pluck('kit_product_id')
            ->each(fn ($kitId) => $this->refreshKit((int) $kitId));
    }

    /**
     * Selling a combo: each component leaves (clamped, from its own lots) as an 'out' movement
     * referencing the order, so it counts as consumption for Reposición but never as a sale
     * of its own in Rentabilidad. The combo gets the 'sale' movement, at its components' cost.
     */
    private function sellKit(Product $kit, int $quantity, string $reference): InventoryMovement
    {
        return DB::transaction(function () use ($kit, $quantity, $reference) {
            $locked = Product::whereKey($kit->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->stock;

            $cost = 0.0;
            $known = $this->schema->lotsReady();
            foreach (ProductComponent::where('kit_product_id', $kit->id)->get() as $component) {
                $product = Product::find($component->component_product_id);
                $movement = $product ? $this->takeOut($product, $quantity * $component->quantity,
                    "Venta del combo «{$locked->name}» ({$quantity} und.)", $reference) : null;
                if ($movement?->total_cost === null) {
                    $known = false;
                } else {
                    $cost += (float) $movement->total_cost;
                }
            }

            $this->refreshKit($kit->id);
            $after = (int) Product::whereKey($kit->id)->value('stock');

            return InventoryMovement::create([
                'product_id'   => $locked->id,
                'store_id'     => $locked->store_id,
                'type'         => 'sale',
                'quantity'     => -$quantity,
                'stock_before' => $before,
                'stock_after'  => $after,
                'reason'       => "Venta de combo ({$quantity} und.)",
                'reference'    => $reference,
            ] + ($this->schema->lotsReady() ? [
                'total_cost' => $known ? round($cost, 2) : null,
                'unit_cost'  => $known ? round($cost / $quantity, 4) : null,
            ] : []));
        });
    }

    /** A component leaving because its combo was sold (clamped like a sale). */
    private function takeOut(Product $product, int $quantity, string $reason, string $reference): ?InventoryMovement
    {
        if (!$product->track_stock || $quantity <= 0) {
            return null;
        }

        $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
        $before = (int) $locked->stock;
        $taken = min($quantity, $before);
        $locked->update(['stock' => $before - $taken]);

        $missing = $quantity - $taken;
        $movement = InventoryMovement::create([
            'product_id'   => $locked->id,
            'store_id'     => $locked->store_id,
            'type'         => 'out',
            'quantity'     => -$taken,
            'stock_before' => $before,
            'stock_after'  => $before - $taken,
            'reason'       => $missing > 0 ? "{$reason}: faltaron {$missing}" : $reason,
            'reference'    => $reference,
        ]);

        if ($this->schema->lotsReady() && $taken > 0) {
            $this->alignLots($locked, $before);
            $this->consumeLots($locked, $movement, $taken);
        }
        $this->refreshKitsUsing([$locked->id]);

        return $movement->fresh();
    }

    /** Value of what is on hand, at the cost of each lot. Lots without a cost are skipped. */
    public function stockValue(int $storeId): float
    {
        if (!$this->schema->lotsReady()) {
            return (float) (Product::where('store_id', $storeId)->where('track_stock', true)
                ->selectRaw('SUM(stock * cost_price) as val')->value('val') ?? 0);
        }

        return (float) (StockLot::where('store_id', $storeId)->onHand()->whereNotNull('unit_cost')
            ->selectRaw('SUM(quantity_remaining * unit_cost) as val')->value('val') ?? 0);
    }

    // ─── Lots ────────────────────────────────────────────────────

    /**
     * Makes the lots add up to $stock before a change. Products that had stock before lots
     * existed (or whose form changed it) get the difference as a lot at their cost price;
     * lots holding more than the real stock give back the excess.
     */
    private function alignLots(Product $product, int $stock): void
    {
        $onLots = (int) StockLot::where('product_id', $product->id)->sum('quantity_remaining');

        if ($stock > $onLots) {
            $first = !StockLot::where('product_id', $product->id)->exists();
            $this->createLot($product, $stock - $onLots, [], $first ? 'opening' : 'adjustment');
        } elseif ($stock < $onLots) {
            $this->takeFromLots($product, $onLots - $stock);
        }
    }

    private function receiveLot(Product $product, InventoryMovement $movement, int $quantity, array $data, string $origin): void
    {
        $lot = $this->createLot($product, $quantity, $data, $origin);
        DB::table('stock_lot_movements')->insert([
            'inventory_movement_id' => $movement->id, 'stock_lot_id' => $lot->id, 'quantity' => $quantity,
            'unit_cost' => $lot->unit_cost, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $movement->update([
            'unit_cost'  => $lot->unit_cost,
            'total_cost' => $lot->unit_cost === null ? null : round($quantity * (float) $lot->unit_cost, 2),
        ]);

        // The last purchase cost becomes the product's reference cost (form, margins).
        if (isset($data['unit_cost']) && $data['unit_cost'] !== null && $data['unit_cost'] !== '') {
            $product->update(['cost_price' => round((float) $data['unit_cost'], 2)]);
        }
    }

    private function createLot(Product $product, int $quantity, array $data, string $origin): StockLot
    {
        $cost = $data['unit_cost'] ?? null;
        $cost = $cost === null || $cost === '' ? $product->cost_price : $cost;

        return StockLot::create([
            'store_id'           => $product->store_id,
            'product_id'         => $product->id,
            'code'               => filled($data['code'] ?? null) ? trim($data['code']) : null,
            'expires_at'         => filled($data['expires_at'] ?? null) ? $data['expires_at'] : null,
            'unit_cost'          => $cost === null ? null : round((float) $cost, 4),
            'quantity_received'  => $quantity,
            'quantity_remaining' => $quantity,
            'origin'             => $origin,
            'received_at'        => now(),
        ]);
    }

    /** Takes stock out of the lots for a movement and writes its cost (null if any lot has no cost). */
    private function consumeLots(Product $product, InventoryMovement $movement, int $quantity): void
    {
        $taken = $this->takeFromLots($product, $quantity);

        $now = now();
        DB::table('stock_lot_movements')->insert(array_map(fn ($row) => [
            'inventory_movement_id' => $movement->id, 'stock_lot_id' => $row['lot']->id, 'quantity' => -$row['quantity'],
            'unit_cost' => $row['lot']->unit_cost, 'created_at' => $now, 'updated_at' => $now,
        ], $taken));

        $units = array_sum(array_column($taken, 'quantity'));
        $known = array_filter($taken, fn ($row) => $row['lot']->unit_cost !== null);
        $total = array_sum(array_map(fn ($row) => $row['quantity'] * (float) $row['lot']->unit_cost, $known));
        $complete = $units > 0 && count($known) === count($taken);

        $movement->update([
            'total_cost' => $complete ? round($total, 2) : null,
            'unit_cost'  => $complete ? round($total / $units, 4) : null,
        ]);
    }

    /** @return list<array{lot: StockLot, quantity: int}> */
    private function takeFromLots(Product $product, int $quantity): array
    {
        $taken = [];
        $lots = StockLot::where('product_id', $product->id)->onHand()->consumptionOrder()->lockForUpdate()->get();

        foreach ($lots as $lot) {
            if ($quantity <= 0) {
                break;
            }
            $take = min($quantity, (int) $lot->quantity_remaining);
            $lot->decrement('quantity_remaining', $take);
            $taken[] = ['lot' => $lot, 'quantity' => $take];
            $quantity -= $take;
        }

        return $taken;
    }
}
