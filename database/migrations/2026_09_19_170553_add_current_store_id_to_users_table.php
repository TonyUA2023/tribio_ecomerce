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
        Schema::table('users', function (Blueprint $table) {
            // Tribio Pass: an account can now own several stores (User::stores(), was
            // hasOne). This remembers which one the dashboard should open by default —
            // persisted per account (not session) so it survives across devices/logins.
            // nullOnDelete: deleting the current store just falls back to another one,
            // resolved by User::currentStore() — never a hard failure.
            $table->foreignId('current_store_id')->nullable()->after('role')
                ->constrained('stores')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_store_id');
        });
    }
};
