<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación electrónica SUNAT y sedes.
 *
 * - branches: los establecimientos del negocio (sede principal "0000", anexos "0001"…) con su dirección
 *   fiscal y ubigeo, que el XML de cada comprobante necesita. El stock sigue siendo uno por negocio.
 * - registers.branch_id y orders.branch_id: en qué sede está cada caja y dónde se emitió cada venta.
 * - document_series: ahora una serie puede ser de todo el negocio, de una sede o de una sola caja.
 * - sunat_settings: una fila por negocio con el ambiente, qué se envía, la clave SOL y el certificado
 *   digital (estos tres, cifrados con la APP_KEY).
 * - electronic_documents: el XML firmado, la constancia (CDR) y el estado de cada comprobante enviado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('sunat_code', 4)->default('0000');   // código de establecimiento anexo (0000 = domicilio fiscal)
            $table->string('address', 200)->nullable();
            $table->string('ubigeo', 6)->nullable();
            $table->string('department', 60)->nullable();
            $table->string('province', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'sunat_code']);
        });

        Schema::table('registers', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('store_id')->constrained('branches')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('register_session_id')->constrained('branches')->nullOnDelete();
        });

        // New unique first: on MySQL the foreign key on store_id needs an index that starts with it while the old one is dropped.
        Schema::table('document_series', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('store_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('register_id')->nullable()->after('branch_id')->constrained('registers')->cascadeOnDelete();
            // A series code is given once per document type in a business (B001 cannot be two sequences).
            $table->unique(['store_id', 'type', 'series']);
        });
        Schema::table('document_series', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'type']);
        });

        Schema::create('sunat_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('environment', 10)->default('beta');            // beta | production
            $table->string('factura_mode', 10)->default('auto');            // auto | manual
            $table->string('boleta_mode', 10)->default('manual');           // manual (se elige en cada venta) | auto | never
            $table->text('sol_user')->nullable();                           // usuario secundario (cifrado)
            $table->text('sol_password')->nullable();                       // clave SOL (cifrada)
            $table->longText('certificate_pem')->nullable();                // certificado + llave privada (cifrado)
            $table->string('certificate_subject', 255)->nullable();
            $table->timestamp('certificate_valid_to')->nullable();
            $table->string('endpoint', 255)->nullable();                    // avanzado: OSE/PSE con el mismo servicio billService
            $table->boolean('email_company')->default(true);
            $table->string('email_to', 160)->nullable();
            $table->boolean('email_customer')->default(false);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('electronic_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 12);                                     // boleta | factura
            $table->string('series', 4);
            $table->unsignedInteger('number');
            $table->string('environment', 10)->default('beta');
            $table->string('status', 12)->default('pending');               // pending | accepted | observed | rejected | error
            $table->string('sunat_code', 12)->nullable();
            $table->text('sunat_message')->nullable();
            $table->string('hash', 80)->nullable();
            $table->longText('xml')->nullable();                            // XML firmado
            $table->longText('cdr_zip')->nullable();                        // constancia de recepción (base64)
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electronic_documents');
        Schema::dropIfExists('sunat_settings');
        \Illuminate\Support\Facades\DB::table('document_series')->where(fn ($q) => $q->whereNotNull('branch_id')->orWhereNotNull('register_id'))->delete();
        Schema::table('document_series', function (Blueprint $table) {
            $table->unique(['store_id', 'type']);
        });
        Schema::table('document_series', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'type', 'series']);
            $table->dropConstrainedForeignId('register_id');
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::table('registers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::dropIfExists('branches');
    }
};
