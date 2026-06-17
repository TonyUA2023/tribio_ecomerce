<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Movimientos de inventario (historial completo)
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment', 'sale', 'return', 'loss']);
            $table->integer('quantity');                    // Positivo = entrada, Negativo = salida
            $table->integer('stock_before');               // Stock antes del movimiento
            $table->integer('stock_after');                // Stock después del movimiento
            $table->string('reason')->nullable();          // Motivo del ajuste
            $table->string('reference')->nullable();       // Referencia (order_id, etc.)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Quién lo hizo
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['store_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
