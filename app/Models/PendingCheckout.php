<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    /**
     * True once the gateway charge for this checkout has been credited (paid in full, or
     * the deposit of a made-to-order cart) or refunded — later callbacks must not touch it.
     */
    public function isSettled(): bool
    {
        if ($this->isBalancePayment()) {
            return !empty($this->gateway_meta['settled']);
        }

        return $this->order_id !== null
            && in_array($this->order?->payment_status, ['paid', 'partial', 'refunded'], true);
    }

    /**
     * What the gateway is asked to charge for this checkout: the order total, or only the
     * deposit share when a made-to-order cart asks for one (payload amount_due_now).
     */
    public function chargeAmount(): float
    {
        $payload = $this->payload;

        return round((float) ($payload['amount_due_now'] ?? $payload['total'] ?? 0), 2);
    }

    /**
     * A draft that pays the balance of an existing made-to-order order (see
     * MercadoPagoBalanceCheckout): it never becomes an order of its own — settleBalance()
     * credits the existing one instead of materialize().
     */
    public function isBalancePayment(): bool
    {
        return !empty($this->payload['balance_for_order_id']);
    }

    /**
     * Credits a Mercado Pago payment of an order's balance to that order's ledger.
     * $payment must be what Mercado Pago's API reported (webhook, or a return verified
     * through MercadoPagoPayments) — never query parameters. Idempotent: the first
     * approved call marks the draft settled; replays and the webhook/return race find it
     * settled and credit nothing. Returns the order when this call credited it.
     */
    public function settleBalance(array $payment): ?Order
    {
        if (!$this->isBalancePayment()) {
            throw new \LogicException('settleBalance() solo aplica a pagos de saldo.');
        }
        $status = (string) ($payment['status'] ?? '');
        if ($status !== 'approved') {
            $this->update(['gateway_meta' => array_merge($this->gateway_meta ?? [], ['last_status' => $status])]);
            return null;
        }

        $order = Order::where('store_id', $this->store_id)->find($this->payload['balance_for_order_id']);
        if (!$order) {
            Log::critical('Pago de saldo aprobado para un pedido que ya no existe.', [
                'reference' => $this->reference, 'payment_id' => $payment['id'] ?? null,
            ]);
            return null;
        }

        $paymentId = (string) ($payment['id'] ?? '');
        $amount = DB::transaction(function () use ($paymentId, $payment) {
            $locked = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if (!empty($locked->gateway_meta['settled'])) {
                return null;
            }
            $locked->update(['gateway_meta' => array_merge($locked->gateway_meta ?? [], [
                'settled' => true, 'payment_id' => $paymentId, 'last_status' => 'approved',
            ])]);

            // What Mercado Pago actually charged is what the store received.
            return round((float) ($payment['transaction_amount'] ?? $locked->chargeAmount()), 2);
        });
        $this->refresh();
        if ($amount === null) {
            return null;
        }

        // Outside the transaction, like every settlement: the money already moved.
        try {
            $order->recordPayment($amount, OrderPayment::KIND_BALANCE, 'mercadopago', $paymentId !== '' ? $paymentId : $this->reference,
                null, "Saldo pagado en línea ({$this->reference})");
        } catch (\Throwable $e) {
            Log::critical('Pago de saldo confirmado sin registrar en el libro de pagos.', [
                'order_id' => $order->id, 'order_number' => $order->order_number, 'amount' => $amount,
                'reference' => $this->reference, 'payment_id' => $paymentId, 'exception' => $e->getMessage(),
            ]);
        }

        return $order->fresh();
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
        if ($this->isBalancePayment()) {
            throw new \LogicException("El pago de saldo {$this->reference} se acredita con settleBalance(), no crea un pedido.");
        }
        $settlement = null;
        $created = false;

        $order = DB::transaction(function () use ($paymentStatus, $status, $decrementStock, &$settlement, &$created) {
            $locked = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $data = $locked->payload;
            $charged = $locked->chargeAmount();
            // A made-to-order deposit is a real, successful charge that leaves a balance: the
            // order is 'partial' until the rest is paid (see Order::refreshPaymentTotals()).
            if ($paymentStatus === 'paid' && $charged + 0.005 < (float) $data['total']) {
                $paymentStatus = 'partial';
            }
            // Set only by the call that actually credits the charge — replays never re-credit.
            $settles = in_array($paymentStatus, ['paid', 'partial'], true)
                ? ['gateway' => $locked->gateway, 'ref' => $locked->gateway_ref, 'amount' => $charged, 'total' => (float) $data['total']]
                : null;

            if ($locked->order_id) {
                $order = Order::whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
                if (in_array($order->payment_status, ['paid', 'partial', 'refunded'], true)) {
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
                // Made-to-order carts open the workshop pipeline (null for stock orders).
                'production_stage'    => !empty($data['has_made_to_order']) ? 'received' : null,
                'deposit_amount'      => $data['deposit_amount'] ?? null,
                'required_by'         => $data['required_by'] ?? null,
                'estimated_ready_at'  => $data['estimated_ready_at'] ?? null,
            ]);
            $order->items()->createMany($data['items']);
            $store->increment('total_orders');

            if ($decrementStock) {
                $this->decrementStockFor($data['items']);
            }

            $locked->update(['order_id' => $order->id]);
            $settlement = $settles;
            $created = true;
            return $order;
        });

        if ($settlement) {
            $this->recordSettlement($order, $settlement);
        }
        if ($created) {
            $this->linkAttachments($order);
            // Where the sale came from (Dashboard → Marketing). Swallows its own errors.
            app(\App\Services\Marketing\TrackingContext::class)->recordAttribution($order, $this->payload['tracking'] ?? null);
        }

        return $order;
    }

    /**
     * Hands each made-to-order line the buyer's uploads (logo, reference) it was priced
     * with. Outside the transaction on purpose, like the ledger: a paid order must never
     * be rolled back because a file link failed — the snapshot still names the file.
     */
    private function linkAttachments(Order $order): void
    {
        try {
            foreach ($order->items()->whereNotNull('customization')->get() as $item) {
                $tokens = array_values(array_filter(array_column($item->customization ?? [], 'attachment_token')));
                if ($tokens !== []) {
                    Attachment::whereIn('token', $tokens)->where('store_id', $order->store_id)->whereNull('attachable_type')
                        ->update(['attachable_type' => $item->getMorphClass(), 'attachable_id' => $item->id]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('No se pudieron vincular los archivos del cliente al pedido.', [
                'order_id' => $order->id, 'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Credits the confirmed charge to the payments ledger — deliberately outside the
     * transaction above: the gateway has already moved real money, so a ledger problem
     * must never roll back the order the buyer just paid for. It is logged for manual
     * reconciliation instead (see the Mercado Pago charge-loss incident in the vault).
     */
    private function recordSettlement(Order $order, array $settlement): void
    {
        $kind = $settlement['amount'] + 0.005 < $settlement['total'] ? OrderPayment::KIND_DEPOSIT : OrderPayment::KIND_FULL;
        try {
            $order->recordPayment($settlement['amount'], $kind, $settlement['gateway'], $settlement['ref']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::critical('Pago confirmado sin registrar en el libro de pagos.', [
                'order_id' => $order->id, 'order_number' => $order->order_number, 'amount' => $settlement['amount'],
                'gateway' => $settlement['gateway'], 'gateway_ref' => $settlement['ref'], 'exception' => $e->getMessage(),
            ]);
        }
    }

    private function decrementStockFor(array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            // Made-to-order lines are produced after the sale, never taken from stock.
            if (!$product || !$product->track_stock || array_key_exists('customization', $item)) {
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
