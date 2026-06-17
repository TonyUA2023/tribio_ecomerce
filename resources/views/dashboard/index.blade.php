@extends('layouts.dashboard')
@section('title', 'Mi Dashboard')
@section('page_title', '¡Hola, ' . Auth::user()->name . '! 👋')

@section('content')
<div class="space-y-6">

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
        $statCards = [
            ['label' => 'Productos activos', 'value' => $stats['active_products'], 'icon' => '📦', 'color' => 'purple', 'sub' => $stats['total_products'] . ' total'],
            ['label' => 'Pedidos pendientes', 'value' => $stats['pending_orders'], 'icon' => '🛒', 'color' => 'yellow', 'sub' => $stats['total_orders'] . ' total'],
            ['label' => 'Ingresos del mes', 'value' => 'S/. ' . number_format($stats['revenue_month'], 2), 'icon' => '💰', 'color' => 'green', 'sub' => 'S/. ' . number_format($stats['revenue_total'], 2) . ' total'],
            ['label' => 'Stock bajo', 'value' => $stats['low_stock_products'], 'icon' => '⚠️', 'color' => 'red', 'sub' => $stats['out_of_stock'] . ' sin stock'],
        ];
        @endphp

        @foreach($statCards as $card)
        <div class="stat-card">
            <div class="flex items-start justify-between mb-3">
                <p class="text-white/50 text-xs font-medium">{{ $card['label'] }}</p>
                <span class="text-2xl">{{ $card['icon'] }}</span>
            </div>
            <p class="text-2xl font-black text-white">{{ $card['value'] }}</p>
            <p class="text-white/30 text-xs mt-1">{{ $card['sub'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Quick Actions --}}
    <div class="glass-card p-5">
        <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">Acciones rápidas</h3>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('dashboard.productos.create') }}" class="btn-primary py-2.5 px-4 text-sm">
                + Nuevo producto
            </a>
            <a href="{{ route('dashboard.pedidos.index') }}" class="btn-secondary py-2.5 px-4 text-sm">
                🛒 Ver pedidos
            </a>
            <a href="{{ route('dashboard.galeria.index') }}" class="btn-secondary py-2.5 px-4 text-sm">
                🖼️ Subir fotos
            </a>
            <a href="{{ route('dashboard.store.edit') }}" class="btn-secondary py-2.5 px-4 text-sm">
                🎨 Personalizar tienda
            </a>
            @if($store)
            <a href="{{ route('store.show', $store->slug) }}" target="_blank" class="btn-ghost py-2.5 px-4 text-sm">
                🌐 Ver mi tienda
            </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Recent Orders --}}
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-white font-bold">Pedidos recientes</h3>
                <a href="{{ route('dashboard.pedidos.index') }}" class="text-tribio-cyan text-sm hover:text-white transition-colors">Ver todos →</a>
            </div>
            @if($recentOrders->isEmpty())
            <div class="text-center py-8">
                <p class="text-5xl mb-3">🛒</p>
                <p class="text-white/40 text-sm">Aún no tienes pedidos.</p>
                <p class="text-white/25 text-xs mt-1">Comparte el link de tu tienda para empezar.</p>
            </div>
            @else
            <div class="space-y-3">
                @foreach($recentOrders as $order)
                <div class="flex items-center gap-3 p-3 rounded-xl bg-white/3 hover:bg-white/5 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium">{{ $order->customer_name }}</p>
                        <p class="text-white/40 text-xs">{{ $order->order_number }} • {{ $order->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-white font-bold text-sm">S/. {{ number_format($order->total, 2) }}</p>
                        <span class="badge badge-{{ $order->status_color }} text-xs">{{ $order->status_label }}</span>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Top Products --}}
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-white font-bold">Productos más vendidos</h3>
                <a href="{{ route('dashboard.productos.index') }}" class="text-tribio-cyan text-sm hover:text-white transition-colors">Ver todos →</a>
            </div>
            @if($topProducts->isEmpty())
            <div class="text-center py-8">
                <p class="text-5xl mb-3">📦</p>
                <p class="text-white/40 text-sm">Aún no tienes productos.</p>
                <a href="{{ route('dashboard.productos.create') }}" class="btn-primary mt-3 text-sm py-2 px-4">+ Agregar producto</a>
            </div>
            @else
            <div class="space-y-3">
                @foreach($topProducts as $i => $product)
                <div class="flex items-center gap-3">
                    <span class="text-white/20 text-sm font-bold w-5 text-right flex-shrink-0">{{ $i + 1 }}</span>
                    <div class="w-10 h-10 rounded-lg bg-tribio-purple/20 flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($product->image_path)
                            <img src="{{ $product->image_url }}" class="w-full h-full object-cover">
                        @else
                            <span class="text-lg">📦</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ $product->name }}</p>
                        <p class="text-white/40 text-xs">{{ $product->sold_count }} ventas</p>
                    </div>
                    <p class="text-tribio-gold font-bold text-sm flex-shrink-0">S/. {{ number_format($product->price, 2) }}</p>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Store status card --}}
    @if($store && !$store->isActive())
    <div class="p-5 rounded-2xl bg-yellow-500/10 border border-yellow-500/20 flex items-center gap-4">
        <span class="text-3xl">⚠️</span>
        <div class="flex-1">
            <p class="text-yellow-300 font-bold">Tu tienda no está activa</p>
            <p class="text-yellow-300/60 text-sm mt-1">Estado actual: <strong>{{ $store->status }}</strong>. Contacta soporte si crees que esto es un error.</p>
        </div>
        <a href="{{ route('dashboard.store.edit') }}" class="btn-gold py-2 px-4 text-sm">Configurar</a>
    </div>
    @endif

</div>
@endsection
