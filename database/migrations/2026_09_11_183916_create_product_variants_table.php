<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar configuración de variantes a tabla products si no existen
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'has_variants')) {
                $table->boolean('has_variants')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('products', 'variant_options')) {
                $table->json('variant_options')->nullable()->after('has_variants');
            }
        });

        // 2. Crear tabla de variantes
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->decimal('price_usd', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->json('attributes')->nullable(); // Ej: {"Color": "Negro", "Talla": "41"}
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        // 3. Agregar referencia de variante en order_items
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'variant_id')) {
                $table->foreignId('variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            }
            if (!Schema::hasColumn('order_items', 'variant_title')) {
                $table->string('variant_title')->nullable()->after('product_name');
            }
            if (!Schema::hasColumn('order_items', 'variant_attributes')) {
                $table->json('variant_attributes')->nullable()->after('variant_title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'variant_id')) {
                $table->dropForeign(['variant_id']);
                $table->dropColumn(['variant_id', 'variant_title', 'variant_attributes']);
            }
        });

        Schema::dropIfExists('product_variants');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'has_variants')) {
                $table->dropColumn(['has_variants', 'variant_options']);
            }
        });
    }
};
