<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `national_shipping_cost` was NOT NULL with a DB-level default of 0.00, so every
     * store had a "configured" value from the moment the column existed — even one the
     * owner never touched. StoreController::resolveShippingCostForStore() treats
     * non-null as "use this", so Zonas de Envío's country-wide PE rate (ShippingRate
     * with country_code=PE, state=null) could never actually be reached: the 0.00
     * default always won first, silently making national shipping free. Making the
     * column nullable (paired with the StoreSettingsController change that now saves
     * a blank field as NULL instead of 0.00) lets "not configured" mean exactly that.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('national_shipping_cost', 10, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('national_shipping_cost', 10, 2)->nullable(false)->default(0.00)->change();
        });
    }
};
