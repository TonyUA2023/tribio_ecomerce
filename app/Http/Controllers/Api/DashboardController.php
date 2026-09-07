<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StoreAnalytic;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $store = $user->store;

        if (!$store) {
            return response()->json([
                'message' => 'No tienes ninguna tienda configurada.'
            ], 404);
        }

        // Calcular estadísticas financieras de lotes compuestos (Tractores, cajones, etc.)
        $compositeRootProducts = $store->products()
            ->whereNull('parent_id')
            ->where('is_composite', true)
            ->get();
            
        $totalCompositeCost = 0.0;
        $totalCompositeRevenue = 0.0;
        
        foreach ($compositeRootProducts as $rootProd) {
            $totalCompositeCost += $rootProd->total_cost;
            $totalCompositeRevenue += $rootProd->revenue_generated;
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
            'revenue_month'      => floatval($store->orders()
                ->where('status', '!=', 'cancelled')
                ->whereMonth('created_at', now()->month)
                ->sum('total')),
            'revenue_total'      => floatval($store->orders()
                ->where('status', '!=', 'cancelled')
                ->sum('total')),
            
            // Financiero de lotes compuestos
            'composite_lots_count'    => $compositeRootProducts->count(),
            'total_composite_cost'    => floatval($totalCompositeCost),
            'total_composite_revenue' => floatval($totalCompositeRevenue),
            'total_composite_profit'  => floatval($totalCompositeRevenue - $totalCompositeCost),
        ];

        // Pedidos recientes
        $recentOrders = $store->orders()
            ->with('items')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'total' => floatval($order->total),
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'status_color' => $order->status_color,
                    'created_at' => $order->created_at->toISOString(),
                ];
            });

        // Productos más vendidos
        $topProducts = $store->products()
            ->orderByDesc('sold_count')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => floatval($product->price),
                    'sold_count' => $product->sold_count ?? 0,
                    'stock' => $product->stock,
                ];
            });

        // Analytics últimos 7 días
        $analytics = StoreAnalytic::where('store_id', $store->id)
            ->where('date', '>=', now()->subDays(7))
            ->orderBy('date')
            ->get()
            ->map(function ($analytic) {
                return [
                    'date' => $analytic->date,
                    'views' => $analytic->views ?? 0,
                    'orders' => $analytic->orders ?? 0,
                    'revenue' => floatval($analytic->revenue ?? 0),
                ];
            });

        return response()->json([
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'logo_url' => $store->logo_url,
            ],
            'stats' => $stats,
            'recent_orders' => $recentOrders,
            'top_products' => $topProducts,
            'analytics' => $analytics,
        ]);
    }
}
