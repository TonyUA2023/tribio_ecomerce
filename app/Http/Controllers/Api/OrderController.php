<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()->store;

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $query = $store->orders()->with('items');

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate(15);

        // Map status helpers
        $orders->getCollection()->transform(function ($order) {
            $order->status_label = $order->status_label;
            $order->status_color = $order->status_color;
            return $order;
        });

        return response()->json($orders);
    }

    public function show(Request $request, $id)
    {
        $store = $request->user()->store;

        if (!$store) {
            return response()->json(['message' => 'No tienes ninguna tienda configurada.'], 404);
        }

        $order = $store->orders()->with(['items'])->find($id);

        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        return response()->json([
            'order' => $order,
            'status_label' => $order->status_label,
            'status_color' => $order->status_color,
            'whatsapp_url' => "https://wa.me/" . preg_replace('/[^0-9]/', '', $order->store->whatsapp_phone ?? '') . "?text=" . $order->buildWhatsappMessage(),
            'whatsapp_message' => urldecode($order->buildWhatsappMessage()),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $store = $request->user()->store;

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

        $order->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Estado del pedido actualizado con éxito.',
            'order' => $order,
            'status_label' => $order->status_label,
            'status_color' => $order->status_color,
        ]);
    }
}
