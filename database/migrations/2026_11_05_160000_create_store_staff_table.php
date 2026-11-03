<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 4d — register staff. Cashiers and managers identify with a PIN on a device the
 * owner put in "modo caja" (the owner's account stays signed in; staff never get a login).
 * Every counter action records who did it and, for restricted ones, which manager
 * approved it. See tribio_brain Register-Staff-And-Offline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('role', 20);                          // cajero | encargado
            $table->string('pin_hash');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('register_session_id')->constrained('store_staff')->nullOnDelete();
            $table->foreignId('approved_staff_id')->nullable()->after('staff_id')->constrained('store_staff')->nullOnDelete();
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('register_session_id')->constrained('store_staff')->nullOnDelete();
        });
        Schema::table('register_sessions', function (Blueprint $table) {
            $table->foreignId('opened_staff_id')->nullable()->after('opened_by')->constrained('store_staff')->nullOnDelete();
            $table->foreignId('closed_staff_id')->nullable()->after('closed_by')->constrained('store_staff')->nullOnDelete();
        });
        Schema::table('register_movements', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('user_id')->constrained('store_staff')->nullOnDelete();
            $table->foreignId('approved_staff_id')->nullable()->after('staff_id')->constrained('store_staff')->nullOnDelete();
        });
        Schema::table('order_returns', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('user_id')->constrained('store_staff')->nullOnDelete();
            $table->foreignId('approved_staff_id')->nullable()->after('staff_id')->constrained('store_staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_staff_id');
            $table->dropConstrainedForeignId('staff_id');
        });
        Schema::table('register_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_staff_id');
            $table->dropConstrainedForeignId('staff_id');
        });
        Schema::table('register_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_staff_id');
            $table->dropConstrainedForeignId('opened_staff_id');
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_staff_id');
            $table->dropConstrainedForeignId('staff_id');
        });
        Schema::dropIfExists('store_staff');
    }
};
