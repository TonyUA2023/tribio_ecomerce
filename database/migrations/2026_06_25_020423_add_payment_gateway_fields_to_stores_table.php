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
            $table->string('checkout_mode')->default('whatsapp'); // whatsapp | card
            $table->string('payment_gateway')->nullable(); // culqi | mercado_pago
            $table->string('gateway_public_key')->nullable();
            $table->string('gateway_private_key')->nullable();
            $table->text('gateway_access_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'checkout_mode',
                'payment_gateway',
                'gateway_public_key',
                'gateway_private_key',
                'gateway_access_token'
            ]);
        });
    }
};
