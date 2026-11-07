<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulos que el dueño enciende o apaga en Configuración → Módulos: la tienda virtual y el sistema POS
 * ya salen del tipo de cuenta (stores.mode); el CRM (Clientes, seguimientos, WhatsApp) es independiente.
 * Los negocios que ya existían lo conservan encendido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('crm_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('crm_enabled');
        });
    }
};
