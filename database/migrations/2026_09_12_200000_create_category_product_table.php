<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('category_product')) {
            Schema::create('category_product', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['category_id', 'product_id']);
            });
        }

        // Backfill: Migrar las relaciones existentes desde products.category_id a category_product
        try {
            $existingProducts = DB::table('products')
                ->whereNotNull('category_id')
                ->get(['id', 'category_id']);

            $now = now();
            $records = [];
            foreach ($existingProducts as $p) {
                $categoryExists = DB::table('categories')->where('id', $p->category_id)->exists();
                if ($categoryExists) {
                    $records[] = [
                        'product_id'  => $p->id,
                        'category_id' => $p->category_id,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                }
            }

            if (!empty($records)) {
                DB::table('category_product')->insertOrIgnore($records);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Error during category_product backfill: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};
