<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing → Contenido con IA (M1): kit de marca, biblioteca de contenido (copys y
 * flyers) y registro de cada llamada a un proveedor de IA (créditos y costo real).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_kits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tone', 30)->default('cercano');          // cercano | profesional | divertido | premium
            $table->string('slogan', 120)->nullable();
            $table->string('audience', 200)->nullable();             // a quién le vende
            $table->json('banned_words')->nullable();                // palabras/promesas que nunca deben aparecer
            $table->string('primary_color', 7)->nullable();          // #RRGGBB
            $table->string('secondary_color', 7)->nullable();
            $table->boolean('show_whatsapp')->default(true);         // número en los flyers
            $table->timestamps();
        });

        Schema::create('marketing_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);                              // copy | flyer
            $table->string('template', 40)->nullable();              // plantilla del flyer / objetivo del copy
            $table->string('format', 20)->nullable();                // square | portrait | story
            $table->string('title')->nullable();
            $table->json('content')->nullable();                     // variantes de copy
            $table->string('file_path')->nullable();                 // PNG del flyer (disco public)
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 30);                           // copy, (luego) image, chat, classify
            $table->string('provider', 20);                          // anthropic, …
            $table->string('model', 60);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedSmallInteger('credits')->default(0);     // lo que se descuenta del plan
            $table->string('status', 20);                            // ok | error | refused
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('marketing_assets');
        Schema::dropIfExists('brand_kits');
    }
};
