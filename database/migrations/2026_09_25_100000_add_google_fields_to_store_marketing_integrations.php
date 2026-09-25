<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Marketing → Google: la conexión de Google es otra fila de
 * store_marketing_integrations (provider = 'google'). Reutiliza is_active,
 * domain_verification (código de google-site-verification) y default_condition;
 * aquí solo se agregan las etiquetas de medición. El código tolera que esta
 * migración aún no haya corrido (MarketingSchema::googleReady()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_marketing_integrations', function (Blueprint $table) {
            $table->string('measurement_id', 32)->nullable();       // Google Analytics 4: G-XXXXXXXXXX
            $table->string('ads_conversion_id', 32)->nullable();    // Google Ads: AW-123456789
            $table->string('ads_conversion_label', 64)->nullable(); // Google Ads: etiqueta de la conversión "Compra"
        });
    }

    public function down(): void
    {
        Schema::table('store_marketing_integrations', function (Blueprint $table) {
            $table->dropColumn(['measurement_id', 'ads_conversion_id', 'ads_conversion_label']);
        });
    }
};
