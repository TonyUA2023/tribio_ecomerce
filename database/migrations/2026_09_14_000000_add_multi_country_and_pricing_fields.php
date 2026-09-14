<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'enabled_countries')) {
                $table->json('enabled_countries')->nullable()->after('country');
            }
            if (!Schema::hasColumn('stores', 'national_shipping_cost')) {
                $table->decimal('national_shipping_cost', 10, 2)->default(0.00)->after('express_shipping_cost');
            }
            if (!Schema::hasColumn('stores', 'country_shipping_costs')) {
                $table->json('country_shipping_costs')->nullable()->after('national_shipping_cost');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'currency_prices')) {
                $table->json('currency_prices')->nullable()->after('compare_price_usd');
            }
            if (!Schema::hasColumn('products', 'compare_currency_prices')) {
                $table->json('compare_currency_prices')->nullable()->after('currency_prices');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['enabled_countries', 'national_shipping_cost', 'country_shipping_costs']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['currency_prices', 'compare_currency_prices']);
        });
    }
};