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
    private const GOOGLE_CACHE_KEY = 'marketing.schema_google_ready';

    public function ready(): bool
    {
        return $this->remember(self::CACHE_KEY, fn () => Schema::hasTable('store_marketing_integrations')
            && Schema::hasTable('marketing_event_logs')
            && Schema::hasTable('order_attributions')
            && Schema::hasColumn('products', 'exclude_from_ads'));
    }

    /** The Google columns (2026_09_25_100000) on top of the base marketing tables. */
    public function googleReady(): bool
    {
        return $this->ready()
            && $this->remember(self::GOOGLE_CACHE_KEY, fn () => Schema::hasColumn('store_marketing_integrations', 'measurement_id'));
    }

    private function remember(string $key, \Closure $check): bool
    {
        // Once the tables exist they stay: remember only the positive answer.
        if (Cache::get($key) === true) {
            return true;
        }

        try {
            $ready = (bool) $check();
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put($key, true, now()->addDay());
        }

        return $ready;
    }
}
