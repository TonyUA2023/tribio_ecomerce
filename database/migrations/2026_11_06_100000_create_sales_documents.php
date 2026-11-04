<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobantes de venta (Perú): nota de venta, boleta de venta y factura. Cada negocio tiene
 * una serie por tipo (NV01, B001, F001) con su correlativo; cada venta de caja recibe el
 * siguiente número de la serie de su tipo. La numeración y los datos del emisor (RUC, razón
 * social) quedan en la base; el envío electrónico a SUNAT (OSE/PSE) no forma parte de esta migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('ruc', 11)->nullable();          // RUC del negocio (emisor)
            $table->string('legal_name', 160)->nullable();  // razón social
        });

        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12);                     // nota_venta | boleta | factura
            $table->string('series', 4);                    // NV01 | B001 | F001
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'type']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('document_type', 12)->nullable()->after('order_number');
            $table->string('document_series', 4)->nullable()->after('document_type');
            $table->unsignedInteger('document_number')->nullable()->after('document_series');

            // A number of a series is never given twice (NULLs — sales before this — do not collide).
            $table->unique(['store_id', 'document_type', 'document_series', 'document_number'], 'orders_document_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_document_unique');
            $table->dropColumn(['document_type', 'document_series', 'document_number']);
        });
        Schema::dropIfExists('document_series');
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['ruc', 'legal_name']);
        });
    }
};
