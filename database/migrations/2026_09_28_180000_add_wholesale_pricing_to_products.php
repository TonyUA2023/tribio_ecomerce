<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wholesale ("por mayor") pricing per product: unit-price tiers by total quantity of the
 * product in the cart, and a minimum order quantity. Both nullable = no change for every
 * existing product. Code checks App\Services\Pricing\WholesalePricing::ready() first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // [{"min_qty": 12, "price": 25.00}, …] in the store's base currency (PEN).
            $table->json('price_tiers')->nullable()->after('compare_price');
            $table->unsignedInteger('min_quantity')->nullable()->after('price_tiers');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_tiers', 'min_quantity']);
        });
    }
};
