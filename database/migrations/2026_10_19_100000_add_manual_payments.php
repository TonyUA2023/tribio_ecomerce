<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Pago por confirmación: Yape / Plin / transferencia / contra entrega that the
            // owner confirms by hand. Independent of the single active card gateway (ADR 0003).
            $table->json('manual_payments')->nullable();
            // Departments the store delivers to (null = everywhere the shipping rules allow).
            $table->json('delivery_coverage')->nullable();
            // Hours after delivery in which the buyer can ask for an exchange (null = not stated).
            $table->unsignedSmallInteger('exchange_hours')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            // null = not a manual payment · awaiting = buyer says "ya pagué" · confirmed · rejected
            $table->string('payment_confirmation', 20)->nullable()->index();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('payment_submitted_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->unsignedBigInteger('payment_confirmed_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_confirmation']);
            $table->dropColumn(['payment_confirmation', 'payment_reference', 'payment_submitted_at', 'payment_confirmed_at', 'payment_confirmed_by']);
        });
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['manual_payments', 'delivery_coverage', 'exchange_hours']);
        });
    }
};
