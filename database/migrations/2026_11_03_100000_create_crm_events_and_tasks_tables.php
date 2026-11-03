<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 2 (CRM) — ficha del cliente: etiquetas, cumpleaños, tipo de relación y etapa
 * (calculada por reglas, ver LifecycleClassifier); su historia (notas, llamadas, WhatsApp,
 * cambios de etapa) y sus seguimientos (tareas con fecha).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('notes');
            $table->date('birthday')->nullable()->after('city');
            $table->string('relationship', 20)->nullable()->after('source');     // consumidor | mayorista | distribuidor
            $table->string('lifecycle_stage', 20)->nullable()->after('relationship');
            $table->timestamp('stage_changed_at')->nullable()->after('lifecycle_stage');

            $table->index(['store_id', 'lifecycle_stage']);
        });

        Schema::create('contact_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);                       // note | call | whatsapp | visit | stage | task
            $table->text('body')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['contact_id', 'occurred_at']);
        });

        Schema::create('contact_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();   // recompra (Fase 2c)
            $table->string('kind', 20)->default('follow_up');  // follow_up | reorder | payment | other
            $table->string('title');
            $table->timestamp('due_at');
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'done_at', 'due_at']);
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tasks');
        Schema::dropIfExists('contact_events');
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'lifecycle_stage']);
            $table->dropColumn(['tags', 'birthday', 'relationship', 'lifecycle_stage', 'stage_changed_at']);
        });
    }
};
