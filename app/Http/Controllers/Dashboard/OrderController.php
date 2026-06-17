<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    private function getStore() { return Auth::user()->store; }

    public function index(Request $request)
    {
        $store = $this->getStore();

        $orders = $store->orders()
            ->with('items')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('order_number', 'like', "%{$request->search}%")
                ->orWhere('customer_name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(15);

        $statusCounts = [
            'pending'    => $store->orders()->where('status', 'pending')->count(),
            'confirmed'  => $store->orders()->where('status', 'confirmed')->count(),
            'processing' => $store->orders()->where('status', 'processing')->count(),
            'delivered'  => $store->orders()->where('status', 'delivered')->count(),
            'cancelled'  => $store->orders()->where('status', 'cancelled')->count(),
        ];

        return view('dashboard.orders.index', compact('store', 'orders', 'statusCounts'));
    }

    public function show(Order $order)
    {
        $store = $this->getStore();
        abort_if($order->store_id !== $store->id, 403);
        $order->load('items');

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
