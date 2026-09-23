<?php

namespace App\Mail;

use App\Models\ProductReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the store owner a customer just reviewed one of their products. */
class ProductReviewReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ProductReview $review)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('app.name')),
            subject: "⭐ Nueva reseña de {$this->review->rating}/5 — " . ($this->review->product?->name ?? 'tu tienda'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reviews.received',
            with: [
                'review'       => $this->review,
                'store'        => $this->review->store,
                'productName'  => $this->plain($this->review->product?->name ?? 'un producto'),
                'reviewerName' => $this->plain($this->review->reviewer_name),
                'commentHtml'  => $this->review->comment ? $this->commentHtml($this->review->comment) : null,
                'dashboardUrl' => route('dashboard.resenas.index'),
            ],
        );
    }

    /**
     * The mail body goes through a markdown renderer, and reviews are customer-written:
     * names lose the characters markdown gives meaning to, and the comment is emitted as
     * a single-line HTML block (escaped, newlines → <br>) that markdown leaves untouched —
     * so a review can't smuggle a clickable link or formatting into the owner's inbox.
     */
    private function plain(string $text): string
    {
        return trim(preg_replace('/[\\\\`*_{}\[\]()#+!|<>~\r\n]+/u', ' ', $text) ?? $text);
    }

    private function commentHtml(string $comment): string
    {
        return '<div>' . str_replace(["\r", "\n"], ['', '<br>'], e($comment)) . '</div>';
    }
}
