<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 3b — Meta-approved WhatsApp templates of the store's number: the only way to
 * write first or after the 24 h window. Synced from Meta (also the ones created in Meta's
 * own screens) and created from Tribio's templates. See tribio_brain WhatsApp-Cloud-Inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->string('meta_id', 64)->nullable();
            $table->string('name', 512);
            $table->string('language', 10);
            $table->string('category', 20);
            $table->string('status', 20);
            $table->text('body');
            $table->json('components')->nullable();
            // Tribio placeholder behind each variable ({{1}} → "nombre"); null for templates made in Meta.
            $table->json('params')->nullable();
            $table->string('source_key', 40)->nullable();
            $table->string('rejected_reason', 255)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'name', 'language']);
            $table->index('meta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
