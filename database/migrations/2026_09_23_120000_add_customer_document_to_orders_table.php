<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Couriers (Olva, Shalom, etc.) ask for the recipient's identity document, so the
     * store owner needs it on every order. Nullable: orders placed before this existed
     * (and API clients that don't send it yet) simply don't have one.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_document_type', 10)->nullable()->after('customer_email');   // DNI | CE | PAS | RUC
            $table->string('customer_document_number', 20)->nullable()->after('customer_document_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_document_type', 'customer_document_number']);
        });
    }
};
