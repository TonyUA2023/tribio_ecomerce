<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\RegisterSession;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * "Modo simple": selling from the phone in two taps (bodega, puesto, ambulante). It is the POS
 * underneath — same orders, stock, payments and reports — minus what a tiny business never uses:
 * shifts are opened and closed by themselves (one per Lima day), the customer is "Cliente varios",
 * the comprobante is a nota de venta, and a sale can be just an amount.
 */
class SimpleSales
{
    public const TZ = 'America/Lima';

    /** What can be tapped to pay; the rest of the POS methods stay in the full register. */
    public const METHODS = ['cash' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin'];

    /** Tiles on the selling screen (the search box narrows them). */
    public const CATALOG_LIMIT = 400;

    public function __construct(private RegisterService $registers, private PosSaleService $sales)
    {
    }

    /**
     * The shift the sale goes into. Nobody opens or closes a register here: yesterday's shift is
     * closed on its own (cash count = what the system expected) the first time today's sale arrives.
     */
    public function session(Store $store, ?int $userId): RegisterSession
    {
        $register = $this->registers->registers($store)->first();
        $open = fn () => RegisterSession::where('register_id', $register->id)->where('status', 'open')->latest('id')->first();

        $session = $open();
        if ($session && $session->opened_at->copy()->setTimezone(self::TZ)->toDateString() !== Carbon::now(self::TZ)->toDateString()) {
            $this->registers->close($session, $this->registers->summary($session)['expected_cash'], 'Cierre automático (modo simple)', $userId);
            $session = null;
        }
        if (!$session) {
            try {
                $session = $this->registers->open($register, 0, $userId);
            } catch (ValidationException $e) {
                // Two phones opened the day at once: use the shift that won.
                $session = $open() ?? throw $e;
            }
        }

        return $session;
    }

    /**
     * @param  array{uuid: string, items: list<array>, method: string, tendered?: ?float, offline_at?: ?string}  $data
     * @return array{order: Order, repeated: bool, change: float}
     */
    public function sell(Store $store, ?int $userId, array $data): array
    {
        if (!isset(self::METHODS[$data['method'] ?? ''])) {
            throw ValidationException::withMessages(['method' => 'Elige cómo te pagan.']);
        }

        $items = array_map(fn (array $item) => !empty($item['free'])
            ? ['free' => true, 'name' => $item['name'] ?? null, 'price' => $item['price'] ?? 0, 'quantity' => $item['quantity'] ?? 1]
            : ['product_id' => (int) $item['product_id'], 'quantity' => max(1, (int) ($item['quantity'] ?? 1))], $data['items']);

        $sale = ['uuid' => $data['uuid'], 'items' => $items, 'price_list' => null, 'offline_at' => $data['offline_at'] ?? null];
        // The server prices the cart (tiers and price lists included): the phone only says how it was paid.
        $total = round(array_sum(array_column($this->sales->lines($store->id, $items, null), 'subtotal')), 2);
        $paid = $data['method'] === 'cash' ? max($total, round((float) ($data['tendered'] ?? 0), 2)) : $total;
        $sale['payments'] = [['method' => $data['method'], 'amount' => $paid]];

        [$order, $repeated] = $this->sales->sell($this->session($store, $userId), $sale, $userId);

        return ['order' => $order, 'repeated' => $repeated, 'change' => round((float) $order->change_given, 2)];
    }

    /** Sales of today (Lima): the number shown on top of the selling screen. @return array{count: int, total: float} */
    public function today(Store $store): array
    {
        $from = Carbon::now(self::TZ)->startOfDay()->setTimezone(config('app.timezone'));
        $orders = Order::where('store_id', $store->id)->where('source', 'pos')->where('status', '!=', 'cancelled')->where('created_at', '>=', $from);

        return ['count' => (clone $orders)->count(), 'total' => round((float) (clone $orders)->sum('total'), 2)];
    }

    /**
     * What is on sale, best sellers first, as plain data for the phone. Products that need a choice
     * (variants) or are made to order are left out: they have their place in the full register.
     *
     * @return list<array{id: int, name: string, price: float, stock: ?int}>
     */
    public function catalog(Store $store): array
    {
        return Product::where('store_id', $store->id)->where('is_active', true)->where('has_variants', false)
            ->orderByDesc('sold_count')->orderBy('name')->limit(self::CATALOG_LIMIT)
            ->get(['id', 'name', 'price', 'stock', 'track_stock', 'sale_mode'])
            ->reject(fn (Product $p) => $p->isMadeToOrder($store))
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price, 'stock' => $p->track_stock ? (int) $p->stock : null])
            ->values()->all();
    }

    /** The day in numbers for the "Hoy" screen. */
    public function summary(Store $store, array $report): array
    {
        $totals = $report['totals'];
        $methods = [];
        foreach (self::METHODS as $key => $label) {
            $methods[$key] = ['label' => $label, 'amount' => (float) ($report['methods'][$key] ?? 0)];
        }

        return [
            'sales'   => $totals['sales'],
            'sold'    => $totals['income'],
            'cost'    => $totals['cost'],
            'profit'  => $totals['gross'],
            'average' => $totals['average_ticket'],
            'methods' => $methods,
            'other'   => round(max(0, array_sum($report['methods']) - array_sum(array_column($methods, 'amount'))), 2),
            'partial' => $report['estimated'] || $report['missing_cost'] || $report['untracked_items'] > 0,
        ];
    }
}
