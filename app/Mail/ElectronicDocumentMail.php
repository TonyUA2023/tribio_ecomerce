<?php

namespace App\Mail;

use App\Models\ElectronicDocument;
use App\Models\Order;
use App\Models\Store;
use App\Services\Sunat\ComprobantePdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The comprobante electrónico once SUNAT received it: PDF to read, XML and CDR to keep. */
class ElectronicDocumentMail extends Mailable
{
    public function __construct(public ElectronicDocument $document, public Store $store, public ?Order $order)
    {
    }

    public function envelope(): Envelope
    {
        $label = $this->document->type === 'factura' ? 'Factura electrónica' : 'Boleta electrónica';

        return new Envelope(subject: "{$label} {$this->document->code()} · {$this->store->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.electronic-document', with: ['doc' => $this->document, 'store' => $this->store, 'order' => $this->order]);
    }

    public function attachments(): array
    {
        $base = $this->store->ruc . '-' . ($this->document->type === 'factura' ? '01' : '03') . '-' . $this->document->series . '-' . $this->document->number;
        $files = [];
        try {
            $files[] = Attachment::fromData(fn () => app(ComprobantePdf::class)->pdf($this->document), $base . '.pdf')->withMime('application/pdf');
        } catch (\Throwable $e) {
            report($e); // the XML below is the legal document: a PDF problem must not stop the email
        }
        if ($this->document->xml) {
            $files[] = Attachment::fromData(fn () => $this->document->xml, $base . '.xml')->withMime('application/xml');
        }
        if ($this->document->cdr_zip) {
            $files[] = Attachment::fromData(fn () => base64_decode($this->document->cdr_zip), 'R-' . $base . '.zip')->withMime('application/zip');
        }

        return $files;
    }
}
