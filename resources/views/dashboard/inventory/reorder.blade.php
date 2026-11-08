@extends('layouts.dashboard')
@section('title', 'Reposición')
@section('page_title', '🔁 Reposición')
@section('content')
@php $money = fn ($value) => 'S/ ' . number_format((float) $value, 2); @endphp
<div class="w-full max-w-[1600px] mx-auto space-y-5">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Qué comprar antes de quedarte sin stock, según lo que vendiste en los últimos {{ $params['window'] }} días. Pedimos para cubrir {{ $params['lead'] }} días de entrega más {{ $params['cover'] }} días de venta.</p>
        <a href="{{ route('dashboard.inventario.index') }}" class="text-xs text-tribio-cyan underline">← Inventario</a>
    </div>

    <form method="GET" action="{{ route('dashboard.inventario.reorder') }}" class="glass-card p-4 grid grid-cols-3 gap-3 items-end">
        <div>
            <label class="input-label" for="ro-window">Ventas de los últimos</label>
            <select id="ro-window" name="window" class="input-field">
                @foreach([15, 30, 60, 90] as $days)<option value="{{ $days }}" @selected($params['window'] === $days)>{{ $days }} días</option>@endforeach
            </select>
        </div>
        <div>
            <label class="input-label" for="ro-lead">Tu proveedor demora</label>
            <select id="ro-lead" name="lead" class="input-field">
                @foreach([1, 3, 7, 15, 30] as $days)<option value="{{ $days }}" @selected($params['lead'] === $days)>{{ $days }} {{ $days === 1 ? 'día' : 'días' }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="input-label" for="ro-cover">Comprar para</label>
            <select id="ro-cover" name="cover" class="input-field">
                @foreach([15, 30, 45, 60] as $days)<option value="{{ $days }}" @selected($params['cover'] === $days)>{{ $days }} días</option>@endforeach
            </select>
        </div>
        <button type="submit" class="btn-secondary text-xs sm:text-sm col-span-3 sm:col-span-1 sm:col-start-3">Recalcular</button>
    </form>

    {{-- Sugeridos --}}
    @if($suggestions === [])
        <div class="glass-card p-6 text-center">
            <p class="text-white font-semibold">Tu stock alcanza por ahora. 👌</p>
            <p class="text-xs text-white/50 mt-1">Ningún producto se quedará sin stock antes de que llegue una compra.</p>
        </div>
    @else
    <form method="POST" action="{{ route('dashboard.inventario.reorder.purchase') }}" class="glass-card p-4 sm:p-6 space-y-3"
          x-data="{ lines: @js(collect($suggestions)->mapWithKeys(fn ($s) => [$s['product_id'] => ['on' => true, 'qty' => $s['suggested'], 'cost' => $s['unit_cost'] ?? 0]])) }">
        @csrf
        @foreach(['window', 'lead', 'cover'] as $key)<input type="hidden" name="{{ $key }}" value="{{ $params[$key] }}">@endforeach
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-white font-bold text-base">Te conviene comprar <span class="text-white/50 text-xs font-normal">· {{ $suggestionsTotal }} {{ $suggestionsTotal === 1 ? 'producto' : 'productos' }}</span></h3>
            <p class="text-xs text-white/60">Costo estimado: <strong class="text-white" x-text="'S/ ' + Object.values(lines).filter(l => l.on).reduce((s, l) => s + l.qty * l.cost, 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong></p>
        </div>
        @if($errors->any())
            @foreach($errors->all() as $error)<p class="text-xs text-red-300" role="alert">{{ $error }}</p>@endforeach
        @endif

        @foreach($suggestions as $row)
        <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center gap-3">
            <input type="checkbox" name="lines[{{ $row['product_id'] }}][selected]" value="1" x-model="lines[{{ $row['product_id'] }}].on" aria-label="Comprar {{ $row['name'] }}">
            <div class="min-w-0 flex-1">
                <p class="text-sm text-white font-semibold truncate">{{ $row['name'] }}</p>
                <p class="text-[11px] text-white/50">
                    Stock {{ $row['stock'] }} · vendiste {{ $row['sold'] }} en {{ $params['window'] }} días
                    @if($row['days_left'] !== null)
                        · <span class="{{ $row['days_left'] <= $params['lead'] ? 'text-red-300' : '' }}">alcanza para {{ $row['days_left'] }} {{ $row['days_left'] === 1 ? 'día' : 'días' }}</span>
                    @else
                        · en su alerta de stock bajo
                    @endif
                    @if($row['supplier']) · último proveedor {{ $row['supplier'] }} @endif
                </p>
            </div>
            <div class="w-20 flex-shrink-0">
                <input type="number" min="1" name="lines[{{ $row['product_id'] }}][quantity]" class="input-field !px-2 text-center" x-model.number="lines[{{ $row['product_id'] }}].qty" aria-label="Cantidad a comprar de {{ $row['name'] }}">
            </div>
            <p class="w-20 text-right text-[11px] text-white/50 flex-shrink-0">{{ $row['unit_cost'] !== null ? $money($row['unit_cost']) . ' c/u' : 'sin costo' }}</p>
        </div>
        @endforeach

        @if($suggestionsTotal > count($suggestions))
            <p class="text-xs text-white/60 text-center">Mostrando los {{ count($suggestions) }} más urgentes de {{ $suggestionsTotal }}. <a href="{{ route('dashboard.inventario.reorder', array_merge(\Illuminate\Support\Arr::only($params, ['window', 'lead', 'cover']), ['todo' => 1])) }}" class="underline text-tribio-cyan">Ver más</a></p>
        @endif

        @if($canPurchase)
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end pt-2">
            <div>
                <label class="input-label" for="ro-supplier">Proveedor</label>
                <select id="ro-supplier" name="supplier_id" class="input-field">
                    <option value="">Sin proveedor</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected($supplierId === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-primary text-xs sm:text-sm">Armar compra con lo marcado</button>
        </div>
        @endif
    </form>
    @endif

    {{-- Sin movimiento --}}
    @if($stagnant !== [])
    <div class="glass-card p-4 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-white font-bold text-base">Sin ventas hace {{ $params['stale'] }} días <span class="text-white/50 text-xs font-normal">· {{ $stagnantTotal }} {{ $stagnantTotal === 1 ? 'producto' : 'productos' }}</span></h3>
            <p class="text-xs text-white/60">Dinero detenido: <strong class="text-white">{{ $money($stagnantMoney) }}</strong></p>
        </div>
        <p class="text-xs text-white/50 mt-1">Ideal para una oferta, un combo o dejar de comprarlos.</p>
        <div class="space-y-2 mt-3">
            @foreach($stagnant as $row)
            <a href="{{ route('dashboard.inventario.product', $row['product_id']) }}" class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3 hover:border-tribio-cyan/40 transition">
                <span class="text-sm text-white truncate">{{ $row['name'] }} <span class="text-white/40 text-xs">· {{ $row['stock'] }} und.</span></span>
                <span class="text-sm text-white font-semibold whitespace-nowrap">{{ $money($row['value']) }}</span>
            </a>
            @endforeach
        </div>
        @if($stagnantTotal > count($stagnant))
            <p class="text-xs text-white/60 text-center mt-3">Mostrando los {{ count($stagnant) }} con más dinero detenido de {{ $stagnantTotal }}. <a href="{{ route('dashboard.inventario.reorder', array_merge(\Illuminate\Support\Arr::only($params, ['window', 'lead', 'cover']), ['todo' => 1])) }}" class="underline text-tribio-cyan">Ver más</a></p>
        @endif
    </div>
    @endif
</div>
@endsection
