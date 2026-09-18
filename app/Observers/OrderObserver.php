<?php

namespace App\Observers;

use App\Mail\OrderReceivedCustomer;
use App\Mail\OrderReceivedStore;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OrderObserver
{
    /**
     * Fires once per order, right after it's placed (any payment method/source).
     * Both sends are queued, so a Brevo outage never blocks or slows down checkout.
     */
    public function created(Order $order): void
    {
        $order->loadMissing(['items', 'store']);

        if ($order->customer_email) {
            try {
                Mail::to($order->customer_email)->queue(new OrderReceivedCustomer($order));
            } catch (Throwable $e) {
                Log::error('No se pudo encolar el correo de pedido para el cliente.', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $storeEmail = $order->store?->contact_email ?: $order->store?->email;
        if ($storeEmail) {
            try {
                Mail::to($storeEmail)->queue(new OrderReceivedStore($order));
            } catch (Throwable $e) {
                Log::error('No se pudo encolar el correo de pedido para la tienda.', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
