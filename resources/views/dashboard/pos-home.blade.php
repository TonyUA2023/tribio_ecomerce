@extends('layouts.dashboard')
@section('title', 'Inicio')
@section('page_title', '🏠 Hoy en ' . $store->name)
@section('content')
@php
    $money = fn ($value) => (round((float) $value, 2) < 0 ? '−' : '') . 'S/ ' . number_format(abs((float) $value), 2);
    $lima = fn ($at) => $at?->copy()->setTimezone('America/Lima')->locale('es');
    $totals = $week['totals'];
    $pending = collect($steps)->where('done', false)->count();
    $cash = collect($registers)->sum('cash');
@endphp
<div class="hm-app w-full max-w-6xl mx-auto space-y-5">
    <div class="hm-actions">
        @if($posReady)<a href="{{ route('dashboard.pos.index') }}" class="hm-action is-primary"><x-dashboard-icon name="pos"/><span><strong>Nueva venta</strong><small>Abrir la caja y cobrar</small></span></a>@endif
        <a href="{{ route('dashboard.productos.rapido') }}" class="hm-action"><x-dashboard-icon name="plus"/><span><strong>Agregar productos</strong><small>Tabla rápida · pega desde Excel</small></span></a>
        <a href="{{ route('dashboard.compras.create') }}" class="hm-action"><x-dashboard-icon name="truck"/><span><strong>Registrar compra</strong><small>Suma stock con su costo</small></span></a>
        <a href="{{ route('dashboard.ganancias.index') }}" class="hm-action"><x-dashboard-icon name="trend"/><span><strong>Ver ganancias</strong><small>Por día y por período</small></span></a>
    </div>

    @if($pending > 0)
        <section class="hm-start" aria-labelledby="hm-start-title">
            <h2 id="hm-start-title">Empieza en {{ $pending === 1 ? '1 paso' : $pending . ' pasos' }}</h2>
            <ol>
                @foreach($steps as $step)
                    <li class="{{ $step['done'] ? 'is-done' : '' }}">
                        <span class="hm-check" aria-hidden="true">@if($step['done'])<x-dashboard-icon name="check"/>@endif</span>
                        <span class="hm-step-text"><strong>{{ $step['title'] }}</strong>@unless($step['done'])<small>{{ $step['hint'] }}</small>@endunless</span>
                        @unless($step['done'])<a href="{{ $step['url'] }}" class="btn-primary">{{ $step['cta'] }}</a>@endunless
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="hm-kpis" aria-label="Resumen de hoy">
        <a href="{{ route('dashboard.ventas.index', ['periodo' => 'hoy']) }}" class="hm-kpi">
            <small>Ventas de hoy</small><strong>{{ $money($today['income']) }}</strong><span>{{ $today['sales'] }} {{ $today['sales'] === 1 ? 'venta' : 'ventas' }}</span>
        </a>
        <a href="{{ route('dashboard.ganancias.index', ['periodo' => 'hoy']) }}" class="hm-kpi {{ $today['gross'] >= 0 ? 'is-good' : 'is-bad' }}">
            <small>Ganancia de hoy</small><strong>{{ $money($today['gross']) }}</strong><span>{{ $today['margin'] !== null ? 'Margen ' . number_format($today['margin'], 0) . ' %' : 'Aún sin ventas' }}</span>
        </a>
        @if($posReady)
        <a href="{{ route('dashboard.pos.index') }}" class="hm-kpi">
            <small>Efectivo en caja</small><strong>{{ $registers->isEmpty() ? '—' : $money($cash) }}</strong><span>{{ $registers->isEmpty() ? 'Caja cerrada · abrir' : $registers->count() . ($registers->count() === 1 ? ' caja abierta' : ' cajas abiertas') }}</span>
        </a>
        @endif
        <a href="{{ route('dashboard.productos.index', ['stock' => 'low']) }}" class="hm-kpi {{ $lowStock > 0 ? 'is-warn' : '' }}">
            <small>Stock bajo</small><strong>{{ $lowStock }}</strong><span>{{ $lowStock === 1 ? 'producto por reponer' : 'productos por reponer' }}</span>
        </a>
        <a href="{{ route('dashboard.productos.index', ['stock' => 'out']) }}" class="hm-kpi {{ $outOfStock > 0 ? 'is-bad' : '' }}">
            <small>Agotados</small><strong>{{ $outOfStock }}</strong><span>{{ $outOfStock === 1 ? 'producto sin stock' : 'productos sin stock' }}</span>
        </a>
        @if($creditReady)
        <a href="{{ route('dashboard.receivables.index') }}" class="hm-kpi {{ $owed['total'] > 0 ? 'is-warn' : '' }}">
            <small>Por cobrar</small><strong>{{ $money($owed['total']) }}</strong><span>{{ $owed['count'] }} {{ $owed['count'] === 1 ? 'venta a crédito' : 'ventas a crédito' }}</span>
        </a>
        @endif
    </section>

    <div class="hm-columns">
        <div class="dc-section">
            <div class="dc-filters"><span>Período</span>
                @foreach(\App\Services\Reports\SalesSeries::PERIODS as $key => $label)
                    <a href="{{ route('dashboard.index', ['grafico' => $key]) }}" class="pos-chip {{ $chartPeriod === (string) $key ? 'is-active' : '' }}" @if($chartPeriod === (string) $key) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </div>
            <x-bar-chart title="Ventas del punto de venta" :days="$salesSeries['days']" :total="$salesSeries['total']" :count="$salesSeries['count']" :noun="['venta', 'ventas']" color="#2176d2"
                         :href="route('dashboard.ventas.index', ['periodo' => $chartPeriod === '7' ? '7_dias' : 'mes'])" cta="Ver ventas" empty="Aún no hay ventas en este período."/>
        </div>

        <div class="hm-side">
            <section class="glass-card hm-card" aria-labelledby="hm-restock">
                <div class="hm-card-head"><h2 id="hm-restock">Por reponer</h2><a href="{{ route('dashboard.inventario.index', ['filter' => 'low']) }}">Ver todo</a></div>
                @forelse($restock as $product)
                    <div class="hm-line">
                        <a href="{{ route('dashboard.inventario.product', $product->id) }}">{{ $product->name }}</a>
                        <span class="pos-stock {{ $product->stock <= 0 ? 'is-out' : 'is-low' }}">{{ $product->stock <= 0 ? 'Sin stock' : $product->stock . ' ' . $product->unit }}</span>
                    </div>
                @empty
                    <p class="hm-empty">Todo en orden: ningún producto por reponer. ✅</p>
                @endforelse
            </section>

            <section class="glass-card hm-card" aria-labelledby="hm-recent">
                <div class="hm-card-head"><h2 id="hm-recent">Últimas ventas</h2><a href="{{ route('dashboard.ventas.index') }}">Ver todas</a></div>
                @forelse($recent as $order)
                    <div class="hm-line">
                        <a href="{{ route('dashboard.pos.receipt', $order->id) }}" target="_blank" rel="noopener">{{ $order->document_code ?? $order->order_number }} <small>{{ $lima($order->created_at)->isoFormat('D MMM H:mm') }}</small></a>
                        <strong>{{ $money($order->total) }}</strong>
                    </div>
                @empty
                    <p class="hm-empty">Aún no hay ventas.</p>
                @endforelse
            </section>
        </div>
    </div>
</div>
@endsection
