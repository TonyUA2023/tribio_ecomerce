<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Whether 2026_11_02_100000_create_contacts_table has run. The deploy never runs
 * migrations, and the order observer runs inside every checkout: until the table and
 * orders.contact_id exist, nothing CRM-related may touch the database.
 */
class CrmSchema
{
    private const CACHE_KEY = 'crm.schema_ready';
    private const PROFILE_CACHE_KEY = 'crm.profile_ready';
    private const REORDER_CACHE_KEY = 'crm.reorder_ready';
    private const TEMPLATES_CACHE_KEY = 'crm.templates_ready';
    private const DEALS_CACHE_KEY = 'crm.deals_ready';

    public function ready(): bool
    {
        // Once the tables exist they stay: remember only the positive answer.
        if (Cache::get(self::CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = Schema::hasTable('contacts') && Schema::hasColumn('orders', 'contact_id');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Customer profile, timeline and follow-ups (2026_11_03_100000) on top of the contacts. */
    public function profileReady(): bool
    {
        if (Cache::get(self::PROFILE_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->ready() && Schema::hasColumn('contacts', 'lifecycle_stage')
                && Schema::hasTable('contact_events') && Schema::hasTable('contact_tasks');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::PROFILE_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Recompra (2026_11_03_120000): products.consumption_days on top of the follow-ups. */
    public function reorderReady(): bool
    {
        if (Cache::get(self::REORDER_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->profileReady() && Schema::hasColumn('products', 'consumption_days');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::REORDER_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Opportunities (2026_11_03_160000) on top of the customer profile. */
    public function dealsReady(): bool
    {
        if (Cache::get(self::DEALS_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->profileReady() && Schema::hasTable('deals');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::DEALS_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** WhatsApp templates (2026_11_03_140000). Without it the built-in templates still work, unedited. */
    public function templatesReady(): bool
    {
        if (Cache::get(self::TEMPLATES_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->profileReady() && Schema::hasTable('message_templates');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::TEMPLATES_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }
}
