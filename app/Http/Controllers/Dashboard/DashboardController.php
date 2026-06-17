<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreAnalytic;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $store = Auth::user()->store;

        if (!$store) {
            return redirect()->route('dashboard.store.edit')
                ->with('info', 'Configura tu tienda para comenzar.');
        }

        // Estadísticas rápidas
        $stats = [
            'total_products'     => $store->products()->count(),
            'active_products'    => $store->products()->where('is_active', true)->count(),
            'low_stock_products' => $store->products()
                ->where('track_stock', true)
                ->whereColumn('stock', '<=', 'low_stock_alert')
                ->where('stock', '>', 0)
                ->count(),
            'out_of_stock'       => $store->products()
                ->where('track_stock', true)
                ->where('stock', 0)
                ->count(),
            'total_orders'       => $store->orders()->count(),
            'pending_orders'     => $store->orders()->where('status', 'pending')->count(),
            'revenue_month'      => $store->orders()
                ->where('status', '!=', 'cancelled')
                ->whereMonth('created_at', now()->month)
                ->sum('total'),
            'revenue_total'      => $store->orders()
                ->where('status', '!=', 'cancelled')
                ->sum('total'),
        ];

        // Pedidos recientes
        $recentOrders = $store->orders()
            ->with('items')
            ->latest()
            ->limit(5)
            ->get();

        // Productos más vendidos
        $topProducts = $store->products()
            ->orderByDesc('sold_count')
            ->limit(5)
            ->get();

        // Analytics últimos 7 días
        $analytics = StoreAnalytic::where('store_id', $store->id)
            ->where('date', '>=', now()->subDays(7))
            ->orderBy('date')
            ->get();

        return view('dashboard.index', compact('store', 'stats', 'recentOrders', 'topProducts', 'analytics'));
    }
}
