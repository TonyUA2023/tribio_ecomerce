<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Made-to-order commerce, Phase 1 (B2C): products produced per order instead of taken
 * from stock, buyer customization per line, merchant-defined deposit, and a workshop
 * stage per order. Everything is additive and inert unless a store opts in
 * (stores.made_to_order_enabled) and marks a product `made_to_order`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // % of the order charged at checkout when the cart has made-to-order items.
            $table->unsignedTinyInteger('deposit_percent')->default(100);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_mode', 20)->default('stock');       // stock | made_to_order
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            // What the buyer must specify (see App\Services\MadeToOrder\CustomizationSchema).
            $table->json('customization_schema')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Frozen snapshot of the buyer's answers + price extras at purchase time.
            $table->json('customization')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            // Workshop pipeline, independent from the commercial `status` enum the mobile
            // app validates (see Orders-API): null for plain stock orders.
            $table->string('production_stage', 20)->nullable()->index();
            // Deposit asked at checkout (null = the order was charged in full).
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->date('required_by')->nullable();
            $table->date('estimated_ready_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['production_stage']);
            $table->dropColumn(['production_stage', 'deposit_amount', 'required_by', 'estimated_ready_at']);
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('customization'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['sale_mode', 'lead_time_days', 'customization_schema']));
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('deposit_percent'));
    }
};
