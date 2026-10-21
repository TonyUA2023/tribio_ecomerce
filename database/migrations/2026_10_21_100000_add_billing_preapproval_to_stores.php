<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Mercado Pago Suscripciones id of the store's Tribio plan (the culqi_* columns
            // stay for stores that already paid through Culqi until their plan expires).
            $table->string('billing_preapproval_id', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['billing_preapproval_id']);
            $table->dropColumn('billing_preapproval_id');
        });
    }
};
