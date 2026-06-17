<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro de visitas y métricas de la tienda
        Schema::create('store_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('unique_visitors')->default(0);
            $table->unsignedBigInteger('product_views')->default(0);
            $table->unsignedBigInteger('cart_adds')->default(0);
            $table->unsignedBigInteger('checkouts')->default(0);
            $table->unsignedBigInteger('orders_count')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'date']);
            $table->index(['store_id', 'date']);
        });

        // Logs de actividad (super-admin puede ver todo)
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');                           // 'product.created', 'order.status_changed', etc.
            $table->string('subject_type')->nullable();         // Morphable type
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload')->nullable();                // Datos adicionales
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['store_id', 'created_at']);
            $table->index('action');
        });

        // Planes de suscripción disponibles
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 8, 2);
            $table->decimal('price_yearly', 8, 2)->nullable();
            $table->json('features');                           // Lista de características
            $table->unsignedInteger('max_products')->default(10);
            $table->unsignedInteger('max_gallery_items')->default(20);
            $table->boolean('custom_domain')->default(false);
            $table->boolean('analytics')->default(false);
            $table->boolean('inventory_module')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('store_analytics');
        Schema::dropIfExists('subscription_plans');
    }
};
