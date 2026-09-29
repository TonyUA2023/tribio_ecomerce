<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stores.category was a MySQL ENUM of the original ten rubros, so the new "textileria"
 * rubro was rejected by the database ("Data truncated") even though the app accepted it.
 * The list now lives in config('tribio.business_categories') and is validated there
 * (App\Support\BusinessProfile), so the column is a plain string: adding a rubro is a
 * config edit, never another migration. Existing values are kept as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('category', 40)->default('otros')->change();
        });
    }

    public function down(): void
    {
        // Stores created in a new rubro fall back to "otros" before restoring the ENUM.
        $legacy = ['moda', 'calzado', 'tecnologia', 'alimentos', 'joyeria', 'hogar', 'deporte', 'salud', 'servicios', 'otros'];
        \Illuminate\Support\Facades\DB::table('stores')->whereNotIn('category', $legacy)->update(['category' => 'otros']);

        Schema::table('stores', function (Blueprint $table) use ($legacy) {
            $table->enum('category', $legacy)->default('otros')->change();
        });
    }
};
