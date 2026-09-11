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
        // Modificar columna role de ENUM a VARCHAR(50) para permitir 'cliente'
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'cliente'");

        // Actualizar usuarios existentes que puedan tener 'customer' a 'cliente'
        \Illuminate\Support\Facades\DB::table('users')->where('role', 'customer')->update(['role' => 'cliente']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'store_owner', 'customer') NOT NULL DEFAULT 'store_owner'");
    }
};
