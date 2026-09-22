<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Per-template customizations from the dashboard "Plantillas" module, namespaced
            // by template key ({"soft-market": {"hero": {...}}}) so switching designs and
            // coming back never loses what the owner already configured.
            $table->json('template_settings')->nullable();
            // Bespoke/protected storefronts: the Plantillas module can neither switch nor
            // customize them. Deliberately NOT mass-assignable on the model.
            $table->boolean('template_locked')->default(false);
        });

        // minimal-light is Maetek's bespoke build, frozen as-is (its reusable twin is
        // soft-market). Every store on it at migration time is protected.
        DB::table('stores')->where('template_name', 'minimal-light')->update(['template_locked' => true]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['template_settings', 'template_locked']);
        });
    }
};
