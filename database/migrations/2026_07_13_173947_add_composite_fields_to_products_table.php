<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('category_id')->constrained('products')->nullOnDelete();
            $table->boolean('is_composite')->default(false)->after('unit');
            $table->enum('composite_type', ['assembly', 'bucket', 'unit'])->default('unit')->after('is_composite');
            $table->boolean('is_sold')->default(false)->after('is_digital');
            $table->timestamp('sold_at')->nullable()->after('is_sold');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'is_composite', 'composite_type', 'is_sold', 'sold_at']);
        });
    }
};
