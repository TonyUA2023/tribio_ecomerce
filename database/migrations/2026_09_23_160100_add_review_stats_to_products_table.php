<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Denormalized review counters (same idea as stores.total_views / total_orders):
        // the catalog shows stars on up to dozens of cards per page, so the average must be
        // a column read, not a per-card aggregate. Kept in sync by ProductReviewObserver.
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['average_rating', 'reviews_count']);
        });
    }
};
