<?php

namespace App\Services\Marketing\Meta;

use App\Jobs\Marketing\SendMetaPurchaseEvent;
use App\Models\Order;

/**
 * Decides when an order is a "Purchase" for Meta: the first time it becomes 'paid' or
 * 'partial', whatever made it so — a gateway confirming in PendingCheckout::materialize(),
 * a voucher clearing days later, or the owner recording a WhatsApp/Yape payment in
 * the dashboard (Order::recordPayment()). Called from OrderObserver.
 */
class PurchaseTracking
{
    private const PAID = ['paid', 'partial'];

    public function __construct(private MetaIntegrationService $meta) {}

    public function orderSaved(Order $order, bool $created): void
    {
        if (!in_array($order->payment_status, self::PAID, true)) {
            return;
        }
        if (!$created && (!$order->wasChanged('payment_status')
            || in_array($order->getOriginal('payment_status'), [...self::PAID, 'refunded'], true))) {
            return; // e.g. partial → paid: paying the balance is not a second purchase
        }

        $store = $order->store;
        if (!$store || !$this->meta->active($store)?->hasConversionsApi()) {
            return;
        }

        // After commit: the order (and the PendingCheckout it came from) must be real
        // before the job reads them; a rolled-back checkout sends nothing.
        SendMetaPurchaseEvent::dispatch($order->id)->afterCommit();
    }
}
