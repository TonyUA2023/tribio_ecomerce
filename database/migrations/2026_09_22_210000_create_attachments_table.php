<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Files buyers and merchants exchange about a custom order (logos to embroider,
        // reference photos, design proofs). Stored on the private disk and only ever
        // served through signed URLs — never under /storage.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // Null until the upload is linked to what it belongs to (order item, quote…);
            // unlinked uploads are pruned by `attachments:prune`.
            $table->nullableMorphs('attachable');
            // Opaque handle the buyer's cart refers to before any order exists.
            $table->string('token', 64)->unique();
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::table('stores', function (Blueprint $table) {
            // Made-to-order commerce (custom work, deposits, quotes…) is opt-in per store.
            $table->boolean('made_to_order_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('made_to_order_enabled');
        });
        Schema::dropIfExists('attachments');
    }
};
