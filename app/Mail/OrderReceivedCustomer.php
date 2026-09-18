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

class OrderReceivedCustomer extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        $store = $this->order->store;

        return new Envelope(
            from: new Address(config('mail.from.address'), $store->name),
            replyTo: $store->contact_email ? [new Address($store->contact_email, $store->name)] : [],
            subject: "Confirmamos tu pedido #{$this->order->order_number} — {$store->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.customer',
            with: [
                'order' => $this->order,
                'store' => $this->order->store,
                'confirmationUrl' => route('store.order.confirmation', [$this->order->store->slug, $this->order->id]),
            ],
        );
    }
}
