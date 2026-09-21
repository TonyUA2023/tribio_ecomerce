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
        Schema::create('pending_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // The reference is what the gateway echoes back to us (Flow's commerceOrder,
            // Mercado Pago's external_reference, PayPal's purchase_unit reference_id) —
            // it looks like an order number so support can recognize it, but nothing in
            // `orders`/stock exists yet until materialize() runs.
            $table->string('reference')->unique();
            $table->string('gateway');
            // Full snapshot of what would become the Order + OrderItems: customer data,
            // items, totals, currency, payment_method. Nothing is written to `orders` or
            // product stock until the gateway actually confirms a result.
            $table->json('payload');
            // Gateway-side identifier used to look this up from a webhook/return that
            // doesn't carry our `reference` (e.g. Flow's token, a Mercado Pago payment id).
            $table->string('gateway_ref')->nullable()->index();
            // Extra gateway response data needed to verify a later callback (e.g. Flow's
            // flowOrder) — kept separate from `payload` so payload stays a pure snapshot
            // of the checkout request itself.
            $table->json('gateway_meta')->nullable();
            // Set exactly once, by materialize(), guarded by a DB-unique constraint so a
            // webhook and a redirect-return racing each other can never create two orders.
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_checkouts');
    }
};
