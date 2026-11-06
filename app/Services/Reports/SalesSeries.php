<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\Store;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Sales per day for the dashboard charts, by origin: `pos` (counter sales) or `online` (the
 * online store's orders). Days are Peru days. Cancelled orders do not count.
 */
class SalesSeries
{
    public const SOURCES = ['pos', 'online'];

    public const PERIODS = ['7' => 'Últimos 7 días', '30' => 'Últimos 30 días', 'mes' => 'Este mes'];

    /** @return array{0: Carbon, 1: Carbon} in Peru time */
    public static function range(?string $period): array
    {
        $now = Carbon::now(ProfitAndLoss::TZ);

        return match ($period) {
            '30'    => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'mes'   => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            default => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    /**
     * @return array{days: array<string, array{count: int, total: float}>, count: int, total: float, average: float}
     */
    public function daily(Store $store, string $source, CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];
        for ($d = Carbon::instance($from)->setTimezone(ProfitAndLoss::TZ)->startOfDay(); $d->lte($to); $d->addDay()) {
            $days[$d->toDateString()] = ['count' => 0, 'total' => 0.0];
        }

        $orders = Order::query()->where('store_id', $store->id)->where('status', '!=', 'cancelled')
            ->when($source === 'pos', fn ($q) => $q->where('source', 'pos'), fn ($q) => $q->where('source', '!=', 'pos'))
            ->whereBetween('created_at', [
                Carbon::instance($from)->setTimezone(config('app.timezone')), Carbon::instance($to)->setTimezone(config('app.timezone')),
            ])->get(['total', 'created_at']);

        foreach ($orders as $order) {
            $key = $order->created_at->copy()->setTimezone(ProfitAndLoss::TZ)->toDateString();
            if (isset($days[$key])) {
                $days[$key]['count']++;
                $days[$key]['total'] += (float) $order->total;
            }
        }
        foreach ($days as &$day) {
            $day['total'] = round($day['total'], 2);
        }
        unset($day);

        $count = array_sum(array_column($days, 'count'));
        $total = round(array_sum(array_column($days, 'total')), 2);

        return ['days' => $days, 'count' => $count, 'total' => $total, 'average' => $count > 0 ? round($total / $count, 2) : 0.0];
    }
}
