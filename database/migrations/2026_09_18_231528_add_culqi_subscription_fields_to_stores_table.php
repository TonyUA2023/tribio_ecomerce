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
        Schema::table('stores', function (Blueprint $table) {
            // Platform-level Culqi subscription billing (Tribio charging the store owner).
            // Distinct from `gateway_*`/`mp_*` fields, which are the store's OWN checkout gateway
            // for charging its customers.
            $table->string('culqi_customer_id')->nullable();
            $table->string('culqi_card_id')->nullable();
            $table->string('culqi_subscription_id')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['culqi_customer_id', 'culqi_card_id', 'culqi_subscription_id']);
        });
    }
};
