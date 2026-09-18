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

class OrderReceivedStore extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('app.name')),
            replyTo: $this->order->customer_email ? [new Address($this->order->customer_email, $this->order->customer_name)] : [],
            subject: "🛍️ Nuevo pedido #{$this->order->order_number} — {$this->order->currency} " . number_format((float) $this->order->total, 2),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.store',
            with: [
                'order' => $this->order,
                'store' => $this->order->store,
                'dashboardUrl' => route('dashboard.pedidos.show', $this->order->id),
            ],
        );
    }
}
