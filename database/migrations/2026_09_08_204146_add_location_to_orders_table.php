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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_country', 2)->default('PE')->after('customer_address');
            $table->string('customer_state')->nullable()->after('customer_country');
            $table->string('customer_zipcode')->nullable()->after('customer_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_country', 'customer_state', 'customer_zipcode']);
        });
    }
};
