<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cómo aparece una tienda en Google Shopping (fila provider = 'google'):
 *   'tribio' → Tribio la publica en su propia cuenta de Merchant Center (marketplace),
 *              automático y sin dominio propio;
 *   'own'    → el dueño usa su propia cuenta de Merchant Center (requiere dominio propio);
 *   null     → no aparece en Google Shopping.
 * El código tolera que esta migración aún no haya corrido (MarketingSchema::googleReady()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_marketing_integrations', function (Blueprint $table) {
            $table->string('shopping_mode', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_marketing_integrations', function (Blueprint $table) {
            $table->dropColumn('shopping_mode');
        });
    }
};
