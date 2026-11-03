<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 4a — POS: cash registers, register sessions (opening float → count at close)
 * and cash movements. A counter sale is an ordinary order (source = pos) so reports, the
 * CRM, reorder reminders and the mobile Orders API stay unified.
 *
 * orders.source and orders.payment_status were MySQL ENUMs: "pos" (and the "partial"
 * status the payments ledger already writes) were rejected by the database. Both become
 * strings, the same fix as stores.category. See tribio_brain Point-Of-Sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 20)->default('store')->change();
            $table->string('payment_status', 20)->default('pending')->change();
        });

        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
        });

        Schema::create('register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->default('open');      // open | closed
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_float', 12, 2)->default(0);
            // Frozen at close: what the drawer should hold, what was counted, and the gap.
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->json('totals')->nullable();                 // money taken per method at close
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['register_id', 'status']);
            $table->index(['store_id', 'opened_at']);
        });

        Schema::create('register_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_session_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                        // in | out
            $table->decimal('amount', 12, 2);
            $table->string('reason', 160);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('register_session_id')->nullable()->after('source')->constrained()->nullOnDelete();
            // Client-generated id of a counter sale: a double tap or a retry never sells twice.
            $table->string('pos_uuid', 36)->nullable()->unique()->after('register_session_id');
            $table->decimal('cash_tendered', 12, 2)->nullable()->after('pos_uuid');
            $table->decimal('change_given', 12, 2)->nullable()->after('cash_tendered');
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('method', 20)->nullable()->after('gateway');   // cash | yape | plin | card | transfer
            $table->foreignId('register_session_id')->nullable()->after('method')->constrained()->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Sold by packaging ("Caja" × 2): quantity stays in base units for stock and reports.
            $table->string('unit_name', 40)->nullable()->after('quantity');
            $table->unsignedInteger('unit_quantity')->nullable()->after('unit_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_name', 'unit_quantity']);
        });
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('register_session_id');
            $table->dropColumn('method');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('register_session_id');
            $table->dropUnique(['pos_uuid']);
            $table->dropColumn(['pos_uuid', 'cash_tendered', 'change_given']);
        });
        Schema::dropIfExists('register_movements');
        Schema::dropIfExists('register_sessions');
        Schema::dropIfExists('registers');
        // source/payment_status stay strings: the ENUMs rejected values already in use.
    }
};
