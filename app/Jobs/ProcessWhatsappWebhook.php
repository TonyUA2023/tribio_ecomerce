<?php

namespace App\Jobs;

use App\Models\WhatsappWebhookEvent;
use App\Services\Whatsapp\WebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Processes one stored Meta webhook off the request, so the endpoint can answer 200 at once. */
class ProcessWhatsappWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(public int $eventId)
    {
    }

    public function handle(WebhookProcessor $processor): void
    {
        $event = WhatsappWebhookEvent::with('connection.store')->find($this->eventId);
        if (!$event || $event->processed_at) {
            return;
        }

        $event->increment('attempts');
        $processor->process($event);
        $event->forceFill(['processed_at' => now(), 'error' => null])->save();
    }

    public function failed(\Throwable $e): void
    {
        WhatsappWebhookEvent::whereKey($this->eventId)->update(['error' => mb_substr($e->getMessage(), 0, 255)]);
    }
}
