<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PendingCheckout extends Model
{
    protected $fillable = ['store_id', 'reference', 'gateway', 'payload', 'gateway_ref', 'gateway_meta', 'order_id'];

    protected $casts = [
        'payload' => 'array',
        'gateway_meta' => 'array',
    ];

    /**
     * Opens a checkout draft under a fresh order number. Two buyers of the same store
     * checking out at the same instant can still compute the same number, so a unique
     * violation is retried with the next free one instead of surfacing as a 500.
     */
    public static function openWithFreshReference(int $storeId, array $attributes): self
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return self::create(['store_id' => $storeId, 'reference' => Order::generateOrderNumber($storeId)] + $attributes);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /** True once the resulting order is paid or refunded — later callbacks must not touch it. */
    public function isSettled(): bool
    {
        return $this->order_id !== null
            && in_array($this->order?->payment_status, ['paid', 'refunded'], true);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Turns this pending checkout into a real Order — the only place stock is ever
     * decremented. $decrementStock is explicit rather than inferred from $paymentStatus:
     * a WhatsApp order decrements on arrival (there is no online confirmation to wait
     * for), while a gateway order should only decrement once actually paid — a failed
     * or still-pending gateway result still materializes (the attempt is real and worth
     * the store owner seeing), just without touching inventory.
     *
     * Idempotent, and re-callable as a result improves (pending -> paid, e.g. a cash
     * voucher that later clears): a second call updates the existing order instead of
     * creating another one, and only ever decrements stock the first time it happens.
     * The one thing it never does is downgrade a payment already settled as paid/
     * refunded — a stale or replayed callback can't undo a real sale.
     */
    public function materialize(string $paymentStatus, string $status, bool $decrementStock): Order
    {
        $settlement = null;

        $order = DB::transaction(function () use ($paymentStatus, $status, $decrementStock, &$settlement) {
            $locked = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $data = $locked->payload;
            // Set only by the call that actually turns the order paid — replays never re-credit.
            $settles = $paymentStatus === 'paid' ? ['gateway' => $locked->gateway, 'ref' => $locked->gateway_ref] : null;

            if ($locked->order_id) {
                $order = Order::whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
                if (in_array($order->payment_status, ['paid', 'refunded'], true)) {
                    return $order;
                }
                $settlement = $settles;
                // Reaching here means the existing order is 'pending' or 'failed', so
                // stock was never decremented for it before — safe to do now if this
                // call is the one that finally confirms payment.
                $order->update(['payment_status' => $paymentStatus, 'status' => $status]);
                if ($decrementStock) {
                    $this->decrementStockFor($data['items']);
                }
                return $order;
            }

            $store = $locked->store;
            $order = Order::create([
                'store_id'            => $store->id,
                'user_id'             => $data['user_id'] ?? null,
                'order_number'        => $locked->reference,
                'customer_name'       => $data['customer_name'],
                'customer_phone'      => $data['customer_phone'],
                'customer_email'      => $data['customer_email'],
                'customer_document_type'   => $data['customer_document_type'] ?? null,
                'customer_document_number' => $data['customer_document_number'] ?? null,
                'customer_address'    => $data['customer_address'] ?? null,
                'customer_country'    => $data['customer_country'] ?? 'PE',
                'customer_state'      => $data['customer_state'] ?? null,
                'customer_city'       => $data['customer_city'] ?? null,
                'customer_zipcode'    => $data['customer_zipcode'] ?? null,
                'customer_notes'      => $data['customer_notes'] ?? null,
                'subtotal'            => $data['subtotal'],
                'discount'            => $data['discount'],
                'shipping_cost'       => $data['shipping_cost'],
                'is_express_shipping' => $data['is_express_shipping'] ?? false,
                'total'               => $data['total'],
                'currency'            => $data['currency'],
                'status'              => $status,
                'payment_status'      => $paymentStatus,
                'payment_method'      => $data['payment_method'],
                'source'              => $data['source'] ?? 'store',
                'paypal_order_id'     => $data['paypal_order_id'] ?? null,
            ]);
            $order->items()->createMany($data['items']);
            $store->increment('total_orders');

            if ($decrementStock) {
                $this->decrementStockFor($data['items']);
            }

            $locked->update(['order_id' => $order->id]);
            $settlement = $settles;
            return $order;
        });

        if ($settlement) {
            $this->recordSettlement($order, $settlement['gateway'], $settlement['ref']);
        }

        return $order;
    }

    /**
     * Credits the confirmed charge to the payments ledger — deliberately outside the
     * transaction above: the gateway has already moved real money, so a ledger problem
     * must never roll back the order the buyer just paid for. It is logged for manual
     * reconciliation instead (see the Mercado Pago charge-loss incident in the vault).
     */
    private function recordSettlement(Order $order, string $gateway, ?string $gatewayRef): void
    {
        try {
            $order->recordPayment((float) $order->total, OrderPayment::KIND_FULL, $gateway, $gatewayRef);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::critical('Pago confirmado sin registrar en el libro de pagos.', [
                'order_id' => $order->id, 'order_number' => $order->order_number,
                'gateway' => $gateway, 'gateway_ref' => $gatewayRef, 'exception' => $e->getMessage(),
            ]);
        }
    }

    private function decrementStockFor(array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product || !$product->track_stock) {
                continue;
            }
            // Clamped to available stock: a backorder sale (out_of_stock_message set,
            // so checkout allows buying at 0 stock — see StoreController::checkout())
            // must never push products.stock (unsigned) negative. That throws a
            // QueryException that rolls back this whole transaction — including the
            // Order just created — after a gateway may have already charged the
            // customer, which is far worse than just leaving stock floored at 0.
            if (!empty($item['variant_id'])) {
                $variant = ProductVariant::find($item['variant_id']);
                $variant?->decrement('stock', min($item['quantity'], $variant->stock));
            }
            $product->decrement('stock', min($item['quantity'], $product->stock));
        }
    }
}
