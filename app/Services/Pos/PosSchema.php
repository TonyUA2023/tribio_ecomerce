<?php

namespace App\Services\Pos;

use App\Services\Crm\CrmSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** The deploy never runs migrations: the POS (2026_11_05_100000) stays off until its tables exist. */
class PosSchema
{
    private const CACHE_KEY = 'pos.ready';
    private const CREDIT_CACHE_KEY = 'pos.credit_ready';
    private const EXTRAS_CACHE_KEY = 'pos.extras_ready';
    private const STAFF_CACHE_KEY = 'pos.staff_ready';

    public function __construct(private CrmSchema $crm)
    {
    }

    public function ready(): bool
    {
        if (Cache::get(self::CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->crm->ready() && Schema::hasTable('register_sessions') && Schema::hasColumn('orders', 'pos_uuid')
                && Schema::hasColumn('order_payments', 'method') && Schema::hasColumn('order_items', 'unit_name');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Credit sales and collections (2026_11_05_120000). */
    public function creditReady(): bool
    {
        if (Cache::get(self::CREDIT_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->ready() && Schema::hasTable('order_installments') && Schema::hasColumn('contacts', 'credit_limit');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::CREDIT_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Price lists, quotes and returns (2026_11_05_140000). */
    public function extrasReady(): bool
    {
        if (Cache::get(self::EXTRAS_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->ready() && Schema::hasTable('product_list_prices') && Schema::hasTable('quotes')
                && Schema::hasTable('order_returns') && Schema::hasColumn('orders', 'returned_total');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::EXTRAS_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Register staff with PIN, "modo caja" (2026_11_05_160000). */
    public function staffReady(): bool
    {
        if (Cache::get(self::STAFF_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->ready() && Schema::hasTable('store_staff') && Schema::hasColumn('orders', 'staff_id');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::STAFF_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }
}
