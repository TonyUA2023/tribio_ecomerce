<?php

namespace App\Services\Reports;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RegisterMovement;
use App\Models\RegisterSession;
use App\Models\Store;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Ganancias y pérdidas of a store, per day and for the whole period (Peru time).
 *
 *   ventas            what was sold (paid or with a deposit, not cancelled, PEN), discounts already taken off
 *   − devoluciones    what customers got back
 *   = ingresos
 *   − costo de ventas the cost of the lots the sold units came from (a return puts its cost back)
 *   = ganancia bruta
 *   − gastos          cash taken out of the registers (lunch, freight, utilities, day labour…)
 *   − pérdidas        stock written off: losses and count shortfalls, at their lot cost
 *   = ganancia neta   (a negative number is a pérdida)
 *
 * Stock purchases are not an expense of the period — they turn into cost when the goods are
 * sold — so they are reported apart as `purchases`, and `outflow` (egresos de caja del negocio)
 * is purchases + gastos. Same sales and cost rules as Inventario → Rentabilidad (ProfitReport),
 * which this report matches to the cent when nothing was returned.
 */
class ProfitAndLoss
{
    public const TZ = 'America/Lima';

    public const PRESETS = [
        'hoy'        => 'Hoy',
        'ayer'       => 'Ayer',
        '7_dias'     => 'Últimos 7 días',
        'mes'        => 'Este mes',
        'mes_pasado' => 'Mes pasado',
        '30_dias'    => 'Últimos 30 días',
        'anio'       => 'Este año',
    ];

    private const MAX_DAYS = 400;

