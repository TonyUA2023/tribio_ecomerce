<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\Whatsapp\CloudApi;
use App\Services\Whatsapp\CloudApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps a copy of a customer's photo, audio, video or document (Fase 3b): Meta's link
 * lasts minutes and the file only about 30 days. Private disk, served to the store only.
 */
class DownloadWhatsappMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'audio/ogg' => 'ogg', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/aac' => 'aac', 'audio/amr' => 'amr',
        'video/mp4' => 'mp4', 'video/3gpp' => '3gp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(public int $messageId)
    {
    }

    public function handle(CloudApi $api): void
    {
        $message = WhatsappMessage::with('conversation.connection')->find($this->messageId);
        $payload = $message?->payload ?? [];
        $connection = $message?->conversation?->connection;
        if (!$message || empty($payload['media_id']) || !empty($payload['path']) || !$connection?->isConnected()) {
            return;
        }

        try {
            $media = $api->media($connection, (string) $payload['media_id']);
            if ((int) ($media['file_size'] ?? 0) > CloudApi::MAX_MEDIA_BYTES || empty($media['url'])) {
                $message->forceFill(['payload' => $payload + ['too_large' => true]])->save();

                return;
            }
            $bytes = $api->download($connection, $media['url']);
        } catch (CloudApiException $e) {
            if ($e->status === 413) {
                $message->forceFill(['payload' => $payload + ['too_large' => true]])->save();

                return;
            }
            if ($e->isPermanent()) {
                $message->forceFill(['payload' => $payload + ['unavailable' => true]])->save();

                return;
            }
            throw $e;
        }

        $mime = strtolower(strtok((string) ($media['mime_type'] ?? $payload['mime_type'] ?? ''), ';'));
        $path = "whatsapp/{$message->store_id}/{$message->id}." . (self::EXTENSIONS[$mime] ?? 'bin');
        Storage::disk('local')->put($path, $bytes);

        $message->forceFill(['payload' => $payload + ['path' => $path, 'mime_type' => $mime, 'size' => strlen($bytes)]])->save();
    }
}
