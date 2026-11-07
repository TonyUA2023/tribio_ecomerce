<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enviar a SUNAT pasa a ser una acción sobre cada comprobante (botón «Enviar a SUNAT»), no una casilla al cobrar:
 * - document_series.electronic: una serie puede ser «solo interna» (sus boletas o facturas llevan su numeración
 *   pero nunca se informan a SUNAT), para no dejar huecos en las series que sí se informan.
 * - Facturas y boletas se envían con el botón por defecto; «todas automáticas» sigue siendo opcional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_series', function (Blueprint $table) {
            $table->boolean('electronic')->default(true)->after('last_number');
        });

        Schema::table('sunat_settings', function (Blueprint $table) {
            $table->string('factura_mode', 10)->default('manual')->change();
        });
        DB::table('sunat_settings')->where('factura_mode', 'auto')->update(['factura_mode' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('sunat_settings', function (Blueprint $table) {
            $table->string('factura_mode', 10)->default('auto')->change();
        });
        Schema::table('document_series', function (Blueprint $table) {
            $table->dropColumn('electronic');
        });
    }
};
