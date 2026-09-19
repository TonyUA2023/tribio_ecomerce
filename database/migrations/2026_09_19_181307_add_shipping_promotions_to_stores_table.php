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
            // Free shipping kicks in when EITHER threshold is met (OR, not AND) — a store
            // owner can use one, both, or neither. See Store::qualifiesForFreeShipping().
            $table->unsignedInteger('free_shipping_min_quantity')->nullable();
            $table->decimal('free_shipping_min_amount', 10, 2)->nullable();
            // Bulk discount: a single quantity threshold, applied to the order subtotal
            // before shipping. See Store::calculateBulkDiscount().
            $table->unsignedInteger('bulk_discount_min_quantity')->nullable();
            $table->string('bulk_discount_type')->nullable(); // 'percentage' | 'fixed'
            $table->decimal('bulk_discount_value', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'free_shipping_min_quantity',
                'free_shipping_min_amount',
                'bulk_discount_min_quantity',
                'bulk_discount_type',
                'bulk_discount_value',
            ]);
        });
    }
};
