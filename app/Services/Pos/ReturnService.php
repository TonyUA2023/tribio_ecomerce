<?php

namespace App\Services\Pos;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderReturn;
use App\Models\RegisterSession;
use App\Services\Crm\ContactResolver;
use App\Services\Crm\ContactService;
use App\Services\Inventory\StockService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Returns of counter sales (ERP Fase 4c) with an internal credit note (DEV-2026-000001).
 * The sale is never edited: orders.returned_total grows, so what the customer owes goes
 * down first; whatever is left is money given back, a negative ledger row (cash from an
 * open register lowers its expected cash). Returned units go back to stock unless marked
 * damaged. A full return marks the order refunded.
 */
class ReturnService
{
    public function __construct(
        private StockService $stock,
        private ContactService $timeline,
        private ContactResolver $contacts,
    ) {
    }

    /**
     * Units that can still come back, per order item.
     *
     * @return Collection<int, int> order_item_id → units
     */
    public function returnable(Order $order): Collection
    {
        $returned = $order->returns()->get()->flatMap(fn (OrderReturn $return) => $return->items)
            ->groupBy('order_item_id')->map(fn ($rows) => (int) collect($rows)->sum('quantity'));

        return $order->items->mapWithKeys(fn ($item) => [$item->id => max(0, (int) $item->quantity - (int) ($returned[$item->id] ?? 0))]);
    }

    /**
     * @param  array{items: list<array{order_item_id: int, quantity: int, restock?: bool}>, reason: string, method?: ?string, register_session_id?: ?int}  $data
     */
    public function create(Order $order, array $data, ?int $userId, ?Actor $actor = null): OrderReturn
    {
        if ($order->source !== 'pos' || in_array($order->status, ['cancelled', 'refunded'], true)) {
            throw ValidationException::withMessages(['items' => 'Esta venta no admite devoluciones.']);
        }
        $session = !empty($data['register_session_id'])
            ? RegisterSession::where('store_id', $order->store_id)->where('status', 'open')->find($data['register_session_id'])
            : null;
        if (!empty($data['register_session_id']) && !$session) {
            throw ValidationException::withMessages(['register_session_id' => 'Esa caja ya se cerró.']);
        }

        $return = DB::transaction(function () use ($order, $data, $session, $userId, $actor) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load('items');
            $left = $this->returnable($order);
            // The sale's discount is shared by every unit it covered.
            $share = (float) $order->subtotal > 0 ? 1 - (float) $order->discount / (float) $order->subtotal : 1.0;

            $lines = [];
            foreach ($data['items'] as $row) {
                $quantity = (int) ($row['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }
                $item = $order->items->firstWhere('id', (int) $row['order_item_id']);
                if (!$item || $quantity > ($left[$item->id] ?? 0)) {
                    throw ValidationException::withMessages(['items' => $item
                        ? "De «{$item->product_name}» se pueden devolver {$left[$item->id]} und. como máximo."
                        : 'Ese producto no es de esta venta.']);
                }
                $lines[] = [
                    'order_item_id' => $item->id,
                    'product_name'  => $item->product_name . ($item->variant_title ? ' · ' . $item->variant_title : ''),
                    'quantity'      => $quantity,
                    'amount'        => round((float) $item->subtotal / max(1, (int) $item->quantity) * $quantity * $share, 2),
                    'restock'       => (bool) ($row['restock'] ?? true),
                    'item'          => $item,
                ];
            }
            if ($lines === []) {
                throw ValidationException::withMessages(['items' => 'Indica qué productos devuelve y cuántos.']);
            }

            $total = round(array_sum(array_column($lines, 'amount')), 2);
            $debt = round(min($total, (float) $order->balance_due), 2);
            $refund = round($total - $debt, 2);
            $method = $data['method'] ?? null;
            if ($refund > 0 && !isset(PosSaleService::METHODS[$method])) {
                throw ValidationException::withMessages(['method' => 'Elige cómo le devuelves S/ ' . number_format($refund, 2) . '.']);
            }

            $return = $this->withNumber($order, fn (string $number) => OrderReturn::create([
                'store_id'            => $order->store_id,
                'order_id'            => $order->id,
                'number'              => $number,
                'user_id'             => $userId,
                'register_session_id' => $refund > 0 ? $session?->id : null,
                'reason'              => mb_substr(trim($data['reason']), 0, 255),
                'items'               => array_map(fn ($line) => array_diff_key($line, ['item' => true]), $lines),
                'total'               => $total,
                'debt_reduced'        => $debt,
                'refunded'            => $refund,
                'method'              => $refund > 0 ? $method : null,
            ] + ($actor ? $actor->columns() : [])));

            $returned = round((float) $order->returned_total + $total, 2);
            $order->forceFill(['returned_total' => $returned] + ($returned + 0.005 >= (float) $order->total
                ? ['status' => 'refunded', 'payment_status' => 'refunded'] : []))->save();

            if ($refund > 0) {
                $order->payments()->create([
                    'kind' => 'refund', 'amount' => -$refund, 'currency' => $order->currency ?: 'PEN', 'gateway' => 'pos',
                    'gateway_ref' => "devolucion:{$return->id}", 'method' => $method, 'register_session_id' => $session?->id,
                    'status' => OrderPayment::STATUS_PAID, 'paid_at' => now(), 'recorded_by' => $userId, 'notes' => "Devolución {$return->number}",
                ] + ($actor ? $actor->columns('staff_id', null) : []));
                $order->refreshPaymentTotals();
            }

            foreach ($lines as $line) {
                if ($line['restock'] && $line['item']->product) {
                    $this->stock->restock($line['item']->product, $line['item']->variant_id ? \App\Models\ProductVariant::find($line['item']->variant_id) : null,
                        $line['quantity'], $return->number, $userId);
                }
            }

            return $return;
        });

        if ($contact = $order->fresh()->contact) {
            $this->contacts->refreshTotals($contact);
            $this->timeline->log($contact, 'credit', sprintf('Devolvió productos de %s por S/ %s (%s)%s.', $order->order_number, number_format((float) $return->total, 2), $return->number,
                (float) $return->debt_reduced > 0 ? ', se descontó de su deuda' : ''), $userId);
        }

        return $return;
    }

    /** DEV-2026-000001 per store. */
    private function withNumber(Order $order, \Closure $create): OrderReturn
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $count = OrderReturn::where('store_id', $order->store_id)->where('number', 'like', 'DEV-' . now()->year . '-%')->count() + 1 + $attempt;
            try {
                return DB::transaction(fn () => $create(sprintf('DEV-%d-%06d', now()->year, $count)));
            } catch (UniqueConstraintViolationException $e) {
                continue;
            }
        }

        throw new \RuntimeException('No se pudo numerar la devolución.');
    }
}
