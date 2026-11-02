<?php

namespace App\Services\AiMarketing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Whether 2026_11_01_120000_create_ai_marketing_tables has run. The deploy never runs
 * migrations, so the dashboard asks this first and shows "activando" until it has.
 */
class AiMarketingSchema
{
    private const CACHE_KEY = 'ai_marketing.schema_ready';

    public function ready(): bool
    {
        // Once the tables exist they stay: remember only the positive answer.
        if (Cache::get(self::CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = Schema::hasTable('brand_kits') && Schema::hasTable('marketing_assets') && Schema::hasTable('ai_usage_logs');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }
}
