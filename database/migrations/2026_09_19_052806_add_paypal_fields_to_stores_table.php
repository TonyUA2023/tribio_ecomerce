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
            // The store's OWN PayPal app credentials (independent of `payment_gateway`/
            // `gateway_*`, which back the single-select Culqi/Mercado Pago slot) — the
            // storefront checkout offers PayPal whenever these are present, same pattern
            // as `mp_access_token`/`mp_public_key`.
            $table->string('paypal_client_id')->nullable();
            $table->text('paypal_client_secret')->nullable();
            $table->string('paypal_mode')->default('sandbox'); // sandbox | live
            $table->string('paypal_webhook_id')->nullable(); // optional, for webhook signature verification
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['paypal_client_id', 'paypal_client_secret', 'paypal_mode', 'paypal_webhook_id']);
        });
    }
};
