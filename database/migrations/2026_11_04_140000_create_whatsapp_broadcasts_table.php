<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP Fase 3c — WhatsApp broadcasts: one approved template to a segment of customers, with
 * the cost estimated before sending, a monthly budget cap per store, Meta's 24 h messaging
 * limit respected and sends spread over time. Meta bills per delivered message: the status
 * webhook's pricing block fills whatsapp_messages.cost. See tribio_brain WhatsApp-Cloud-Inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            // Meta's limit of customers the business may write first to per moving 24 h (null = unlimited).
            $table->string('messaging_limit_tier', 30)->nullable()->after('quality_rating');
            $table->unsignedInteger('messaging_limit')->nullable()->after('messaging_limit_tier');
            $table->decimal('monthly_budget', 10, 2)->nullable()->after('messaging_limit');
            // Price per delivered message by category, in PEN (null = Tribio's reference rates).
            $table->json('rates')->nullable()->after('monthly_budget');
        });

        Schema::create('whatsapp_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('template_name', 512);
            $table->string('category', 20);
            $table->json('audience');
            // Template variable → ['source' => 'nombre'|…|'fixed', 'text' => …].
            $table->json('variables')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->decimal('rate', 8, 4);
            $table->decimal('estimated_cost', 10, 2);
            $table->string('error', 255)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('whatsapp_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('whatsapp_broadcasts')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->string('status', 20);
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['broadcast_id', 'contact_id']);
            $table->index(['contact_id', 'created_at']);
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('broadcast_id')->nullable()->after('conversation_id')->constrained('whatsapp_broadcasts')->nullOnDelete();
            // From the status webhook: Meta's billing category and what it costs (0 when free).
            $table->string('billing_category', 40)->nullable()->after('error');
            $table->decimal('cost', 10, 4)->nullable()->after('billing_category');
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'created_at']);
            $table->dropConstrainedForeignId('broadcast_id');
            $table->dropColumn(['billing_category', 'cost']);
        });
        Schema::dropIfExists('whatsapp_broadcast_recipients');
        Schema::dropIfExists('whatsapp_broadcasts');
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->dropColumn(['messaging_limit_tier', 'messaging_limit', 'monthly_budget', 'rates']);
        });
    }
};
