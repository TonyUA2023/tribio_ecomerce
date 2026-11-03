<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 0 — contactos: una persona por tienda (identificada por teléfono, correo o
 * documento) a la que se enlazan sus pedidos. Base del CRM, la clasificación de clientes
 * y el punto de venta. Ver tribio_brain ERP-CRM-POS-Proposal / Contacts-CRM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();                // solo dígitos con código de país: 51987654321
            $table->string('email')->nullable();                    // en minúsculas
            $table->string('document_type', 20)->nullable();
            $table->string('document_number', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('source', 20)->default('store');         // store | whatsapp | manual | pos | import
            $table->text('notes')->nullable();
            $table->boolean('accepts_marketing')->default(false);   // consentimiento (Ley 29733), aún sin UI
            $table->timestamp('marketing_consent_at')->nullable();
            // Resumen de sus pedidos, recalculado por ContactResolver::refreshTotals().
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);      // pagado, solo pedidos en PEN
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'phone']);
            $table->index(['store_id', 'email']);
            $table->index(['store_id', 'document_number']);
            $table->index(['store_id', 'last_order_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });
        Schema::dropIfExists('contacts');
    }
};
