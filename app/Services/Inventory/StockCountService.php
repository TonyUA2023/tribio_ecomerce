<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Conteo físico: open → applied | cancelled.
 *
 * Each line remembers what the system said at the moment that product was counted, so a
 * sale made while the store keeps selling does not show up as a loss. Applying corrects
 * the current stock by (counted − expected) through StockService::correct().
 */
class StockCountService
{
    public function __construct(private StockService $stock)
    {
    }

    public function start(Store $store, ?int $categoryId, bool $blind, ?string $notes, ?int $userId): StockCount
    {
        if ($categoryId !== null && !$store->categories()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Esa categoría no es de tu tienda.']);
        }

        $kitIds = app(InventorySchema::class)->unitsReady()
            ? ProductComponent::whereHas('kit', fn ($q) => $q->where('store_id', $store->id))->distinct()->pluck('kit_product_id')->all()
            : [];
        $products = $store->products()->where('track_stock', true)->whereNotIn('id', $kitIds)
            ->when($categoryId, fn ($q) => $q->where(fn ($q) => $q->where('category_id', $categoryId)
                ->orWhereHas('categories', fn ($q) => $q->whereKey($categoryId))))
            ->orderBy('name')->get(['id', 'stock', 'cost_price']);

        if ($products->isEmpty()) {
            throw ValidationException::withMessages(['category_id' => 'No hay productos con control de stock para contar.']);
        }

        return DB::transaction(function () use ($store, $categoryId, $blind, $notes, $userId, $products) {
            $count = $this->create($store, [
                'user_id' => $userId, 'category_id' => $categoryId, 'blind' => $blind, 'notes' => $notes, 'status' => 'open',
            ]);
            $now = now();
            StockCountItem::insert($products->map(fn (Product $p) => [
                'stock_count_id' => $count->id, 'product_id' => $p->id, 'expected_qty' => (int) $p->stock,
                'unit_cost' => $p->cost_price, 'counted_qty' => null, 'counted_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ])->all());

            return $count;
        });
    }

    /** Saves one counted quantity (null clears it). The system stock is read again now. */
    public function record(StockCount $count, int $itemId, ?int $counted): StockCountItem
    {
        if (!$count->isOpen()) {
            throw ValidationException::withMessages(['counted_qty' => 'Este conteo ya se cerró.']);
        }
        $item = $count->items()->with('product:id,stock,cost_price')->findOrFail($itemId);

        $item->update($counted === null
            ? ['counted_qty' => null, 'counted_at' => null]
            : [
                'counted_qty'  => $counted,
                'expected_qty' => (int) ($item->product?->stock ?? $item->expected_qty),
                'unit_cost'    => $item->product?->cost_price ?? $item->unit_cost,
                'counted_at'   => now(),
            ]);

        return $item;
    }

    /** Corrects the stock of every counted line with a difference. Uncounted lines are left alone. */
    public function apply(StockCount $count, ?int $userId): StockCount
    {
        return DB::transaction(function () use ($count, $userId) {
            $count = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            if (!$count->isOpen()) {
                throw ValidationException::withMessages(['count' => 'Este conteo ya se cerró.']);
            }

            $items = $count->items()->whereNotNull('counted_qty')->with('product')->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['count' => 'Aún no contaste ningún producto.']);
            }

            $value = 0.0;
            foreach ($items as $item) {
                $variance = $item->variance;
                if ($variance === 0 || !$item->product || $item->product->trashed()) {
                    continue;
                }
                $movement = $this->stock->correct($item->product, $variance, "Conteo físico {$count->number}", $userId, $count->number);
                // Missing units are worth what their lots cost; extra units, the cost at counting time.
                $value += $movement?->total_cost !== null
                    ? ($variance < 0 ? -1 : 1) * (float) $movement->total_cost
                    : (float) ($item->variance_value ?? 0);
            }

            $count->update(['status' => 'applied', 'applied_at' => now(), 'variance_value' => round($value, 2)]);

            return $count;
        });
    }

    public function cancel(StockCount $count): void
    {
        if (!$count->isOpen()) {
            throw ValidationException::withMessages(['count' => 'Este conteo ya se cerró.']);
        }
        $count->update(['status' => 'cancelled']);
    }

    private function create(Store $store, array $attributes): StockCount
    {
        for ($attempt = 0; ; $attempt++) {
            $next = StockCount::where('store_id', $store->id)->count() + 1 + $attempt;
            try {
                return StockCount::create($attributes + [
                    'store_id' => $store->id,
                    'number'   => 'CF-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }
}