    /** @return array{0: Carbon, 1: Carbon} in Peru time */
    public static function range(?string $preset): array
    {
        $now = Carbon::now(self::TZ);

        return match ($preset) {
            'hoy'        => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'ayer'       => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'mes'        => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'mes_pasado' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            '30_dias'    => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'anio'       => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default      => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    /** A custom range from two Y-m-d dates; null when they are not a valid range (or longer than ~13 months). */
    public static function customRange(?string $from, ?string $to): ?array
    {
        try {
            $start = Carbon::createFromFormat('Y-m-d', (string) $from, self::TZ)->startOfDay();
            $end = Carbon::createFromFormat('Y-m-d', (string) $to, self::TZ)->endOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        return $end->gte($start) && $start->diffInDays($end) < self::MAX_DAYS ? [$start, $end] : null;
    }

    /**
     * @return array{
     *   days: array<string, array>, totals: array, sources: array<string, array{count: int, income: float}>, methods: array<string, float>, documents: array<string, array{count: int, total: float}>,
     *   expenses: list<array{reason: string, amount: float}>, losses: list<array>, estimated: bool, missing_cost: bool, untracked_items: int
     * }
     */
    public function build(Store $store, CarbonInterface $from, CarbonInterface $to): array
    {
        $from = Carbon::instance($from)->setTimezone(self::TZ);
        $to = Carbon::instance($to)->setTimezone(self::TZ);
        // Instants go to the database in the app's timezone.
        $dbFrom = $from->copy()->setTimezone(config('app.timezone'));
        $dbTo = $to->copy()->setTimezone(config('app.timezone'));
        $dayOf = fn ($at) => Carbon::instance($at)->setTimezone(self::TZ)->toDateString();

        $days = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to) && count($days) < self::MAX_DAYS; $d->addDay()) {
            $days[$d->toDateString()] = ['sales' => 0, 'sold' => 0.0, 'returns' => 0.0, 'income' => 0.0, 'cost' => 0.0, 'gross' => 0.0, 'margin' => null,
                'expenses' => 0.0, 'losses' => 0.0, 'net' => 0.0, 'purchases' => 0.0, 'outflow' => 0.0];
        }

        // Sales
        $counted = Order::query()->where('store_id', $store->id)->whereBetween('created_at', [$dbFrom, $dbTo])
            ->where('status', '!=', 'cancelled')->whereIn('payment_status', ['paid', 'partial'])->where('currency', 'PEN');
        $documents = [];
        $sources = ['pos' => ['count' => 0, 'income' => 0.0], 'online' => ['count' => 0, 'income' => 0.0]];
        $orderColumns = ['id', 'subtotal', 'discount', 'total', 'source', 'created_at'];
        if (app(\App\Services\Pos\SalesDocuments::class)->ready()) {
            $orderColumns[] = 'document_type'; // the column exists once the comprobantes migration has run
        }
        foreach ((clone $counted)->get($orderColumns) as $order) {
            $key = $dayOf($order->created_at);
            if (!isset($days[$key])) {
                continue;
            }
            $days[$key]['sales']++;
            $days[$key]['sold'] += (float) $order->subtotal - (float) $order->discount;
            $origin = $order->source === 'pos' ? 'pos' : 'online';
            $sources[$origin]['count']++;
            $sources[$origin]['income'] += (float) $order->subtotal - (float) $order->discount;

            $type = $order->document_type ?: ($order->source === 'pos' ? 'nota_venta' : 'tienda');
            $documents[$type] ??= ['count' => 0, 'total' => 0.0];
            $documents[$type]['count']++;
            $documents[$type]['total'] += (float) $order->total;
        }

        // Returns (ERP Fase 4c)
        if (Schema::hasTable('order_returns')) {
            foreach (OrderReturn::where('store_id', $store->id)->whereBetween('created_at', [$dbFrom, $dbTo])->get(['total', 'created_at']) as $return) {
                if (isset($days[$key = $dayOf($return->created_at)])) {
                    $days[$key]['returns'] += (float) $return->total;
                }
            }
        }

        // Cost of what was sold (and put back by returns), at lot cost; unknown costs use the product's cost price.
        $estimated = $missing = false;
        $movements = InventoryMovement::where('store_id', $store->id)->whereBetween('created_at', [$dbFrom, $dbTo])
            ->where(function ($q) use ($counted) {
                $q->where(fn ($sale) => $sale->where('type', 'sale')->whereIn('reference', (clone $counted)->select('order_number')))
                    ->orWhere('type', 'return');
            })->get(['product_id', 'type', 'quantity', 'total_cost', 'created_at']);
        $costPrices = Product::withTrashed()->whereIn('id', $movements->pluck('product_id')->unique())->pluck('cost_price', 'id');
        foreach ($movements as $movement) {
            if (!isset($days[$key = $dayOf($movement->created_at)])) {
                continue;
            }
            [$cost, $guess, $unknown] = $this->costOf($movement, $costPrices);
            $estimated = $estimated || $guess;
            $missing = $missing || $unknown;
            $days[$key]['cost'] += $movement->type === 'return' ? -$cost : $cost;
        }

        // Stock written off
        $lossRows = InventoryMovement::where('store_id', $store->id)->whereBetween('created_at', [$dbFrom, $dbTo])
            ->where(fn ($q) => $q->where('type', 'loss')->orWhere(fn ($count) => $count->where('type', 'adjustment')->where('quantity', '<', 0)))
            ->with('product:id,name,cost_price')->orderByDesc('created_at')->get(['id', 'product_id', 'type', 'quantity', 'total_cost', 'reason', 'created_at']);
        $losses = [];
        foreach ($lossRows as $movement) {
            if (!isset($days[$key = $dayOf($movement->created_at)])) {
                continue;
            }
            [$cost, $guess, $unknown] = $this->costOf($movement, [$movement->product_id => $movement->product?->cost_price]);
            $estimated = $estimated || $guess;
            $missing = $missing || $unknown;
            $days[$key]['losses'] += $cost;
            $losses[] = ['date' => $key, 'product' => $movement->product?->name ?? 'Producto eliminado', 'quantity' => abs((int) $movement->quantity),
                'cost' => round($cost, 2), 'reason' => $movement->reason, 'type' => $movement->type];
        }

        // Expenses: cash taken out of the registers
        $expenseTotals = [];
        $sessions = RegisterSession::where('store_id', $store->id)->select('id');
        if (Schema::hasTable('register_movements')) {
            foreach (RegisterMovement::where('type', 'out')->whereIn('register_session_id', $sessions)->whereBetween('created_at', [$dbFrom, $dbTo])->get(['amount', 'reason', 'created_at']) as $out) {
                if (isset($days[$key = $dayOf($out->created_at)])) {
                    $days[$key]['expenses'] += (float) $out->amount;
                    $label = trim((string) $out->reason) ?: 'Sin motivo';
                    $expenseTotals[$label] = ($expenseTotals[$label] ?? 0.0) + (float) $out->amount;
                }
            }
        }
        arsort($expenseTotals);

        // Stock purchases received
        foreach (PurchaseOrder::where('store_id', $store->id)->where('status', 'received')->whereBetween('received_at', [$dbFrom, $dbTo])->get(['total', 'received_at']) as $purchase) {
            if (isset($days[$key = $dayOf($purchase->received_at)])) {
                $days[$key]['purchases'] += (float) $purchase->total;
            }
        }

        // Money taken per payment method
        $methods = [];
        foreach (OrderPayment::whereIn('order_id', Order::where('store_id', $store->id)->select('id'))->where('status', OrderPayment::STATUS_PAID)
            ->whereBetween('paid_at', [$dbFrom, $dbTo])->get(['method', 'gateway', 'amount']) as $payment) {
            $label = $payment->method ?: $payment->gateway;
            $methods[$label] = ($methods[$label] ?? 0.0) + (float) $payment->amount;
        }
        arsort($methods);

        $untracked = OrderItem::query()->whereIn('order_items.order_id', (clone $counted)->select('id'))
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where(fn ($q) => $q->whereNull('products.id')->orWhere('products.track_stock', false))->count();

        $totals = array_fill_keys(['sales', 'sold', 'returns', 'income', 'cost', 'gross', 'expenses', 'losses', 'net', 'purchases', 'outflow'], 0);
        foreach ($days as &$day) {
            foreach (['sold', 'returns', 'cost', 'expenses', 'losses', 'purchases'] as $field) {
                $day[$field] = round($day[$field], 2);
            }
            $day['income'] = round($day['sold'] - $day['returns'], 2);
            $day['gross'] = round($day['income'] - $day['cost'], 2);
            $day['margin'] = $day['income'] > 0 ? round($day['gross'] * 100 / $day['income'], 1) : null;
            $day['net'] = round($day['gross'] - $day['expenses'] - $day['losses'], 2);
            $day['outflow'] = round($day['purchases'] + $day['expenses'], 2);
            foreach ($totals as $field => $_) {
                $totals[$field] += $day[$field];
            }
        }
        unset($day);
        $totals = array_map(fn ($value) => is_float($value) ? round($value, 2) : $value, $totals);
        $totals['margin'] = $totals['income'] > 0 ? round($totals['gross'] * 100 / $totals['income'], 1) : null;
        $totals['average_ticket'] = $totals['sales'] > 0 ? round($totals['sold'] / $totals['sales'], 2) : 0.0;

        return [
            'days'      => $days,
            'totals'    => $totals,
            'methods'   => array_map(fn ($v) => round($v, 2), $methods),
            'documents' => array_map(fn ($d) => ['count' => $d['count'], 'total' => round($d['total'], 2)], $documents),
            'sources'   => array_map(fn ($s) => ['count' => $s['count'], 'income' => round($s['income'], 2)], $sources),
            'expenses'  => array_map(fn ($reason, $amount) => ['reason' => $reason, 'amount' => round($amount, 2)], array_keys($expenseTotals), $expenseTotals),
            'losses'    => array_slice($losses, 0, 15),
            'estimated' => $estimated,
            'missing_cost' => $missing,
            'untracked_items' => $untracked,
        ];
    }

    /**
     * Cost of a stock movement: its lot cost, else the product's cost price × units (an estimate), else 0 (unknown).
     *
     * @param  iterable<int|string, mixed>  $costPrices  product id => cost price
     * @return array{0: float, 1: bool, 2: bool} cost, estimated, unknown
     */
    private function costOf(InventoryMovement $movement, $costPrices): array
    {
        if ($movement->total_cost !== null) {
            return [(float) $movement->total_cost, false, false];
        }
        $price = $costPrices[$movement->product_id] ?? null;
        if ($price === null) {
            return [0.0, false, true];
        }

        return [abs((int) $movement->quantity) * (float) $price, true, false];
    }
}
