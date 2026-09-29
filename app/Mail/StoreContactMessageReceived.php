<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the store owner that a buyer wrote from the storefront (contact, complaint, sign-up). */
class StoreContactMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage)
    {
    }

    public function envelope(): Envelope
    {
        $message = $this->contactMessage;
        $prefix = match ($message->kind) {
            ContactMessage::KIND_COMPLAINT => '📕 Nuevo reclamo',
            ContactMessage::KIND_SUBSCRIPTION => 'Nueva suscripción',
            default => 'Nuevo mensaje',
        };

        return new Envelope(
            subject: "{$prefix} en {$message->store->name}: {$message->name}",
            replyTo: [new Address($message->email, $message->name)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.contact.received', with: [
            'contact' => $this->contactMessage,
            'isComplaint' => $this->contactMessage->kind === ContactMessage::KIND_COMPLAINT,
            'url' => route('dashboard.mensajes.show', $this->contactMessage->id),
        ]);
    }
}
