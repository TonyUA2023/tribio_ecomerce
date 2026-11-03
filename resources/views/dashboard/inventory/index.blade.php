@extends('layouts.dashboard')
@section('title','Inventario') @section('page_title','📋 Inventario')
@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach(['total_tracked' => ['📊','Productos con control'],'out_of_stock' => ['🔴','Sin stock'],'low_stock' => ['⚠️','Stock bajo'],'total_value' => ['💰','Valor del stock (a costo)']] as $key => [$icon,$label])
    <div class="stat-card"><p class="text-white/50 text-xs">{{ $label }}</p><p class="text-xl sm:text-2xl font-black text-white mt-2 whitespace-nowrap">{{ $key === 'total_value' ? 'S/ '.number_format($stats[$key],2) : $stats[$key] }}</p></div>
    @endforeach
</div>

<div class="glass-card p-4 sm:p-5 mb-6 flex items-center justify-between gap-3 flex-wrap">
    <div class="min-w-0">
        <p class="text-white font-bold text-sm">📈 ¿Cuánto estás ganando?</p>
        <p class="text-xs text-white/50 mt-0.5">Ventas, costo y ganancia de cada producto, con el costo real de lo que vendiste.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('dashboard.inventario.reorder') }}" class="btn-secondary text-xs sm:text-sm">🔁 Reposición</a>
        <a href="{{ route('dashboard.inventario.conteos.index') }}" class="btn-secondary text-xs sm:text-sm">🧮 Conteo físico</a>
        <a href="{{ route('dashboard.inventario.profit') }}" class="btn-primary text-xs sm:text-sm">Ver rentabilidad</a>
    </div>
</div>

@if($expiring->isNotEmpty())
<div class="glass-card p-4 sm:p-5 mb-6">
    <p class="text-white font-bold text-sm">⏰ Por vencer en los próximos {{ \App\Models\StockLot::EXPIRING_DAYS }} días</p>
    <div class="space-y-2 mt-3">
        @foreach($expiring as $lot)
        <a href="{{ route('dashboard.inventario.product', $lot->product_id) }}" class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3 hover:border-tribio-cyan/40 transition">
            <span class="text-sm text-white truncate">{{ $lot->product?->name }} <span class="text-white/40 text-xs">· {{ $lot->code ?: 'sin código' }}</span></span>
            <span class="flex items-center gap-2 flex-shrink-0">
                <span class="text-xs text-white/60">{{ $lot->quantity_remaining }} und.</span>
                <span class="badge {{ $lot->expiry_state === 'expired' ? 'badge-red' : 'badge-gold' }}">{{ $lot->expiry_state === 'expired' ? 'Vencido' : 'Vence ' . $lot->expires_at->locale('es')->diffForHumans() }}</span>
            </span>
        </a>
        @endforeach
    </div>
</div>
@endif
<div class="glass-card overflow-hidden">
    <div class="p-4 border-b flex gap-3" style="border-color:rgba(255,255,255,0.08);">
        <form method="GET" class="flex gap-3 flex-wrap">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar producto..." class="input-field max-w-xs">
            <select name="filter" class="input-field max-w-xs">
                <option value="">Todos</option>
                <option value="tracked" {{ request('filter')==='tracked' ? 'selected' : '' }}>Con control</option>
                <option value="low" {{ request('filter')==='low' ? 'selected' : '' }}>Stock bajo</option>
                <option value="out" {{ request('filter')==='out' ? 'selected' : '' }}>Sin stock</option>
            </select>
            <button type="submit" class="btn-primary px-4">Filtrar</button>
            <a href="{{ route('dashboard.inventario.export') }}" class="btn-ghost px-4">📥 CSV</a>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b" style="border-color:rgba(255,255,255,0.08);">
                <th class="text-left py-3 px-4 text-white/50">Producto</th>
                <th class="text-left py-3 px-4 text-white/50">SKU</th>
                <th class="text-left py-3 px-4 text-white/50">Stock</th>
                <th class="text-left py-3 px-4 text-white/50">Alerta</th>
                <th class="text-left py-3 px-4 text-white/50">Costo</th>
                <th class="text-right py-3 px-4 text-white/50">Acciones</th>
            </tr></thead>
            <tbody>@foreach($products as $product)
            <tr class="border-b hover:bg-white/3 transition-colors" style="border-color:rgba(255,255,255,0.04);">
                <td class="py-3 px-4"><p class="text-white font-medium">{{ $product->name }}</p><p class="text-white/40 text-xs">{{ $product->category?->name }}</p></td>
                <td class="py-3 px-4 text-white/60 text-xs font-mono">{{ $product->sku ?? '—' }}</td>
                <td class="py-3 px-4">
                    @if($product->track_stock)
                        <span class="{{ $product->stock === 0 ? 'badge badge-red' : ($product->isLowStock() ? 'badge badge-gold' : 'badge badge-green') }}">{{ $product->stock }} {{ $product->unit }}</span>
                        @if($product->relationLoaded('units') && $product->stockInUnits())<span class="block text-[11px] text-white/40 mt-0.5">= {{ $product->stockInUnits() }}</span>@endif
                    @else <span class="text-white/30 text-xs">No controlado</span>@endif
                </td>
                <td class="py-3 px-4 text-white/60 text-xs">{{ $product->low_stock_alert ?? '—' }}</td>
                <td class="py-3 px-4 text-white/60 text-xs">{{ $product->cost_price ? 'S/ '.number_format($product->cost_price,2) : '—' }}</td>
                <td class="py-3 px-4 text-right">
                    <a href="{{ route('dashboard.inventario.product', $product) }}" class="btn-ghost py-1 px-3 text-xs">Ver / mover</a>
                </td>
            </tr>@endforeach</tbody>
        </table>
    </div>
    <div class="p-4">{{ $products->links() }}</div>
</div>
@endsection
