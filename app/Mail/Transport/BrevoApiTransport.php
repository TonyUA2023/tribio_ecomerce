<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoApiTransport extends AbstractTransport
{
    public function __construct(private readonly ?string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (!$email instanceof Email) {
            throw new RuntimeException('BrevoApiTransport solo soporta mensajes Symfony\Mime\Email.');
        }

        if (empty($this->apiKey)) {
            throw new RuntimeException('BREVO_API_KEY no está configurada.');
        }

        $payload = [
            'sender' => $this->formatAddress($email->getFrom()[0] ?? null),
            'to' => $this->formatAddresses($email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($cc = $this->formatAddresses($email->getCc())) {
            $payload['cc'] = $cc;
        }
        if ($bcc = $this->formatAddresses($email->getBcc())) {
            $payload['bcc'] = $bcc;
        }
        if ($replyTo = $this->formatAddress($email->getReplyTo()[0] ?? null)) {
            $payload['replyTo'] = $replyTo;
        }
        if ($html = $email->getHtmlBody()) {
            $payload['htmlContent'] = $html;
        }
        if ($text = $email->getTextBody()) {
            $payload['textContent'] = $text;
        }

        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $filename = $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename') ?? 'archivo';
            $attachments[] = [
                'name' => $filename,
                'content' => base64_encode($attachment->getBody()),
            ];
        }
        if ($attachments) {
            $payload['attachment'] = $attachments;
        }

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'api-key' => $this->apiKey,
            'content-type' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $payload);

        if ($response->failed()) {
            Log::error('Brevo: fallo al enviar correo', [
                'status' => $response->status(),
                'body' => $response->body(),
                'subject' => $payload['subject'],
                'to' => $payload['to'],
            ]);
            throw new RuntimeException("Brevo API error ({$response->status()}): {$response->body()}");
        }
    }

    /** @return array{email: string, name?: string}|null */
    private function formatAddress(?Address $address): ?array
    {
        if (!$address) {
            return null;
        }

        $formatted = ['email' => $address->getAddress()];
        if ($name = $address->getName()) {
            $formatted['name'] = $name;
        }

        return $formatted;
    }

    /** @param Address[] $addresses @return array<int, array{email: string, name?: string}> */
    private function formatAddresses(array $addresses): array
    {
        return array_values(array_filter(array_map(fn (Address $address) => $this->formatAddress($address), $addresses)));
    }

    public function __toString(): string
    {
        return 'brevo+api';
    }
}
