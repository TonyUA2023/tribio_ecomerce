<?php

use App\Services\Attachments\AttachmentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('attachments:prune {--hours=48}', function (AttachmentService $attachments) {
    $removed = $attachments->pruneUnlinked(now()->subHours((int) $this->option('hours')));
    $this->info("Archivos sin pedido eliminados: {$removed}");
})->purpose('Delete buyer uploads never linked to an order (abandoned carts)');

// Requires the `scheduler` program in docker/supervisord.conf (php artisan schedule:work).
Schedule::command('attachments:prune')->dailyAt('04:00')->withoutOverlapping();
