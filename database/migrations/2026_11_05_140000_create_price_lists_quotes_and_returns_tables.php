<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 4c — price lists (menor / mayorista / distribuidor, following the contact's
 * relationship), quotes ("proformas") that become counter sales, and returns of counter
 * sales with an internal credit note. A return never edits the sale: orders.returned_total
 * lowers what is owed, and money given back is a negative ledger row. See tribio_brain
 * Returns-Quotes-And-Price-Lists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_list_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('list', 20);                         // mayorista | distribuidor
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['product_id', 'list']);
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('status', 12)->default('open');     // open | sold | cancelled (expired = open past valid_until)
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('price_list', 20)->nullable();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_document', 11)->nullable();
            // Snapshot of the lines as quoted: product, variant, packaging, quantity, price.
            $table->json('items');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->date('valid_until');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'number']);
            $table->index(['store_id', 'status', 'created_at']);
        });

        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('register_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 255);
            // [{order_item_id, product_name, quantity (base units), amount, restock}]
            $table->json('items');
            $table->decimal('total', 12, 2);
            $table->decimal('debt_reduced', 12, 2)->default(0);  // taken off what the customer owed
            $table->decimal('refunded', 12, 2)->default(0);      // money given back
            $table->string('method', 20)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'number']);
            $table->index('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('returned_total', 12, 2)->default(0)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('returned_total');
        });
        Schema::dropIfExists('order_returns');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('product_list_prices');
    }
};
