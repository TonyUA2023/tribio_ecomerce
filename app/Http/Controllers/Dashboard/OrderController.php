<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RecordManualPaymentRequest;
use App\Http\Requests\Dashboard\UpdateProductionStageRequest;
use App\Mail\OrderProductionUpdated;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    private function getStore() { return Auth::user()->currentStore(); }

    public function index(Request $request)
    {
        $store = $this->getStore();

        $search = trim((string) $request->search);

        $orders = $store->orders()
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->when($request->status === 'por_confirmar', fn($q) => $q->where('payment_confirmation', 'awaiting'))
            ->when($request->status && $request->status !== 'por_confirmar', fn($q) => $q->where('status', $request->status))
            ->when($search !== '', fn($q) => $q->where(fn($s) =>
                $s->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_document_number', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // One grouped query instead of one COUNT per status.
        $counts = $store->orders()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $statusCounts = collect(['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])
            ->mapWithKeys(fn($s) => [$s => (int) ($counts[$s] ?? 0)])
            ->all();
        $totalCount = (int) $counts->sum();
        $statusCounts['por_confirmar'] = $store->orders()->where('payment_confirmation', 'awaiting')->count();

        return view('dashboard.orders.index', compact('store', 'orders', 'statusCounts', 'totalCount', 'search'));
    }

    public function show(Order $order)
    {
        $store = $this->getStore();
        abort_if($order->store_id !== $store->id, 403);
        $order->load(['items.attachments', 'payments' => fn($q) => $q->orderBy('paid_at')]);

        return view('dashboard.orders.show', compact('store', 'order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $store = $this->getStore();
        abort_if($order->store_id !== $store->id, 403);

        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
        ]);

        $order->update(['status' => $request->status]);

        return back()->with('success', "Pedido #{$order->order_number} actualizado a: {$order->status_label}");
    }

    public function whatsappRedirect(Order $order)
    {
        $store = $this->getStore();
        abort_if($order->store_id !== $store->id, 403);

        $order->load('items');
        $url = $store->whatsapp_link . '?text=' . $order->buildWhatsappMessage();

        if (!$order->whatsapp_sent) {
            $order->update([
                'whatsapp_sent'    => true,
                'whatsapp_sent_at' => now(),
            ]);
        }

        return redirect($url);
    }
    /** Moves a made-to-order order through the workshop and (by default) tells the buyer. */
    public function updateProduction(UpdateProductionStageRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->isMadeToOrder(), 404);
        $order->update(['production_stage' => $request->validated('production_stage')]);

        if ($request->boolean('notify_customer') && $order->customer_email) {
            try {
                Mail::to($order->customer_email)->queue(new OrderProductionUpdated($order->fresh(), $request->validated('message')));
            } catch (\Throwable $e) {
                Log::error('No se pudo encolar el aviso de etapa de producción.', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('success', "Pedido #{$order->order_number}: {$order->production_stage_label}.");
    }

    /** Money received outside a gateway (Yape, transferencia, efectivo…), e.g. the balance. */
    public function recordPayment(RecordManualPaymentRequest $request, Order $order): RedirectResponse
    {
        $amount = (float) $request->validated('amount');
        $kind = (float) $order->amount_paid > 0
            ? OrderPayment::KIND_BALANCE
            : ($amount + 0.005 >= (float) $order->total ? OrderPayment::KIND_FULL : OrderPayment::KIND_DEPOSIT);
        $note = RecordManualPaymentRequest::METHODS[$request->validated('method')]
            . ($request->validated('note') ? ' · ' . $request->validated('note') : '');

        $order->recordPayment($amount, $kind, 'manual', null, $request->user()->id, $note);
        $order->refresh();

        return back()->with('success', (float) $order->balance_due > 0
            ? 'Pago registrado. Saldo pendiente: ' . $order->money($order->balance_due) . '.'
            : 'Pago registrado. El pedido quedó pagado por completo.');
    }

    /** Seller verified the Yape/Plin/transfer payment: credit the ledger and move the order on. */
    public function confirmPayment(Request $request, Order $order): RedirectResponse
    {
        $store = $this->getStore();
        abort_if($order->store_id !== $store->id, 403);
        abort_unless($order->payment_confirmation === 'awaiting', 422, 'Este pedido no está esperando confirmación de pago.');

        DB::transaction(function () use ($order, $request) {
            $due = (float) $order->balance_due > 0 ? (float) $order->balance_due : (float) $order->total;
            $kind = (float) $order->amount_paid > 0 ? OrderPayment::KIND_BALANCE : OrderPayment::KIND_FULL;
            $note = \App\Services\Payments\ManualPayments::label($order->payment_method) . ' · confirmado por el vendedor'
                . ($order->payment_reference ? " · Op. {$order->payment_reference}" : '');
            $order->recordPayment($due, $kind, 'manual', null, $request->user()->id, $note);
            $order->refresh();
            $order->update([
                'payment_confirmation' => 'confirmed',
                'payment_confirmed_at' => now(),
                'payment_confirmed_by' => $request->user()->id,
                'status' => $order->status === 'pending' ? 'confirmed' : $order->status,
            ]);
        });

        $this->notifyBuyer($order->fresh(), true, null);

        return back()->with('success', "Pago confirmado. El pedido #{$order->order_number} pasó a Confirmado.");
    }

    /** Seller did not receive the money: keep the order open but mark the payment as not found. */
    public function rejectPayment(Request $request, Order $order): RedirectResponse
    {
        $store = $this->getStore();
        abort_if($order->store_id !== $store->id, 403);
        abort_unless($order->payment_confirmation === 'awaiting', 422, 'Este pedido no está esperando confirmación de pago.');
        $data = $request->validate(['note' => 'nullable|string|max:300']);

        $order->update([
            'payment_confirmation' => 'rejected',
            'payment_confirmed_at' => now(),
            'payment_confirmed_by' => $request->user()->id,
            'payment_status' => 'failed',
            'internal_notes' => trim(($order->internal_notes ? $order->internal_notes . "\n" : '') . 'Pago no encontrado' . (!empty($data['note']) ? ': ' . $data['note'] : '')),
        ]);

        $this->notifyBuyer($order->fresh(), false, $data['note'] ?? null);

        return back()->with('success', "Marcaste el pago del pedido #{$order->order_number} como no recibido.");
    }

    private function notifyBuyer(Order $order, bool $confirmed, ?string $note): void
    {
        if (!$order->customer_email) {
            return;
        }
        try {
            \Illuminate\Support\Facades\Mail::to($order->customer_email)->queue(new \App\Mail\OrderPaymentConfirmed($order, $confirmed, $note));
        } catch (\Throwable $e) {
            Log::error('No se pudo encolar el aviso de pago confirmado.', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
