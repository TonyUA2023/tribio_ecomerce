<?php

namespace App\Services\Reports;

use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * What a set of sales earned: what was sold (discounts taken off) minus the cost of the lots the
 * sold units came from. Works on any order query — a day, a month, a search, a payment method —
 * so a screen can show "ganancia" for exactly what its filters show.
 */
class SalesProfit
{
    /**
     * @param  Builder  $orders  orders already scoped to the store and its filters (and without cancelled ones)
     * @return array{count: int, income: float, cost: float, gross: float, margin: ?float, average: float, partial: bool, returned: float}
     */
    public function forOrders(Builder $orders, int $storeId): array
    {
        $count = (clone $orders)->count();
        $income = round((float) ((clone $orders)->reorder()->selectRaw('COALESCE(SUM(orders.subtotal - orders.discount), 0) as income')->value('income')), 2);

        $movements = InventoryMovement::where('store_id', $storeId)->where('type', 'sale')
            ->whereIn('reference', (clone $orders)->reorder()->select('orders.order_number'));
        $cost = round((float) (clone $movements)->sum('total_cost'), 2);
        $partial = (clone $movements)->whereNull('total_cost')->exists() || ($count > 0 && !(clone $movements)->exists());

        // A return lowers what was really earned; the exact figure (by return date) is in Ganancias y pérdidas.
        $returned = Schema::hasColumn('orders', 'returned_total')
            ? round((float) ((clone $orders)->reorder()->selectRaw('COALESCE(SUM(orders.returned_total), 0) as returned')->value('returned')), 2)
            : 0.0;

        return [
            'count'    => $count,
            'income'   => $income,
            'cost'     => $cost,
            'gross'    => round($income - $cost, 2),
            'margin'   => $income > 0 ? round(($income - $cost) * 100 / $income, 1) : null,
            'average'  => $count > 0 ? round($income / $count, 2) : 0.0,
            'partial'  => $partial,
            'returned' => $returned,
        ];
    }
}
