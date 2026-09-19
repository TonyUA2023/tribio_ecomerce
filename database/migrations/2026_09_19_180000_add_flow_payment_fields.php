<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('flow_enabled')->default(false);
            $table->text('flow_api_key')->nullable();
            $table->text('flow_secret_key')->nullable();
            $table->string('flow_mode', 10)->default('sandbox');
            $table->string('flow_currency', 3)->default('PEN');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('flow_order_id')->nullable()->index();
            $table->string('flow_token')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['flow_order_id', 'flow_token']));
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn(['flow_enabled', 'flow_api_key', 'flow_secret_key', 'flow_mode', 'flow_currency']));
    }
};
