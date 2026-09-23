<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            ->when($request->status, fn($q) => $q->where('status', $request->status))
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

        return view('dashboard.orders.index', compact('store', 'orders', 'statusCounts', 'totalCount', 'search'));
    }

    public function show(Order $order)
    {
        $store = $this->getStore();
        abort_if($order->store_id !== $store->id, 403);
        $order->load(['items', 'payments' => fn($q) => $q->orderBy('paid_at')]);

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
}
