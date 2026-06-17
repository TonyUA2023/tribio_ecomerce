@extends('layouts.admin')
@section('title', 'Dashboard Global')
@section('page_title', '📊 Dashboard Global — Tribio')

@section('content')
<div class="space-y-6">

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @php
        $globalStats = [
            ['label' => 'Tiendas activas', 'value' => $stats['active_stores'], 'total' => $stats['total_stores'], 'icon' => '🏪', 'color' => 'green'],
            ['label' => 'En borrador', 'value' => $stats['draft_stores'], 'total' => null, 'icon' => '📝', 'color' => 'yellow'],
            ['label' => 'Suspendidas', 'value' => $stats['suspended_stores'], 'total' => null, 'icon' => '⛔', 'color' => 'red'],
            ['label' => 'Pedidos totales', 'value' => $stats['total_orders'], 'total' => null, 'icon' => '🛒', 'color' => 'purple'],
            ['label' => 'Ingresos totales', 'value' => 'S/. ' . number_format($stats['revenue_total'], 2), 'total' => 'Mes: S/. ' . number_format($stats['revenue_month'], 2), 'icon' => '💰', 'color' => 'gold'],
        ];
        @endphp
        @foreach($globalStats as $stat)
        <div class="stat-card">
            <div class="flex items-start justify-between mb-2">
                <p class="text-white/50 text-xs">{{ $stat['label'] }}</p>
                <span class="text-xl">{{ $stat['icon'] }}</span>
            </div>
            <p class="text-2xl font-black text-white">{{ $stat['value'] }}</p>
            @if($stat['total'])
            <p class="text-white/30 text-xs mt-1">{{ $stat['total'] }}</p>
            @endif
        </div>
        @endforeach
    </div>

    {{-- New stores this week alert --}}
    <div class="p-4 rounded-2xl bg-tribio-purple/10 border border-tribio-purple/20 flex items-center gap-4">
        <span class="text-3xl">🆕</span>
        <div>
            <p class="text-white font-semibold">{{ $stats['new_stores_week'] }} nueva(s) tienda(s) esta semana</p>
            <p class="text-white/40 text-sm">{{ $stats['total_users'] }} usuarios registrados en la plataforma.</p>
        </div>
        <a href="{{ route('admin.tiendas.index') }}" class="ml-auto btn-primary py-2 px-4 text-sm">Ver tiendas →</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Recent Stores --}}
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-white font-bold">Tiendas recientes</h3>
                <a href="{{ route('admin.tiendas.index') }}" class="text-tribio-cyan text-sm hover:text-white">Ver todas →</a>
            </div>
            <div class="space-y-3">
                @foreach($recentStores as $store)
                <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 transition-colors">
                    <div class="w-9 h-9 rounded-lg bg-tribio-purple/20 flex items-center justify-center text-sm flex-shrink-0 overflow-hidden">
                        @if($store->logo_path)
                            <img src="{{ $store->logo_url }}" class="w-full h-full object-cover">
                        @else
                            🏪
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ $store->name }}</p>
                        <p class="text-white/40 text-xs">{{ $store->user->name }} • {{ $store->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="badge badge-{{ $store->status === 'active' ? 'green' : ($store->status === 'suspended' ? 'red' : 'yellow') }}">
                            {{ $store->status }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Recent Orders global --}}
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-white font-bold">Pedidos recientes (global)</h3>
            </div>
            @if($recentOrders->isEmpty())
            <p class="text-white/40 text-sm text-center py-8">Sin pedidos aún.</p>
            @else
            <div class="space-y-3">
                @foreach($recentOrders->take(8) as $order)
                <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/5 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium">{{ $order->customer_name }}</p>
                        <p class="text-white/40 text-xs">{{ $order->store->name }} • {{ $order->order_number }}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-tribio-gold font-bold text-sm">S/. {{ number_format($order->total, 2) }}</p>
                        <span class="badge badge-{{ $order->status_color }} text-xs">{{ $order->status_label }}</span>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>
</div>
@endsection
