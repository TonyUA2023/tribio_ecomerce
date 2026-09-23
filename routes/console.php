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

Artisan::command('marketing:prune-events {--days=60}', function (\App\Services\Marketing\MarketingSchema $schema) {
    if (!$schema->ready()) {
        $this->info('Módulo de marketing sin migrar: nada que limpiar.');
        return;
    }
    $removed = \App\Models\MarketingEventLog::where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();
    $this->info("Eventos de marketing eliminados: {$removed}");
})->purpose('Delete old Meta Conversions API event logs (Dashboard → Marketing activity)');

// Requires the `scheduler` program in docker/supervisord.conf (php artisan schedule:work).
Schedule::command('attachments:prune')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('marketing:prune-events')->dailyAt('04:15')->withoutOverlapping();
