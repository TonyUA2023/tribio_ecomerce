<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 1e — presentaciones y combos.
 * - product_units: "Caja = 100 und": se compra (y luego se venderá en el POS) por
 *   presentación; el stock siempre está en la unidad base del producto.
 * - product_components: un combo se arma con otros productos; su stock es derivado
 *   (cuántos combos alcanzan) y al venderlo se descuentan sus componentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);                              // caja, docena, saco, rollo
            $table->unsignedInteger('factor');                       // unidades base que trae (≥ 2)
            $table->string('barcode', 60)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity');                     // unidades base del componente por combo
            $table->timestamps();

            $table->unique(['kit_product_id', 'component_product_id']);
            $table->index('component_product_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('unit_name', 40)->nullable()->after('product_id');   // null = unidad base
            $table->unsignedInteger('unit_factor')->default(1)->after('unit_name');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['unit_name', 'unit_factor']);
        });
        Schema::dropIfExists('product_components');
        Schema::dropIfExists('product_units');
    }
};
