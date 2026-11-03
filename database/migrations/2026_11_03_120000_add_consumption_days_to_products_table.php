<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 2c — recompra: cuántos días le dura una unidad del producto a un cliente
 * (un batido de 30 porciones = 30). Con eso ReorderReminders agenda el seguimiento antes
 * de que se le acabe. Null = el producto no se recompra (no se agenda nada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('consumption_days')->nullable()->after('low_stock_alert');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('consumption_days');
        });
    }
};
