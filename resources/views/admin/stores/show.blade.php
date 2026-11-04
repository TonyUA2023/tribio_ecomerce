@extends('layouts.admin')
@section('title', $store->name)
@section('page_title', '🏪 ' . $store->name)
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="glass-card p-6">
            <h3 class="text-white font-bold mb-4">Información de la tienda</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><p class="text-white/40">Slug</p><p class="text-white">/tienda/{{ $store->slug }}</p></div>
                <div><p class="text-white/40">Estado</p><span class="badge badge-{{ $store->status === 'active' ? 'green' : 'yellow' }}">{{ $store->status }}</span></div>
                <div><p class="text-white/40">Plan</p><p class="text-white">{{ ucfirst($store->plan) }}</p></div>
                <div><p class="text-white/40">Plantilla</p><p class="text-white">{{ $store->template_name }}</p></div>
                <div><p class="text-white/40">WhatsApp</p><p class="text-white">{{ $store->whatsapp_phone ?? '—' }}</p></div>
                <div><p class="text-white/40">Categoría</p><p class="text-white">{{ \App\Support\BusinessProfile::forStore($store)->icon() }} {{ \App\Support\BusinessProfile::forStore($store)->label() }}</p></div>
            </div>
        </div>
        @if(\App\Models\Store::modesReady())
        <div class="glass-card p-6">
            <h3 class="text-white font-bold mb-1">Tipo de cuenta</h3>
            <p class="text-white/50 text-xs mb-4">Qué contrató este negocio. «Solo sistema POS» no tiene página pública: su tienda deja de existir en internet, del directorio y del sitemap, y el panel muestra solo ventas, inventario y reportes.</p>
            <form method="POST" action="{{ route('admin.tiendas.mode', $store) }}" class="flex flex-col sm:flex-row gap-3">
                @csrf @method('PATCH')
                <label for="store-mode" class="sr-only">Tipo de cuenta</label>
                <select id="store-mode" name="mode" class="input-field flex-1">
                    @foreach(\App\Models\Store::MODES as $key => $label)<option value="{{ $key }}" @selected($store->mode() === $key)>{{ $label }}</option>@endforeach
                </select>
                <button type="submit" class="btn-primary">Guardar</button>
            </form>
        </div>
        @endif
        <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-white font-bold">Pedidos recientes</h3>
            </div>
            @foreach($recentOrders as $order)
            <div class="flex items-center gap-3 py-2 border-b" style="border-color: rgba(255,255,255,0.05);">
                <div class="flex-1"><p class="text-white text-sm">{{ $order->customer_name }}</p><p class="text-white/40 text-xs">{{ $order->order_number }}</p></div>
                <p class="text-tribio-gold font-bold text-sm">S/. {{ number_format($order->total, 2) }}</p>
                <span class="badge badge-{{ $order->status_color }}">{{ $order->status_label }}</span>
            </div>
            @endforeach
        </div>
    </div>
    <div class="space-y-4">
        @foreach(['total_products' => '📦 Productos', 'total_orders' => '🛒 Pedidos', 'revenue' => '💰 Ingresos', 'views' => '👁 Vistas'] as $key => $label)
        <div class="stat-card">
            <p class="text-white/50 text-xs">{{ $label }}</p>
            <p class="text-xl font-black text-white mt-1">{{ is_float($stats[$key]) ? 'S/. ' . number_format($stats[$key], 2) : number_format($stats[$key]) }}</p>
        </div>
        @endforeach
        <a href="{{ route('store.show', $store->slug) }}" target="_blank" class="btn-primary w-full justify-center">🌐 Ver tienda</a>
        <a href="{{ route('admin.tiendas.index') }}" class="btn-ghost w-full justify-center">← Volver</a>
    </div>
</div>
@endsection
