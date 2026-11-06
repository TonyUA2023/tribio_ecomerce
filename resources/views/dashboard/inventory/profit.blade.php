@extends('layouts.dashboard')
@section('title', 'Rentabilidad')
@section('page_title', '📈 Rentabilidad')
@section('content')
@php
    use App\Services\Inventory\ProfitReport;
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $chipBase = 'inline-flex items-center px-3 py-1.5 rounded-full border text-xs font-semibold transition whitespace-nowrap';
    $chipOn   = 'bg-tribio-cyan/10 border-tribio-cyan/40 text-tribio-cyan';
    $chipOff  = 'bg-white/5 border-white/10 text-white/60 hover:border-white/20';
@endphp
<div class="w-full max-w-5xl mx-auto space-y-5">

    @php
        $sameDay = $from->isSameDay($to);
        $period = $sameDay ? 'el ' . $from->locale('es')->isoFormat('dddd D [de] MMMM YYYY') : 'del ' . $from->locale('es')->isoFormat('D MMM') . ' al ' . $to->locale('es')->isoFormat('D MMM YYYY');
        $today = now('America/Lima')->toDateString();
    @endphp
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Lo que vendiste, lo que te costó y lo que ganaste con cada producto. Cuentan los pedidos pagados (o con adelanto) y no cancelados, <strong class="text-white">{{ $period }}</strong>.</p>
        <a href="{{ route('dashboard.inventario.index') }}" class="text-xs text-tribio-cyan underline">← Inventario</a>
    </div>

    <div class="rp-filters">
        <div class="rp-chips" aria-label="Período">
            @foreach(ProfitReport::PRESETS as $key => $label)
                <a href="{{ route('dashboard.inventario.profit', ['periodo' => $key]) }}" class="pos-chip {{ $preset === $key ? 'is-active' : '' }}" @if($preset === $key) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('dashboard.inventario.profit') }}" class="rp-range" aria-label="Elegir fechas">
            <label>Desde<input type="date" name="desde" value="{{ $from->toDateString() }}" max="{{ $today }}" required></label>
            <label>Hasta<input type="date" name="hasta" value="{{ $to->toDateString() }}" max="{{ $today }}" required></label>
            <button type="submit" class="pos-chip {{ $preset === null ? 'is-active' : '' }}">Ver ganancia</button>
        </form>
        @if($rangeError)<p class="rp-error" role="alert">Revisa las fechas: «Hasta» no puede ser anterior a «Desde» y el rango máximo es de 13 meses. Mostramos este mes.</p>@endif
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="stat-card"><p class="text-white/50 text-xs">Ventas</p><p class="text-lg sm:text-xl font-black whitespace-nowrap text-white mt-1">{{ $money($totals['revenue']) }}</p><p class="text-[11px] text-white/40 mt-0.5">{{ $totals['units'] }} unidades</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Costo de lo vendido</p><p class="text-lg sm:text-xl font-black whitespace-nowrap text-white mt-1">{{ $money($totals['cost']) }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Ganancia bruta</p><p class="text-lg sm:text-xl font-black whitespace-nowrap mt-1 {{ $totals['profit'] < 0 ? 'text-red-400' : 'text-white' }}">{{ $money($totals['profit']) }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Margen</p><p class="text-lg sm:text-xl font-black whitespace-nowrap text-white mt-1">{{ $totals['margin'] !== null ? $totals['margin'] . '%' : '—' }}</p></div>
    </div>

    @if($estimated || $missing)
    <div class="glass-card p-4">
        @if($estimated)<p class="text-xs text-white/70"><span class="badge badge-gold">Estimado</span> Algunas unidades no salieron de un lote con costo (por encargo, sin stock o vendidas antes de registrar costos): usamos el costo actual del producto.</p>@endif
        @if($missing)<p class="text-xs text-white/70 {{ $estimated ? 'mt-2' : '' }}"><span class="badge badge-red">Sin costo</span> Hay productos sin costo registrado: su ganancia aparece más alta de lo real. Registra su costo en una entrada de mercadería o en el producto.</p>@endif
    </div>
    @endif

    @if($rows === [])
        <div class="glass-card p-6 text-center">
            <p class="text-white font-semibold">Aún no hay ventas pagadas en este periodo.</p>
            <p class="text-xs text-white/50 mt-1">Cuando un pedido quede pagado aparecerá aquí con su ganancia. Prueba con otro período o con otras fechas.</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($rows as $row)
            <a href="{{ route('dashboard.inventario.product', $row['product_id']) }}" class="glass-card p-3.5 sm:p-4 flex items-center gap-3 hover:border-tribio-cyan/40 transition">
                @if($row['image'])<img src="{{ $row['image'] }}" alt="" class="w-12 h-12 rounded-lg object-cover flex-shrink-0">@endif
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-white font-semibold truncate">{{ $row['name'] }}</p>
                    <p class="text-[11px] text-white/50">
                        {{ $row['units'] }} und. · vendido {{ $money($row['revenue']) }} · costo {{ $money($row['cost']) }}
                        @if($row['cost_status'] === 'estimated')<span class="badge badge-gold ml-1">Estimado</span>@endif
                        @if($row['cost_status'] === 'missing')<span class="badge badge-red ml-1">Sin costo</span>@endif
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm font-bold {{ $row['profit'] < 0 ? 'text-red-400' : 'text-white' }}">{{ $money($row['profit']) }}</p>
                    <p class="text-[11px] text-white/50">{{ $row['margin'] !== null ? $row['margin'] . '% margen' : '—' }}</p>
                </div>
            </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
