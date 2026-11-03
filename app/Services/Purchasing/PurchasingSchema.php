<?php

namespace App\Services\Purchasing;

use App\Services\Inventory\InventorySchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Whether 2026_11_02_160000_create_purchasing_tables has run (and the lot tables it
 * receives into). The deploy never migrates: until then Compras shows "activando".
 */
class PurchasingSchema
{
    private const CACHE_KEY = 'purchasing.schema_ready';

    public function __construct(private InventorySchema $inventory)
    {
    }

    public function ready(): bool
    {
        if (Cache::get(self::CACHE_KEY) === true) {
            return true;
        }

        try {
            $ready = $this->inventory->lotsReady() && Schema::hasTable('suppliers')
                && Schema::hasTable('purchase_orders') && Schema::hasTable('purchase_order_items');
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
