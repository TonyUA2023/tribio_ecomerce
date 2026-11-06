<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreAnalytic;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(\App\Services\Reports\ProfitAndLoss $report)
    {
        $store = Auth::user()->currentStore();

        if (!$store) {
            return redirect()->route('dashboard.store.edit')
                ->with('info', 'Configura tu tienda para comenzar.');
        }

        // Modo simple: the first thing is always the selling screen.
        if (\App\Models\Store::modesReady() && $store->isSimple()) {
            return redirect()->route('dashboard.simple.index');
        }

        // A POS-only business lands on "Hoy", not on the online store's dashboard.
        if (\App\Models\Store::modesReady() && $store->isPosOnly()) {
            return $this->posHome($store, $report);
        }

        // Pedidos are the online store's; counter sales are Ventas. Revenue adds both when the account has both.
        $online = fn () => $store->orders()->where('source', '!=', 'pos');
        $revenue = fn (string $scope) => $store->orders()->where('status', '!=', 'cancelled')
            ->when($scope === 'pos', fn ($q) => $q->where('source', 'pos'))->when($scope === 'online', fn ($q) => $q->where('source', '!=', 'pos'));

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
            'total_orders'       => $online()->count(),
            'pending_orders'     => $online()->where('status', 'pending')->count(),
            'revenue_month'      => $revenue('all')->whereMonth('created_at', now()->month)->sum('total'),
            'revenue_total'      => $revenue('all')->sum('total'),
            'revenue_month_pos'    => $revenue('pos')->whereMonth('created_at', now()->month)->sum('total'),
            'revenue_month_online' => $revenue('online')->whereMonth('created_at', now()->month)->sum('total'),
        ];

        // Pedidos recientes (de la tienda virtual)
        $recentOrders = $online()
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

        // Two charts, one per system, for the same period: counter sales and online orders.
        $hasPos = app(\App\Services\Pos\PosSchema::class)->ready() && $store->hasPos();
        $hasStorefront = $store->hasStorefront();
        $chartPeriod = array_key_exists((string) request('grafico'), \App\Services\Reports\SalesSeries::PERIODS) ? (string) request('grafico') : '7';
        [$chartFrom, $chartTo] = \App\Services\Reports\SalesSeries::range($chartPeriod);
        $series = app(\App\Services\Reports\SalesSeries::class);
        $posSeries = $hasPos ? $series->daily($store, 'pos', $chartFrom, $chartTo) : null;
        $onlineSeries = $hasStorefront ? $series->daily($store, 'online', $chartFrom, $chartTo) : null;

        return view('dashboard.index', compact('store', 'stats', 'recentOrders', 'topProducts', 'analytics', 'hasPos', 'hasStorefront', 'chartPeriod', 'posSeries', 'onlineSeries'));
    }

    /** "Inicio" of the POS system: today at a glance, what needs doing, and the next step for a new account. */
    private function posHome(\App\Models\Store $store, \App\Services\Reports\ProfitAndLoss $report)
    {
        $pos = app(\App\Services\Pos\PosSchema::class);
        [$from, $to] = \App\Services\Reports\ProfitAndLoss::range('7_dias');
        $week = $report->build($store, $from, $to);
        $today = end($week['days']);

        $chartPeriod = array_key_exists((string) request('grafico'), \App\Services\Reports\SalesSeries::PERIODS) ? (string) request('grafico') : '7';
        [$chartFrom, $chartTo] = \App\Services\Reports\SalesSeries::range($chartPeriod);
        $salesSeries = app(\App\Services\Reports\SalesSeries::class)->daily($store, 'pos', $chartFrom, $chartTo);

        $tracked = $store->products()->where('track_stock', true);
        $lowStock = (clone $tracked)->whereColumn('stock', '<=', 'low_stock_alert')->where('stock', '>', 0)->count();
        $outOfStock = (clone $tracked)->where('stock', 0)->count();
        $restock = (clone $tracked)->whereColumn('stock', '<=', 'low_stock_alert')->orderBy('stock')->orderBy('name')->limit(6)->get(['id', 'name', 'stock', 'low_stock_alert', 'unit']);

        $registers = collect();
        $owed = ['total' => 0.0, 'count' => 0];
        $recent = collect();
        $everSold = false;
        if ($pos->ready()) {
            $registers = \App\Models\RegisterSession::where('store_id', $store->id)->where('status', 'open')->with('register:id,name')->get()
                ->map(fn ($session) => ['name' => $session->register->name, 'cash' => app(\App\Services\Pos\RegisterService::class)->summary($session)['expected_cash'], 'since' => $session->opened_at]);
            $recent = \App\Models\Order::where('store_id', $store->id)->where('source', 'pos')->latest('id')->limit(5)->get();
            $everSold = $recent->isNotEmpty();
            if ($pos->creditReady()) {
                $debts = \App\Models\Order::where('store_id', $store->id)->where('source', 'pos')->where('status', '!=', 'cancelled')->where('balance_due', '>', 0);
                $owed = ['total' => round((float) (clone $debts)->sum('balance_due'), 2), 'count' => (clone $debts)->count()];
            }
        }

        $documents = app(\App\Services\Pos\SalesDocuments::class);
        $steps = array_values(array_filter([
            ['done' => $store->products()->exists(), 'title' => 'Agrega tus productos', 'hint' => 'Hazlo en minutos con la tabla rápida: pega desde Excel o escanea los códigos.', 'url' => route('dashboard.productos.rapido'), 'cta' => 'Agregar productos'],
            $documents->ready() ? ['done' => \App\Services\Pos\SalesDocuments::validRuc((string) $store->ruc), 'title' => 'Registra el RUC de tu negocio', 'hint' => 'Lo necesitas para emitir boletas y facturas.', 'url' => route('dashboard.pos.documents.edit'), 'cta' => 'Completar datos'] : null,
            $pos->ready() ? ['done' => $everSold, 'title' => 'Haz tu primera venta', 'hint' => 'Abre la caja con el sencillo y cobra: toma un minuto.', 'url' => route('dashboard.pos.index'), 'cta' => 'Ir a vender'] : null,
        ]));

        return view('dashboard.pos-home', [
            'store' => $store, 'week' => $week, 'today' => $today, 'chartPeriod' => $chartPeriod, 'salesSeries' => $salesSeries, 'lowStock' => $lowStock, 'outOfStock' => $outOfStock, 'restock' => $restock,
            'registers' => $registers, 'owed' => $owed, 'recent' => $recent, 'steps' => $steps, 'posReady' => $pos->ready(), 'creditReady' => $pos->ready() && $pos->creditReady(),
        ]);
    }

    /**
     * Switches which of the account's own stores the dashboard operates on. Route-model
     * binding alone doesn't prove ownership, so it's checked explicitly — otherwise any
     * logged-in store owner could switch another account's dashboard to their store id.
     */
    public function switchStore(\App\Models\Store $store)
    {
        abort_unless(Auth::user()->stores()->whereKey($store->id)->exists(), 403);

        Auth::user()->switchToStore($store);

        return redirect()->route('dashboard.index');
    }
}
