<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the PREVIOUS address, so an unexpected change doesn't go unnoticed. */
class AccountEmailChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $newEmail)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu correo de acceso a Tribio cambió');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.account.email-changed', with: [
            'name' => $this->name,
            'maskedEmail' => $this->mask($this->newEmail),
            'supportUrl' => 'https://wa.me/' . config('tribio.support.whatsapp_number'),
        ]);
    }

    private function mask(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($user, 0, 2) . str_repeat('•', max(1, mb_strlen($user) - 2)) . '@' . $domain;
    }
}
