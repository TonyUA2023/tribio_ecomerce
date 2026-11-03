<?php

namespace App\Observers;

use App\Jobs\RefreshReorderReminders;
use App\Mail\OrderReceivedCustomer;
use App\Mail\OrderReceivedStore;
use App\Models\Contact;
use App\Models\Order;
use App\Services\Crm\ContactResolver;
use App\Services\Crm\CrmSchema;
use App\Services\Marketing\Meta\PurchaseTracking;
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

        // A counter sale (ERP Fase 4a) gets its ticket at the register: no e-mails, and it is
        // not a website purchase for Meta. The CRM and reorder reminders still follow it.
        if ($order->source === 'pos') {
            $this->linkContact($order);
            $this->scheduleReorderReminders($order);

            return;
        }

        // A rejected gateway attempt is still worth recording (see PendingCheckout::
        // materialize()), but telling the buyer "thanks for your order" on a failed
        // payment would repeat the exact false-success problem the confirmation page
        // used to have — the store owner's copy below is unaffected, they should know.
        if ($order->customer_email && $order->payment_status !== 'failed') {
            try {
                Mail::to($order->customer_email)->queue((new OrderReceivedCustomer($order))->afterCommit());
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
                Mail::to($storeEmail)->queue((new OrderReceivedStore($order))->afterCommit());
            } catch (Throwable $e) {
                Log::error('No se pudo encolar el correo de pedido para la tienda.', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->reportPurchase($order, created: true);
        $this->linkContact($order);
        $this->scheduleReorderReminders($order);
    }

    /** A pending order that gets paid later (voucher cleared, owner recorded a Yape payment…). */
    public function updated(Order $order): void
    {
        if ($order->source !== 'pos') {
            $this->reportPurchase($order, created: false);
        }

        if ($order->wasChanged(['status', 'payment_status', 'total', 'amount_paid', 'currency', 'customer_phone', 'customer_email', 'customer_document_number'])) {
            $this->linkContact($order);
        }
        if ($order->wasChanged(['status', 'payment_status', 'contact_id'])) {
            $this->scheduleReorderReminders($order);
        }
    }

    /**
     * Recompra (Fase 2c): recomputed once the checkout transaction commits, when the
     * order's items exist. Queued — a reminder can never slow down or break a sale.
     */
    private function scheduleReorderReminders(Order $order): void
    {
        try {
            if (app(CrmSchema::class)->reorderReady()) {
                RefreshReorderReminders::dispatch($order->id)->afterCommit();
            }
        } catch (Throwable $e) {
            Log::error('No se pudo agendar la recompra del pedido.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dashboard → Clientes: the order joins its customer's contact (created on first
     * order) and the contact's totals follow payment/status changes. Runs inside the
     * checkout transaction, so it must never throw: a CRM problem can't cost a sale.
     */
    private function linkContact(Order $order): void
    {
        try {
            if (!app(CrmSchema::class)->ready()) {
                return;
            }
            $resolver = app(ContactResolver::class);
            $contact = $order->contact_id ? Contact::find($order->contact_id) : null;
            if ($contact && !$order->wasChanged(['customer_phone', 'customer_email', 'customer_document_number'])) {
                $resolver->refreshTotals($contact);
            } else {
                $resolver->linkOrder($order);
                if ($contact && $contact->id !== $order->contact_id) {
                    $resolver->refreshTotals($contact);
                }
            }
        } catch (Throwable $e) {
            Log::error('No se pudo enlazar el pedido con su cliente.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Meta Conversions API "Purchase" (Dashboard → Marketing). Never allowed to break a save. */
    private function reportPurchase(Order $order, bool $created): void
    {
        try {
            app(PurchaseTracking::class)->orderSaved($order, $created);
        } catch (Throwable $e) {
            Log::error('No se pudo encolar la compra para Meta.', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
