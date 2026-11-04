<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de cuenta: cada negocio contrata el sistema POS (ventas, inventario, compras), la tienda
 * virtual o ambos. Los negocios que ya existían siguen con ambos (default), sin ningún cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('mode', 10)->default('both'); // both | pos | online
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
