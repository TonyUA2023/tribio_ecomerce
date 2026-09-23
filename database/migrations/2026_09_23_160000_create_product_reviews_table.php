<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Verified-purchase product reviews. Every row was unlocked by a delivered order
        // item (order_item_id), so there is no "verified yes/no" flag — all are, by design.
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // Reviews are public content of the store: they outlive a hard-deleted product,
            // account or order item (same reasoning as order_items.product_id), so these
            // three references null out instead of cascading.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot of how the buyer is shown publicly ("María C."), frozen at write time so
            // a later profile edit or account deletion never changes what's on the storefront.
            $table->string('reviewer_name', 80);
            $table->unsignedTinyInteger('rating'); // 1..5, enforced by ProductReviewService
            $table->text('comment')->nullable();
            // published | hidden — reviews publish immediately; the owner can hide afterwards.
            $table->string('status', 20)->default('published');
            $table->text('store_reply')->nullable();
            $table->timestamp('store_replied_at')->nullable();
            $table->timestamp('edited_at')->nullable(); // set when the buyer edits their own review
            $table->timestamps();

            // One review per buyer per product — buying again edits it instead of adding another.
            $table->unique(['user_id', 'product_id'], 'product_reviews_user_product_unique');
            $table->index(['product_id', 'status', 'created_at'], 'product_reviews_product_listing_index');
            $table->index(['store_id', 'status', 'created_at'], 'product_reviews_store_listing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
