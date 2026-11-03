<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard → Inventario → Rentabilidad: per product, what was sold, what it cost and
 * what was earned in a period.
 *
 * Sales: items of orders that are paid (or have a deposit), not cancelled, in PEN. The
 * order-level discount is spread over its items by their share of the subtotal.
 * Cost: the 'sale' movements of those orders, at the cost of the lots they came from.
 * Units without a lot cost (made-to-order, backorder, stock before lots existed without a
 * cost price) are valued at the product's current cost price and flagged as estimated;
 * with no cost price at all they are flagged as missing.
 */
class ProfitReport
{
    public const PRESETS = [
        'mes'         => 'Este mes',
        'mes_pasado'  => 'Mes pasado',
        '30_dias'     => 'Últimos 30 días',
        'anio'        => 'Este año',
    ];

    public function __construct(private InventorySchema $schema)
    {
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    public static function range(?string $preset): array
    {
        return match ($preset) {
            'mes_pasado' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            '30_dias'    => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'anio'       => [now()->startOfYear(), now()->endOfDay()],
            default      => [now()->startOfMonth(), now()->endOfDay()],
        };
    }

    /**
     * @return array{rows: list<array>, totals: array{units: int, revenue: float, cost: float, profit: float, margin: ?float}, estimated: bool, missing: bool}
     */
    public function build(Store $store, CarbonInterface $from, CarbonInterface $to): array
    {
        $orders = Order::query()
            ->where('store_id', $store->id)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['paid', 'partial'])
            ->where('currency', 'PEN');

        $sales = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.order_id', (clone $orders)->select('id'))
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->get([
                'order_items.product_id',
                DB::raw('MAX(order_items.product_name) as product_name'),
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(order_items.subtotal * CASE WHEN orders.subtotal > 0 THEN (orders.subtotal - orders.discount) * 1.0 / orders.subtotal ELSE 1 END) as revenue'),
            ])
            ->keyBy('product_id');

        // Before the lot migration there are no sale costs yet: everything is estimated.
        $costs = !$this->schema->lotsReady() ? collect() : InventoryMovement::query()
            ->where('store_id', $store->id)
            ->where('type', 'sale')
            ->whereIn('reference', (clone $orders)->select('order_number'))
            ->whereNotNull('total_cost')
            ->groupBy('product_id')
            ->get(['product_id', DB::raw('SUM(total_cost) as cost'), DB::raw('SUM(-quantity) as units')])
            ->keyBy('product_id');

        $products = Product::withTrashed()->whereIn('id', $sales->keys())->get(['id', 'name', 'cost_price', 'image_path'])->keyBy('id');

        $rows = [];
        foreach ($sales as $productId => $sale) {
            $units = (int) $sale->units;
            $revenue = round((float) $sale->revenue, 2);
            $cost = round((float) ($costs[$productId]->cost ?? 0), 2);
            $missingUnits = max(0, $units - (int) ($costs[$productId]->units ?? 0));
            $product = $products[$productId] ?? null;

            $status = 'exact';
            if ($missingUnits > 0) {
                if ($product?->cost_price !== null) {
                    $cost = round($cost + $missingUnits * (float) $product->cost_price, 2);
                    $status = 'estimated';
                } else {
                    $status = 'missing';
                }
            }

            $profit = round($revenue - $cost, 2);
            $rows[] = [
                'product_id' => (int) $productId,
                'name'       => $product?->name ?? $sale->product_name,
                'image'      => $product?->image_url,
                'units'      => $units,
                'revenue'    => $revenue,
                'cost'       => $cost,
                'profit'     => $profit,
                'margin'     => $revenue > 0 ? round($profit * 100 / $revenue, 1) : null,
                'cost_status'=> $status,
            ];
        }

        usort($rows, fn ($a, $b) => $b['profit'] <=> $a['profit']);

        $revenue = round(array_sum(array_column($rows, 'revenue')), 2);
        $cost = round(array_sum(array_column($rows, 'cost')), 2);

        return [
            'rows'      => $rows,
            'totals'    => [
                'units'   => (int) array_sum(array_column($rows, 'units')),
                'revenue' => $revenue,
                'cost'    => $cost,
                'profit'  => round($revenue - $cost, 2),
                'margin'  => $revenue > 0 ? round(($revenue - $cost) * 100 / $revenue, 1) : null,
            ],
            'estimated' => in_array('estimated', array_column($rows, 'cost_status'), true),
            'missing'   => in_array('missing', array_column($rows, 'cost_status'), true),
        ];
    }
}
