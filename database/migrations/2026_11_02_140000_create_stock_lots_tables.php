<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 1 — lotes: cada entrada de mercadería es un lote con su costo y vencimiento.
 * products.stock sigue siendo el total que leen la tienda, la app y los catálogos; los
 * lotes son el detalle detrás de ese número (StockService los mantiene cuadrados).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->nullable();                  // lote del proveedor / fabricante
            $table->date('expires_at')->nullable();
            $table->decimal('unit_cost', 12, 4)->nullable();         // null = costo desconocido
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_remaining');
            $table->string('origin', 20)->default('receipt');        // receipt | opening | adjustment
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['product_id', 'quantity_remaining']);
            $table->index(['store_id', 'expires_at']);
        });

        // De qué lote salió (o a cuál entró) cada movimiento: costo exacto y trazabilidad.
        Schema::create('stock_lot_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_lot_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');                              // + entra al lote, − sale del lote
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->timestamps();

            $table->index('stock_lot_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 4)->nullable()->after('stock_after');
            $table->decimal('total_cost', 12, 2)->nullable()->after('unit_cost');
            $table->index(['store_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'reference']);
            $table->dropColumn(['unit_cost', 'total_cost']);
        });
        Schema::dropIfExists('stock_lot_movements');
        Schema::dropIfExists('stock_lots');
    }
};
