<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Identidad
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('tagline')->nullable();                   // Frase corta / eslogan
            $table->enum('category', [
                'moda', 'calzado', 'tecnologia', 'alimentos',
                'joyeria', 'hogar', 'deporte', 'salud', 'servicios', 'otros'
            ])->default('otros');

            // Assets visuales
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('favicon_path')->nullable();

            // Plantilla & personalización
            $table->string('template_name')->default('elegant-dark');  // elegant-dark | minimal-light | vibrant-fresh
            $table->string('accent_color')->default('#8B5CF6');
            $table->string('secondary_color')->default('#F59E0B');
            $table->string('text_color')->default('#FFFFFF');
            $table->string('bg_color')->default('#0F0F1A');
            $table->boolean('hero_carousel')->default(false);           // Hero con carrusel o imagen fija
            $table->string('hero_style')->default('full');              // full | split | minimal
            $table->json('custom_css_vars')->nullable();                // Variables CSS adicionales

            // Contacto y redes
            $table->string('whatsapp_phone')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('PE');
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('custom_domain')->nullable()->unique();       // Dominio propio (futuro)

            // Estado y suscripción
            $table->enum('status', ['draft', 'active', 'suspended', 'cancelled'])->default('draft');
            $table->enum('plan', ['basic', 'professional', 'enterprise'])->default('basic');
            $table->timestamp('plan_expires_at')->nullable();
            $table->boolean('is_featured')->default(false);             // Tienda destacada en portada Tribio

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            // Métricas (desnormalizadas para rendimiento)
            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('total_orders')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
