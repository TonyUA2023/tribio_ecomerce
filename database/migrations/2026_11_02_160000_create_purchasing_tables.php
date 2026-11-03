<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 1c — compras: proveedores y órdenes de compra. Al recibir una orden, cada línea
 * entra al inventario como un lote (StockService) con su costo puesto en tienda: el costo
 * unitario más su parte del flete y otros gastos de la compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document_number', 20)->nullable();      // RUC o DNI
            $table->string('contact_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'name']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 20);                            // OC-0001, por tienda
            $table->string('status', 20)->default('draft');          // draft | ordered | received | cancelled
            $table->date('expected_at')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);          // Σ cantidad × costo pedido
            $table->decimal('extra_costs', 12, 2)->default(0);       // flete, estiba, comisiones
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'number']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity_ordered');
            $table->decimal('unit_cost', 12, 4);
            $table->unsignedInteger('quantity_received')->nullable();
            $table->decimal('landed_unit_cost', 12, 4)->nullable();  // costo + parte de los gastos extra
            $table->string('lot_code', 40)->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
