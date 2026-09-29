<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\RecordManualPaymentRequest;
use App\Http\Requests\Dashboard\UpdateProductionStageRequest;
use App\Mail\OrderProductionUpdated;
use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    private const STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

    public function index(Request $request)
    {
        $store = $request->user()->currentStore();

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $search = trim((string) $request->search);

        $orders = $store->orders()
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->when($request->status && $request->status !== 'all', fn ($q) => $q->where('status', $request->status))
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('order_number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('customer_phone', 'like', "%{$search}%")
                ->orWhere('customer_document_number', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15);

        $orders->getCollection()->transform(function ($order) {
            $order->status_label = $order->status_label;
            $order->status_color = $order->status_color;
            $order->payment_status_label = $order->payment_status_label;
            $order->currency_symbol = $order->currency_symbol;
            $order->is_made_to_order = $order->isMadeToOrder();
            $order->production_stage_label = $order->production_stage_label;
            $order->items_quantity = (int) ($order->items_sum_quantity ?? 0);

            return $order;
        });

        // One grouped query instead of one COUNT per status chip.
        $counts = $store->orders()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $statusCounts = collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all();

        return response()->json(array_merge($orders->toArray(), [
            'status_counts' => $statusCounts,
            'total_count'   => (int) $counts->sum(),
        ]));
    }

    public function show(Request $request, $id)
    {
        $store = $request->user()->currentStore();

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $order = $store->orders()->with(['items.attachments', 'payments' => fn ($q) => $q->orderBy('paid_at')])->find($id);

        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        return response()->json($this->detail($order));
    }

    public function updateStatus(Request $request, $id)
    {
        $store = $request->user()->currentStore();

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $order = $store->orders()->find($id);

        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        $request->validate([
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded',
        ]);

        $order->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Estado del pedido actualizado con éxito.',
            'order' => $order,
            'status_label' => $order->status_label,
            'status_color' => $order->status_color,
        ]);
    }

    /** Moves a made-to-order order through the workshop and (optionally) tells the buyer. */
    public function updateProduction(UpdateProductionStageRequest $request, Order $order): JsonResponse
    {
        abort_unless($order->isMadeToOrder(), 404, 'Este pedido no es por encargo.');
        $order->update(['production_stage' => $request->validated('production_stage')]);

        if ($request->boolean('notify_customer') && $order->customer_email) {
            try {
                Mail::to($order->customer_email)->queue(new OrderProductionUpdated($order->fresh(), $request->validated('message')));
            } catch (\Throwable $e) {
                Log::error('No se pudo encolar el aviso de etapa de producción.', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'message' => "Pedido #{$order->order_number}: {$order->production_stage_label}.",
            'production_stage' => $order->production_stage,
            'production_stage_label' => $order->production_stage_label,
        ]);
    }

    /** Money received outside a gateway (Yape, transferencia, efectivo…), e.g. the balance. */
    public function recordPayment(RecordManualPaymentRequest $request, Order $order): JsonResponse
    {
        $amount = (float) $request->validated('amount');
        $kind = (float) $order->amount_paid > 0
            ? OrderPayment::KIND_BALANCE
            : ($amount + 0.005 >= (float) $order->total ? OrderPayment::KIND_FULL : OrderPayment::KIND_DEPOSIT);
        $note = RecordManualPaymentRequest::METHODS[$request->validated('method')]
            . ($request->validated('note') ? ' · ' . $request->validated('note') : '');

        $order->recordPayment($amount, $kind, 'manual', null, $request->user()->id, $note);
        $order->refresh()->load(['items.attachments', 'payments' => fn ($q) => $q->orderBy('paid_at')]);

        return response()->json(array_merge($this->detail($order), [
            'message' => (float) $order->balance_due > 0
                ? 'Pago registrado. Saldo pendiente: ' . $order->money($order->balance_due) . '.'
                : 'Pago registrado. El pedido quedó pagado por completo.',
        ]));
    }

    /** Everything the order detail screen needs, on top of the raw order the app already read. */
    private function detail(Order $order): array
    {
        $store = $order->store;
        $whatsapp = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $store->whatsapp_phone ?? '') . '?text=' . $order->buildWhatsappMessage();

        return [
            'order' => $order,
            'status_label' => $order->status_label,
            'status_color' => $order->status_color,
            'payment_status_label' => $order->payment_status_label,
            'payment_method_label' => $order->payment_method_label,
            'currency_symbol' => $order->currency_symbol,
            'full_address' => $order->full_address,
            'shipping_label' => $order->shippingLabelText(),
            'whatsapp_url' => $whatsapp,
            'whatsapp_message' => urldecode($order->buildWhatsappMessage()),
            'is_made_to_order' => $order->isMadeToOrder(),
            'production_stage_label' => $order->production_stage_label,
            'production_stages' => Order::PRODUCTION_STAGES,
            'payment_methods' => RecordManualPaymentRequest::METHODS,
            'balance_payment_url' => $order->isMadeToOrder() && (float) $order->balance_due > 0 ? $order->balancePaymentUrl() : null,
            // Logos y diseños que el comprador subió en cada línea (enlaces firmados de corta vida).
            'attachments' => $order->items->flatMap(fn ($item) => $item->attachments->map(fn ($a) => [
                'id' => $a->id,
                'order_item_id' => $item->id,
                'name' => $a->original_name,
                'is_image' => $a->isImage(),
                'url' => $a->signedUrl(120),
                'download_url' => $a->signedUrl(60 * 24, true),
            ]))->values(),
            'payments' => $order->payments->map(fn (OrderPayment $p) => [
                'id' => $p->id,
                'kind' => $p->kind,
                'amount' => (float) $p->amount,
                'gateway' => $p->gateway,
                'status' => $p->status,
                'notes' => $p->notes,
                'paid_at' => $p->paid_at?->toISOString(),
            ])->values(),
        ];
    }
}
