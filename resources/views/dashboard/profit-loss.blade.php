@extends('layouts.dashboard')
@section('title', 'Ganancias y pérdidas')
@section('page_title', '📈 Ganancias y pérdidas')
@section('content')
@php
    $money = fn ($value) => (round((float) $value, 2) < 0 ? '−' : '') . 'S/ ' . number_format(abs((float) $value), 2);
    $day = fn ($date) => \Illuminate\Support\Carbon::parse($date)->locale('es')->isoFormat('ddd D MMM');
    $net = $totals['net'];
    $biggest = max(1, collect($days)->map(fn ($d) => abs($d['net']))->max());
    $docLabels = ['nota_venta' => 'Notas de venta', 'boleta' => 'Boletas de venta', 'factura' => 'Facturas', 'tienda' => 'Pedidos de la tienda online'];
    $methodLabels = \App\Services\Pos\PosSaleService::METHODS;
@endphp
<div class="pl-app w-full max-w-[1600px] mx-auto space-y-5">
    <div class="space-y-3">
        <p class="text-sm text-white/60 max-w-3xl">Lo que vendiste, lo que te costó, lo que gastaste y lo que realmente te queda. Los días son de hora de Lima.</p>
        <div class="flex items-center gap-2 flex-wrap">
            @foreach($presets as $key => $label)
                <a href="{{ route('dashboard.ganancias.index', ['periodo' => $key]) }}" class="pos-chip {{ $preset === $key ? 'is-active' : '' }}" @if($preset === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <form method="GET" action="{{ route('dashboard.ganancias.index') }}" class="pl-range">
                <label class="sr-only" for="pl-from">Desde</label><input id="pl-from" type="date" name="desde" value="{{ $from->toDateString() }}" max="{{ now('America/Lima')->toDateString() }}">
                <span aria-hidden="true">→</span>
                <label class="sr-only" for="pl-to">Hasta</label><input id="pl-to" type="date" name="hasta" value="{{ $to->toDateString() }}" max="{{ now('America/Lima')->toDateString() }}">
                <button type="submit" class="pos-chip {{ $preset === null ? 'is-active' : '' }}">Ver</button>
            </form>
        </div>
        <p class="text-xs text-white/50">{{ $from->locale('es')->isoFormat('D MMM YYYY') }} – {{ $to->locale('es')->isoFormat('D MMM YYYY') }} · {{ $totals['sales'] }} {{ $totals['sales'] === 1 ? 'venta' : 'ventas' }}@if($totals['sales']) · ticket promedio {{ $money($totals['average_ticket']) }}@endif</p>
    </div>

    <section class="pl-kpis" aria-label="Resumen del período">
        <div class="pl-kpi"><small>Ingresos</small><strong>{{ $money($totals['income']) }}</strong><span>Ventas {{ $money($totals['sold']) }}@if($totals['returns'] > 0) − devoluciones {{ $money($totals['returns']) }}@endif</span></div>
        <div class="pl-kpi"><small>Costo de ventas</small><strong>{{ $money($totals['cost']) }}</strong><span>Lo que costó lo que vendiste</span></div>
        <div class="pl-kpi {{ $totals['gross'] >= 0 ? 'is-profit' : 'is-loss' }}"><small>Ganancia bruta</small><strong>{{ $money($totals['gross']) }}</strong><span>{{ $totals['margin'] !== null ? 'Margen ' . number_format($totals['margin'], 1) . ' %' : 'Sin ventas' }}</span></div>
        <div class="pl-kpi"><small>Gastos de caja</small><strong>{{ $money($totals['expenses']) }}</strong><span>Retiros de efectivo</span></div>
        <div class="pl-kpi"><small>Pérdidas de inventario</small><strong>{{ $money($totals['losses']) }}</strong><span>Mermas y faltantes</span></div>
        <div class="pl-kpi is-main {{ $net >= 0 ? 'is-profit' : 'is-loss' }}"><small>{{ $net >= 0 ? 'Ganancia neta' : 'Pérdida neta' }}</small><strong>{{ $money($net) }}</strong><span>Bruta − gastos − pérdidas</span></div>
    </section>

    @if($estimated || $missing_cost || $untracked_items > 0)
        <div class="glass-card p-3 text-xs text-white/70" role="note">
            @if($estimated)<p>⚠ Parte del costo es estimado: algunas unidades no tienen el costo de su lote y se valoraron con el costo de referencia del producto.</p>@endif
            @if($missing_cost)<p>⚠ Hay productos vendidos sin ningún costo registrado: su ganancia aparece completa. Anota su costo en Inventario para afinar el resultado.</p>@endif
            @if($untracked_items > 0)<p>ℹ {{ $untracked_items }} {{ $untracked_items === 1 ? 'producto vendido no controla' : 'productos vendidos no controlan' }} stock, por eso no tienen costo de lote.</p>@endif
        </div>
    @endif

    <div class="pl-columns">
        <section class="glass-card overflow-hidden" aria-labelledby="pl-days">
            <h2 id="pl-days" class="pl-title">Resultado por día</h2>
            <div class="overflow-x-auto">
                <table class="pl-table">
                    <thead>
                        <tr><th scope="col">Día</th><th scope="col" class="r">Ventas</th><th scope="col" class="r">Ingresos</th><th scope="col" class="r">Costo</th><th scope="col" class="r">G. bruta</th><th scope="col" class="r">Gastos</th><th scope="col" class="r">Pérdidas</th><th scope="col" class="r">G. neta</th></tr>
                    </thead>
                    <tbody>
                        @foreach(array_reverse($days, true) as $date => $d)
                            <tr class="{{ $d['sales'] === 0 && $d['expenses'] == 0 && $d['losses'] == 0 ? 'is-empty' : '' }}">
                                <th scope="row">{{ $day($date) }}</th>
                                <td class="r">{{ $d['sales'] }}</td>
                                <td class="r">{{ $money($d['income']) }}</td>
                                <td class="r">{{ $money($d['cost']) }}</td>
                                <td class="r">{{ $money($d['gross']) }}@if($d['margin'] !== null)<small>{{ number_format($d['margin'], 0) }}%</small>@endif</td>
                                <td class="r">{{ $money($d['expenses']) }}</td>
                                <td class="r">{{ $money($d['losses']) }}</td>
                                <td class="r pl-net {{ $d['net'] >= 0 ? 'is-profit' : 'is-loss' }}">
                                    {{ $money($d['net']) }}
                                    <span class="pl-bar" aria-hidden="true"><i style="width: {{ round(abs($d['net']) * 100 / $biggest) }}%"></i></span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th scope="row">Total</th><td class="r">{{ $totals['sales'] }}</td><td class="r">{{ $money($totals['income']) }}</td><td class="r">{{ $money($totals['cost']) }}</td><td class="r">{{ $money($totals['gross']) }}</td><td class="r">{{ $money($totals['expenses']) }}</td><td class="r">{{ $money($totals['losses']) }}</td><td class="r pl-net {{ $net >= 0 ? 'is-profit' : 'is-loss' }}">{{ $money($net) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <aside class="pl-side">
            <section class="glass-card p-4" aria-labelledby="pl-out">
                <h2 id="pl-out" class="pl-title !p-0 !border-0 !mb-3">Egresos del período</h2>
                <dl class="pl-list">
                    <div><dt>Compras de mercadería</dt><dd>{{ $money($totals['purchases']) }}</dd></div>
                    <div><dt>Gastos de caja</dt><dd>{{ $money($totals['expenses']) }}</dd></div>
                    <div class="is-total"><dt>Total egresos</dt><dd>{{ $money($totals['outflow']) }}</dd></div>
                </dl>
                <p class="pl-note">Las compras no restan a la ganancia: se vuelven costo cuando vendes esa mercadería.</p>
            </section>

            @if($store->hasPos() && $store->hasStorefront() && ($sources['pos']['count'] || $sources['online']['count']))
                <section class="glass-card p-4" aria-labelledby="pl-src">
                    <h2 id="pl-src" class="pl-title !p-0 !border-0 !mb-3">Ventas por origen</h2>
                    <dl class="pl-list">
                        <div><dt>Punto de venta <small>({{ $sources['pos']['count'] }})</small></dt><dd>{{ $money($sources['pos']['income']) }}</dd></div>
                        <div><dt>Tienda virtual <small>({{ $sources['online']['count'] }})</small></dt><dd>{{ $money($sources['online']['income']) }}</dd></div>
                    </dl>
                    <p class="pl-note">Un solo resultado para todo el negocio: el stock, los costos y los clientes son los mismos.</p>
                </section>
            @endif

            @if($expenses)
                <section class="glass-card p-4" aria-labelledby="pl-exp">
                    <h2 id="pl-exp" class="pl-title !p-0 !border-0 !mb-3">Gastos por motivo</h2>
                    <dl class="pl-list">@foreach(array_slice($expenses, 0, 8) as $row)<div><dt>{{ $row['reason'] }}</dt><dd>{{ $money($row['amount']) }}</dd></div>@endforeach</dl>
                </section>
            @endif

            @if($methods)
                <section class="glass-card p-4" aria-labelledby="pl-methods">
                    <h2 id="pl-methods" class="pl-title !p-0 !border-0 !mb-3">Cobros por medio de pago</h2>
                    <dl class="pl-list">@foreach($methods as $method => $amount)<div><dt>{{ $methodLabels[$method] ?? ucfirst($method) }}</dt><dd>{{ $money($amount) }}</dd></div>@endforeach</dl>
                </section>
            @endif

            @if($documents)
                <section class="glass-card p-4" aria-labelledby="pl-docs">
                    <h2 id="pl-docs" class="pl-title !p-0 !border-0 !mb-3">Comprobantes emitidos</h2>
                    <dl class="pl-list">@foreach($documents as $type => $row)<div><dt>{{ $docLabels[$type] ?? $type }} <small>({{ $row['count'] }})</small></dt><dd>{{ $money($row['total']) }}</dd></div>@endforeach</dl>
                </section>
            @endif
        </aside>
    </div>

    @if($losses)
        <section class="glass-card overflow-hidden" aria-labelledby="pl-loss">
            <h2 id="pl-loss" class="pl-title">Pérdidas de inventario</h2>
            <div class="overflow-x-auto">
                <table class="pl-table">
                    <thead><tr><th scope="col">Día</th><th scope="col">Producto</th><th scope="col" class="r">Unidades</th><th scope="col" class="r">Costo</th><th scope="col">Motivo</th></tr></thead>
                    <tbody>
                        @foreach($losses as $loss)
                            <tr><th scope="row">{{ $day($loss['date']) }}</th><td>{{ $loss['product'] }}</td><td class="r">{{ $loss['quantity'] }}</td><td class="r">{{ $money($loss['cost']) }}</td><td>{{ $loss['reason'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
