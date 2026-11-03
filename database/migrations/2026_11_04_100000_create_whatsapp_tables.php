<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 3a — WhatsApp Cloud API: one connected number per store, the raw webhook
 * events (idempotent, processed on the queue), and the inbox (one conversation per
 * customer, its messages with delivery status). See tribio_brain WhatsApp-Cloud-Inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('waba_id', 32);
            $table->string('phone_number_id', 32)->unique();
            $table->string('display_phone_number', 32)->nullable();
            $table->string('verified_name', 120)->nullable();
            $table->string('quality_rating', 16)->nullable();
            $table->text('access_token');
            $table->text('app_secret');
            $table->string('verify_token', 64);
            $table->string('webhook_key', 48)->unique();
            $table->string('status', 16)->default('pending');
            $table->string('last_error', 255)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->nullable()->constrained('whatsapp_connections')->nullOnDelete();
            $table->string('payload_hash', 64)->unique();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('processed_at');
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            // Business-scoped user ID (since 2026 the phone may be omitted for username users).
            $table->string('wa_user_id', 160)->nullable();
            $table->string('wa_id', 20)->nullable();
            $table->string('profile_name', 120)->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 160)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['connection_id', 'wa_user_id']);
            $table->unique(['connection_id', 'wa_id']);
            $table->index(['store_id', 'last_message_at']);
            $table->index('contact_id');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('direction', 3);
            $table->string('wamid', 191)->nullable()->unique();
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 12);
            $table->string('error', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('wa_timestamp')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_connections');
    }
};
