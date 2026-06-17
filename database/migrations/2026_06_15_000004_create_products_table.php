<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Información básica
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('short_description')->nullable();
            $table->string('sku')->nullable();               // Código de inventario

            // Precios
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();  // Precio tachado
            $table->decimal('cost_price', 10, 2)->nullable();      // Costo (solo visible al dueño)

            // Inventario
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('track_stock')->default(false);        // Si se gestiona inventario
            $table->boolean('allow_backorder')->default(false);
            $table->unsignedInteger('low_stock_alert')->default(5);
            $table->string('unit')->default('unidad');             // unidad, kg, litro, etc.

            // Imágenes
            $table->string('image_path')->nullable();
            $table->json('gallery_images')->nullable();            // Array de rutas de imágenes

            // Estado
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_digital')->default(false);         // Producto digital (descargable)

            // Peso y dimensiones (para envíos)
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('dimensions')->nullable();              // "10x5x3 cm"

            // Tags y SEO
            $table->json('tags')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            // Métricas
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('sold_count')->default(0);

            // Orden
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'is_active', 'is_featured']);
            $table->index(['store_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
