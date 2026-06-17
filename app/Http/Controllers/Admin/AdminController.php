<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_stores'    => Store::count(),
            'active_stores'   => Store::where('status', 'active')->count(),
            'draft_stores'    => Store::where('status', 'draft')->count(),
            'suspended_stores'=> Store::where('status', 'suspended')->count(),
            'total_users'     => User::count(),
            'total_products'  => Product::count(),
            'total_orders'    => Order::count(),
            'revenue_total'   => Order::where('status', '!=', 'cancelled')->sum('total'),
            'revenue_month'   => Order::where('status', '!=', 'cancelled')
                ->whereMonth('created_at', now()->month)
                ->sum('total'),
            'new_stores_week' => Store::where('created_at', '>=', now()->subWeek())->count(),
        ];

        $recentStores = Store::with('user')
            ->latest()
            ->limit(8)
            ->get();

        $recentOrders = Order::with(['store', 'items'])
            ->latest()
            ->limit(10)
            ->get();

        // Revenue por mes (últimos 6 meses)
        $monthlyRevenue = Order::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subMonths(6))
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total) as revenue, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return view('admin.dashboard', compact('stats', 'recentStores', 'recentOrders', 'monthlyRevenue'));
    }

    public function analytics()
    {
        return view('admin.analytics');
    }

    public function analyticsData()
    {
        // API endpoint para Chart.js
        $data = [
            'stores_by_category' => Store::selectRaw('category, COUNT(*) as total')
                ->groupBy('category')
                ->get(),
            'stores_by_status'   => Store::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get(),
            'revenue_last_30'    => Order::where('status', '!=', 'cancelled')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];

        return response()->json($data);
    }
}
