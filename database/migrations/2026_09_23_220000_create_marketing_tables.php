<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Marketing, etapa 1 (conexión manual con Meta): ajustes por tienda, registro de
 * eventos enviados por Conversions API, de dónde vino cada venta y dos campos de producto
 * para el catálogo de anuncios. Todo el código tolera que esta migración aún no haya
 * corrido (App\Services\Marketing\MarketingSchema): el deploy no ejecuta migraciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_marketing_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20); // 'meta'
            $table->boolean('is_active')->default(true);
            $table->string('pixel_id', 32)->nullable();
            $table->text('capi_token')->nullable(); // cifrado (cast 'encrypted')
            $table->string('test_event_code', 40)->nullable();
            $table->string('domain_verification', 100)->nullable();
            $table->string('default_condition', 20)->default('new');
            // Etapa 2 ("Conectar con Facebook"): hoy siempre 'manual' y vacíos.
            $table->string('connection_type', 20)->default('manual');
            $table->string('business_id', 32)->nullable();
            $table->string('catalog_id', 32)->nullable();
            $table->string('ad_account_id', 32)->nullable();
            $table->string('page_id', 32)->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'provider']);
        });

        Schema::create('marketing_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('event_name', 40);
            $table->string('event_id', 100);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending | sent | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('is_test')->default(false);
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            // La misma compra nunca se envía dos veces, llegue por donde llegue.
            $table->unique(['store_id', 'provider', 'event_name', 'event_id'], 'marketing_event_logs_unique_event');
            $table->index(['store_id', 'created_at']);
        });

        Schema::create('order_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20)->nullable(); // meta | other
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('fbclid', 255)->nullable();
            $table->string('landing_path', 255)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'channel', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('condition', 20)->nullable(); // null = la condición por defecto de la tienda
            $table->boolean('exclude_from_ads')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['condition', 'exclude_from_ads']);
        });
        Schema::dropIfExists('order_attributions');
        Schema::dropIfExists('marketing_event_logs');
        Schema::dropIfExists('store_marketing_integrations');
    }
};
