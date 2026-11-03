<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 1d — conteo físico. Cada línea guarda lo que decía el sistema en el momento
 * en que se contó ese producto (expected_qty), así las ventas que ocurren mientras se
 * cuenta no falsean la diferencia: al aplicar, el stock actual se corrige en
 * (contado − esperado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 20);                            // CF-0001, por tienda
            $table->string('status', 20)->default('open');           // open | applied | cancelled
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('blind')->default(true);                 // no mostrar lo que dice el sistema
            $table->text('notes')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->decimal('variance_value', 12, 2)->nullable();    // valor (a costo) de la diferencia aplicada
            $table->timestamps();

            $table->unique(['store_id', 'number']);
            $table->index(['store_id', 'status']);
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('expected_qty');                          // stock del sistema al contar (o al crear)
            $table->unsignedInteger('counted_qty')->nullable();       // null = aún no contado
            $table->decimal('unit_cost', 12, 4)->nullable();          // costo al contar, para valorizar la diferencia
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
    }
};
