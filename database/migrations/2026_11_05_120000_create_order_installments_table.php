<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 4b — credit sales ("fiado") and collections. A credit sale is an order with a
 * due_date and one or more installments of what was left to pay; payments keep going to
 * the order's payments ledger, and installments are covered in order (oldest first).
 * contacts.credit_limit caps what one customer may owe. See tribio_brain Credit-Sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Set only on credit sales: it is what makes an order a receivable.
            $table->date('due_date')->nullable()->after('change_given');
            $table->index(['store_id', 'due_date']);
        });

        Schema::create('order_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['order_id', 'number']);
            $table->index('due_date');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->nullable()->after('total_spent');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
        Schema::dropIfExists('order_installments');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'due_date']);
            $table->dropColumn('due_date');
        });
    }
};
