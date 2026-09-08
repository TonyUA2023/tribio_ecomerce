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
            $table->boolean('is_express_shipping_enabled')->default(false)->after('checkout_mode');
            $table->decimal('express_shipping_cost', 10, 2)->default(0.00)->after('is_express_shipping_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['is_express_shipping_enabled', 'express_shipping_cost']);
        });
    }
};
