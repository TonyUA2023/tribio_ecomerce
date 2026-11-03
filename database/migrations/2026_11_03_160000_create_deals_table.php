<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 2e — oportunidades: negocios en curso con un cliente o prospecto (un pedido
 * grande, un distribuidor que evalúa unirse) que avanzan por etapas hasta ganarse
 * (opcionalmente con su pedido real) o perderse (con el motivo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->string('stage', 20)->default('nuevo');       // nuevo | contactado | cotizado | negociando | ganada | perdida
            $table->decimal('amount', 12, 2)->nullable();          // valor estimado (S/)
            $table->date('expected_close_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('lost_reason', 40)->nullable();
            $table->string('lost_note', 255)->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();   // pedido con el que se ganó
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'stage']);
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
