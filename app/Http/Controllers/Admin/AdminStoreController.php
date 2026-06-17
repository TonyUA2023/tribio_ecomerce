<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class AdminStoreController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::with('user')
            ->withCount(['products', 'orders'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.stores.index', compact('stores'));
    }

    public function show(Store $store)
    {
        $store->load(['user', 'products', 'orders.items']);

        $stats = [
            'total_products' => $store->products()->count(),
            'total_orders'   => $store->orders()->count(),
            'revenue'        => $store->orders()->where('status', '!=', 'cancelled')->sum('total'),
            'views'          => $store->total_views,
        ];

        $recentOrders = $store->orders()->with('items')->latest()->limit(10)->get();

        return view('admin.stores.show', compact('store', 'stats', 'recentOrders'));
    }

    public function updateStatus(Request $request, Store $store)
    {
        $request->validate(['status' => 'required|in:draft,active,suspended,cancelled']);
        $store->update(['status' => $request->status]);

        return back()->with('success', "Tienda {$store->name} actualizada a: {$request->status}");
    }

    public function toggleFeatured(Store $store)
    {
        $store->update(['is_featured' => !$store->is_featured]);
        $msg = $store->is_featured ? 'destacada' : 'quitada de destacados';
        return back()->with('success', "Tienda {$store->name} {$msg}.");
    }

    public function destroy(Store $store)
    {
        $store->delete();
        return redirect()->route('admin.tiendas.index')
            ->with('success', 'Tienda eliminada correctamente.');
    }
}
