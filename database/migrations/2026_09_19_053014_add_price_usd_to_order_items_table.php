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
        Schema::table('order_items', function (Blueprint $table) {
            // Resolved USD-equivalent unit price, recorded for every order regardless of
            // payment method (cheap to keep, useful for accounting) — but the actual charge
            // only ever happens in USD when the order is paid via PayPal.
            $table->decimal('price_usd', 10, 2)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });
    }
};
