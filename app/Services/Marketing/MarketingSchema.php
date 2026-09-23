<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the marketing migration (2026_09_23_220000) has run on this database. The
 * deploy never runs migrations and the dev .env points at the production DB, so every
 * marketing entry point — storefront <head>, checkout, order observer, dashboard,
 * product form — asks this first and simply does nothing until the tables exist.
 */
class MarketingSchema
{
    private const CACHE_KEY = 'marketing.schema_ready';

    public function ready(): bool
    {
        // Once the tables exist they stay: remember only the positive answer.
        if (Cache::get(self::CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = Schema::hasTable('store_marketing_integrations')
                && Schema::hasTable('marketing_event_logs')
                && Schema::hasTable('order_attributions')
                && Schema::hasColumn('products', 'exclude_from_ads');
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
