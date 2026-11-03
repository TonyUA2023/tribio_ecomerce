<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\Whatsapp\BroadcastService;
use App\Services\Whatsapp\InboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Sends one inbox reply or broadcast message through the Cloud API (Fase 3a/3c). Throttling and outages are retried. */
class SendWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [15, 60];

    public function __construct(public int $messageId)
    {
    }

    public function handle(InboxService $inbox): void
    {
        if ($message = WhatsappMessage::with('conversation.connection')->find($this->messageId)) {
            $inbox->deliver($message);
            if ($message->broadcast_id) {
                app(BroadcastService::class)->settle($message->broadcast_id);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        if (($message = WhatsappMessage::find($this->messageId)) && $message->status === 'queued') {
            app(InboxService::class)->fail($message, 'No se pudo enviar: Meta no respondió. Inténtalo de nuevo.');
            if ($message->broadcast_id) {
                app(BroadcastService::class)->settle($message->broadcast_id);
            }
        }
    }
}
