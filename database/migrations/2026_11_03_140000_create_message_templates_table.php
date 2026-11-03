<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 2d — plantillas de WhatsApp de la tienda. Las de Tribio (recompra, cobranza,
 * gracias…) viven en código (MessageTemplates::BUILTIN); una fila con la misma `key` es
 * la versión editada por el dueño. Las filas sin `key` son plantillas propias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40)->nullable();          // reorder, payment… (null = propia)
            $table->string('name', 60);
            $table->text('body');
            $table->boolean('is_promo')->default(false);    // solo para clientes con consentimiento
            $table->timestamps();

            $table->unique(['store_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
