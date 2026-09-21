<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slides 2 and 3 of the minimal-light hero carousel were hardcoded Unsplash
     * stock photos shared by every store. These two columns let a store owner
     * replace them the same way `cover_path` already covers slide 1.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('hero_image_2_path')->nullable()->after('cover_path');
            $table->string('hero_image_3_path')->nullable()->after('hero_image_2_path');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['hero_image_2_path', 'hero_image_3_path']);
        });
    }
};
