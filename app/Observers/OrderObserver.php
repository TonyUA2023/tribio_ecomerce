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
        // OJO: no cargar 'items' aquí. Este evento se dispara en el instante en que se
        // inserta la fila del pedido — antes de que StoreController::checkout() llame a
        // $order->items()->createMany(...) unas líneas más abajo, en la misma petición.
        // Si cacheamos la relación 'items' aquí, queda vacía para siempre en este objeto
        // ($order->items ya no vuelve a consultar la base de datos), y cualquier código que
        // corra después en la misma petición (p. ej. la creación de la preference de Mercado
        // Pago) ve un pedido sin productos aunque sí se hayan guardado correctamente.
        // Los correos no se ven afectados: al ir en cola, Laravel recarga el pedido desde
        // cero cuando el worker los procesa, así que si ya existen los items para entonces.
        $order->loadMissing('store');

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
