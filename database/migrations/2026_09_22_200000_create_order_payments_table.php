<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Every confirmed movement of money on an order (full payment, deposit, balance,
        // manual Yape/transfer entry). orders.amount_paid is always the sum of the `paid`
        // rows, never written directly — see Order::recordPayment().
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                 // full | deposit | balance
            $table->decimal('amount', 12, 2);           // in the order's own currency
            $table->string('currency', 3);
            $table->string('gateway', 30);              // mercadopago | paypal | flow | manual | …
            $table->string('gateway_ref')->nullable();  // gateway payment/order id when there is one
            $table->string('status', 20)->default('paid'); // paid | refunded
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            // A gateway reference is credited at most once, even if a webhook is replayed.
            $table->unique(['gateway', 'gateway_ref']);
            $table->index(['order_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->default(0)->after('total');
            $table->decimal('balance_due', 12, 2)->default(0)->after('amount_paid');
        });

        // Backfill: orders settled before the ledger existed get one `full` row so that
        // amount_paid always equals the ledger sum. Only payment_status is trusted here —
        // the gateway ids of old orders live in free-text internal_notes.
        DB::table('orders')->orderBy('id')->each(function ($order) {
            $settledStatus = match ($order->payment_status) {
                'paid' => 'paid',
                'refunded' => 'refunded',
                default => null,
            };
            if ($settledStatus) {
                DB::table('order_payments')->insert([
                    'order_id' => $order->id, 'kind' => 'full', 'amount' => $order->total,
                    'currency' => $order->currency ?: 'PEN', 'gateway' => $order->payment_method ?: 'desconocido',
                    'gateway_ref' => null, 'status' => $settledStatus,
                    'paid_at' => $order->updated_at ?? $order->created_at ?? now(),
                    'notes' => 'Registrado al crear el libro de pagos (pago anterior).',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $amountPaid = $settledStatus === 'paid' ? (float) $order->total : 0.0;
            $closed = in_array($order->status, ['cancelled', 'refunded'], true)
                || in_array($order->payment_status, ['failed', 'refunded'], true);
            DB::table('orders')->where('id', $order->id)->update([
                'amount_paid' => $amountPaid,
                'balance_due' => $closed ? 0 : max(0, round((float) $order->total - $amountPaid, 2)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'balance_due']);
        });
        Schema::dropIfExists('order_payments');
    }
};
