<?php

namespace App\Services\Whatsapp;

use App\Services\Crm\CrmSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** The deploy never runs migrations: WhatsApp (2026_11_04_100000) stays off until its tables exist. */
class WhatsappSchema
{
    private const CACHE_KEY = 'whatsapp.ready';
    private const TEMPLATES_CACHE_KEY = 'whatsapp.templates_ready';
    private const BROADCASTS_CACHE_KEY = 'whatsapp.broadcasts_ready';

    public function __construct(private CrmSchema $crm)
    {
    }

    public function ready(): bool
    {
        if (Cache::get(self::CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->crm->profileReady() && Schema::hasTable('whatsapp_connections') && Schema::hasTable('whatsapp_messages');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Meta-approved templates (2026_11_04_120000). */
    public function templatesReady(): bool
    {
        if (Cache::get(self::TEMPLATES_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->ready() && Schema::hasTable('whatsapp_templates');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::TEMPLATES_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Broadcasts, budget and per-message cost (2026_11_04_140000). */
    public function broadcastsReady(): bool
    {
        if (Cache::get(self::BROADCASTS_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->templatesReady() && Schema::hasTable('whatsapp_broadcasts') && Schema::hasColumn('whatsapp_messages', 'cost');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::BROADCASTS_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }
}
