<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the buyer the store verified their Yape/Plin/transfer payment (or could not). */
class OrderPaymentConfirmed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public bool $confirmed = true, public ?string $note = null)
    {
    }

    public function envelope(): Envelope
    {
        $store = $this->order->store;

        return new Envelope(
            from: new Address(config('mail.from.address'), $store->name),
            replyTo: $store->contact_email ? [new Address($store->contact_email, $store->name)] : [],
            subject: $this->confirmed
                ? "Confirmamos el pago de tu pedido #{$this->order->order_number}"
                : "No pudimos confirmar el pago de tu pedido #{$this->order->order_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.payment-confirmed',
            with: ['order' => $this->order, 'store' => $this->order->store, 'confirmed' => $this->confirmed, 'note' => $this->note],
        );
    }
}
