<?php

namespace App\Jobs;

use App\Models\WhatsappBroadcast;
use App\Services\Whatsapp\BroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Queues the messages of one broadcast (Fase 3c). Not retried: a half-built broadcast must not double-send. */
class StartWhatsappBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $broadcastId)
    {
    }

    public function handle(BroadcastService $broadcasts): void
    {
        if ($broadcast = WhatsappBroadcast::with(['connection.store', 'template'])->find($this->broadcastId)) {
            $broadcasts->start($broadcast);
        }
    }

    public function failed(\Throwable $e): void
    {
        $broadcast = WhatsappBroadcast::find($this->broadcastId);
        if ($broadcast?->status === 'sending' && !$broadcast->started_at) {
            $queued = $broadcast->messages()->count();
            $broadcast->forceFill([
                'status'           => $queued ? 'sending' : 'failed',
                'started_at'       => $queued ? now() : null,
                'recipients_count' => $queued,
                'error'            => $queued ? "Se interrumpió al preparar los mensajes: salieron {$queued}." : 'No se pudo preparar la difusión. Inténtalo de nuevo.',
                'finished_at'      => $queued ? null : now(),
            ])->save();
            app(BroadcastService::class)->settle($broadcast->id);
        }
    }
}
