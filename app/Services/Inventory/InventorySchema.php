<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Whether 2026_11_02_140000_create_stock_lots_tables has run. Checkout calls StockService
 * on every sale and the deploy never migrates: until the lot tables exist, stock moves
 * exactly as before (no lots, no costs).
 */
class InventorySchema
{
    private const CACHE_KEY = 'inventory.lots_ready';
    private const COUNTS_CACHE_KEY = 'inventory.counts_ready';
    private const UNITS_CACHE_KEY = 'inventory.units_ready';

    public function lotsReady(): bool
    {
        // Once the tables exist they stay: remember only the positive answer.
        if (Cache::get(self::CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = Schema::hasTable('stock_lots') && Schema::hasTable('stock_lot_movements')
                && Schema::hasColumn('inventory_movements', 'total_cost');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Stock counts (2026_11_02_180000) on top of the lots they correct. */
    public function countsReady(): bool
    {
        if (Cache::get(self::COUNTS_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->lotsReady() && Schema::hasTable('stock_counts') && Schema::hasTable('stock_count_items');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::COUNTS_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }

    /** Packagings and combos (2026_11_02_200000), which also need the purchase tables. */
    public function unitsReady(): bool
    {
        if (Cache::get(self::UNITS_CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->lotsReady() && Schema::hasTable('product_units') && Schema::hasTable('product_components')
                && Schema::hasColumn('purchase_order_items', 'unit_factor');
        } catch (\Throwable $e) {
            report($e);
            return false;
        }

        if ($ready) {
            Cache::put(self::UNITS_CACHE_KEY, true, now()->addDay());
        }

        return $ready;
    }
}
