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

/** Tells a made-to-order buyer their order moved to a new workshop stage. */
class OrderProductionUpdated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public ?string $note = null)
    {
    }

    public function envelope(): Envelope
    {
        $store = $this->order->store;

        return new Envelope(
            from: new Address(config('mail.from.address'), $store->name),
            replyTo: $store->contact_email ? [new Address($store->contact_email, $store->name)] : [],
            subject: "Tu pedido #{$this->order->order_number}: {$this->order->production_stage_label}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.orders.production',
            with: [
                'order' => $this->order,
                'store' => $this->order->store,
                'note' => $this->note,
                'balanceUrl' => (float) $this->order->balance_due > 0 ? $this->order->balancePaymentUrl() : null,
            ],
        );
    }
}
