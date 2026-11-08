@extends('layouts.dashboard')
@section('title', 'Ventas')
@section('page_title', '🧾 Ventas')
@section('content')
@php
    $money = fn ($value) => (round((float) $value, 2) < 0 ? '−' : '') . 'S/ ' . number_format(abs((float) $value), 2);
    $lima = fn ($at) => $at?->copy()->setTimezone('America/Lima')->locale('es');
    $keep = fn (array $over = []) => array_filter(array_merge(request()->except('page'), $over), fn ($v) => $v !== null && $v !== '');
    $typeLabels = ['' => 'Todos'] + \App\Services\Pos\SalesDocuments::labels();
    $activeFilters = ($type ? 1 : 0) + ($method ? 1 : 0) + ($branch ? 1 : 0);
    $periodLabel = $period ? ($periods[$period] ?? '') : ($from && $to ? ($from->isSameDay($to) ? $from->locale('es')->isoFormat('D MMM YYYY') : $from->locale('es')->isoFormat('D MMM') . ' – ' . $to->locale('es')->isoFormat('D MMM YYYY')) : '');
    $day = null;
@endphp
<div class="sl-app w-full max-w-[1600px] mx-auto">
    @if(session('success'))<div class="sl-flash is-ok" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="sl-flash is-bad" role="alert">{{ session('error') }}</div>@endif
    <div class="sl-head">
        <p>Ventas de tu caja que se concretaron. <span>{{ $periodLabel }}</span></p>
        <a href="{{ route('dashboard.pos.index') }}" class="btn-primary">Nueva venta</a>
    </div>

    <section class="sl-kpis" aria-label="Resumen de lo filtrado">
        <div class="sl-kpi"><small>Ventas</small><strong>{{ $summary['count'] }}</strong><span>{{ $summary['count'] ? 'ticket ' . $money($summary['average']) : 'sin ventas' }}</span></div>
        <div class="sl-kpi"><small>Vendido</small><strong>{{ $money($summary['income']) }}</strong><span>con descuentos aplicados</span></div>
        <a href="{{ $detailUrl }}" class="sl-kpi is-profit {{ $summary['gross'] < 0 ? 'is-loss' : '' }}">
            <small>Ganancia</small><strong>{{ $money($summary['gross']) }}</strong>
            <span>{{ $summary['margin'] !== null ? 'margen ' . number_format($summary['margin'], 1) . ' % · ' : '' }}ver por día →</span>
        </a>
    </section>
    @if($summary['partial'] || $summary['returned'] > 0)
        <p class="sl-note" role="note">
            @if($summary['partial'])Hay productos vendidos sin costo registrado: la ganancia real es algo menor. @endif
            @if($summary['returned'] > 0)Incluye ventas con devoluciones por {{ $money($summary['returned']) }}; el detalle exacto está en <a href="{{ $detailUrl }}">Ganancias y pérdidas</a>.@endif
        </p>
    @endif

    <form method="GET" action="{{ route('dashboard.ventas.index') }}" class="sl-search" role="search">
        <x-dashboard-icon name="search" class="sl-search-icon"/>
        <label for="sl-q" class="sr-only">Buscar venta</label>
        <input id="sl-q" type="search" name="q" value="{{ $term }}" class="input-field" placeholder="Cliente, comprobante o DNI" autocomplete="off" data-1p-ignore="true" data-lpignore="true">
        @foreach(['periodo', 'tipo', 'medio', 'desde', 'hasta'] as $field)@if(request($field))<input type="hidden" name="{{ $field }}" value="{{ request($field) }}">@endif @endforeach
    </form>

    <div class="sl-periods" aria-label="Período">
        @foreach($periods as $key => $label)
            <a href="{{ route('dashboard.ventas.index', $keep(['periodo' => $key, 'desde' => null, 'hasta' => null])) }}" class="pos-chip {{ $period === $key ? 'is-active' : '' }}" @if($period === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>

    <div x-data="{ open: {{ $activeFilters || $period === null ? 'true' : 'false' }} }" class="sl-more">
        <button type="button" class="sl-more-toggle" @click="open = !open" :aria-expanded="open">
            Más filtros @if($activeFilters)<b>{{ $activeFilters }}</b>@endif <x-dashboard-icon name="chevron" class="sl-more-caret"/>
        </button>
        <div x-show="open" x-cloak class="sl-more-body">
            <form method="GET" action="{{ route('dashboard.ventas.index') }}" class="sl-range">
                @foreach(['q', 'tipo', 'medio', 'sede'] as $field)@if(request($field))<input type="hidden" name="{{ $field }}" value="{{ request($field) }}">@endif @endforeach
                <label>Desde<input type="date" name="desde" value="{{ $from?->toDateString() }}"></label>
                <label>Hasta<input type="date" name="hasta" value="{{ $to?->toDateString() }}"></label>
                <button type="submit" class="pos-chip {{ $period === null ? 'is-active' : '' }}">Aplicar fechas</button>
            </form>
            @if($docsReady)
                <div class="sl-group"><span>Comprobante</span>
                    @foreach($typeLabels as $key => $label)<a href="{{ route('dashboard.ventas.index', $keep(['tipo' => $key ?: null])) }}" class="pos-chip {{ ($type ?? '') === $key ? 'is-active' : '' }}">{{ $label }}</a>@endforeach
                </div>
            @endif
            @if($branches->isNotEmpty())
                <div class="sl-group"><span>Sede</span>
                    <a href="{{ route('dashboard.ventas.index', $keep(['sede' => null])) }}" class="pos-chip {{ !$branch ? 'is-active' : '' }}">Todas</a>
                    @foreach($branches as $b)<a href="{{ route('dashboard.ventas.index', $keep(['sede' => $b->id])) }}" class="pos-chip {{ $branch?->id === $b->id ? 'is-active' : '' }}">{{ $b->name }}</a>@endforeach
                </div>
            @endif
            <div class="sl-group"><span>Pagó con</span>
                <a href="{{ route('dashboard.ventas.index', $keep(['medio' => null])) }}" class="pos-chip {{ $method === null ? 'is-active' : '' }}">Todo</a>
                @foreach($methods as $key => $label)<a href="{{ route('dashboard.ventas.index', $keep(['medio' => $key])) }}" class="pos-chip {{ $method === $key ? 'is-active' : '' }}">{{ $label }}</a>@endforeach
            </div>
        </div>
    </div>

    <div class="sl-list">
        @forelse($orders as $order)
            @php
                $at = $lima($order->created_at);
                $key = $at->toDateString();
                $paid = $order->payments->where('status', 'paid')->map(fn ($p) => $methods[$p->method] ?? 'Pago')->unique()->implode(' + ');
            @endphp
            @if($day !== $key)
                @php $day = $key; @endphp
                <h2 class="sl-day">{{ $at->isToday() ? 'Hoy · ' : ($at->isYesterday() ? 'Ayer · ' : '') }}{{ ucfirst($at->isoFormat('dddd D [de] MMMM')) }}</h2>
            @endif
            <div class="sl-row">
                <div class="sl-main">
                    <a href="{{ route('dashboard.pos.receipt', $order->id) }}" target="_blank" rel="noopener" class="sl-code">{{ $order->document_code ?? $order->order_number }}</a>
                    <span class="sl-kind">{{ $order->document_label }}</span>
                    @if((float) $order->balance_due > 0.004)<span class="badge badge-gold">Debe {{ $money($order->balance_due) }}</span>@endif
                    <p class="sl-meta">{{ $at->format('H:i') }} · {{ $order->customer_name }}@if($order->customer_document_number) · {{ $order->customer_document_type ?: 'Doc.' }} {{ $order->customer_document_number }}@endif · {{ $paid ?: 'Sin cobro' }}</p>
                </div>
                <div class="sl-side">
                    <strong class="sl-total">{{ $money($order->total) }}</strong>
                    <span class="sl-actions">
                        @php $sun = $electronic[$order->id] ?? null; @endphp
                        @if($sun && $sun['state'] !== 'internal')
                            @if($sun['doc'])<a href="{{ route('dashboard.pos.receipt', $order->id) }}" target="_blank" rel="noopener" class="sl-sunat is-{{ $sun['doc']->status }}">SUNAT: {{ $sun['doc']->label() }}</a>
                            @elseif($sun['can'])<form method="POST" action="{{ route('dashboard.pos.documents.submit', $order->id) }}" class="inline">@csrf<button type="submit" class="sl-sunat is-unsent">Enviar a SUNAT</button></form>
                            @else<a href="{{ route('dashboard.pos.receipt', $order->id) }}" target="_blank" rel="noopener" class="sl-sunat is-unsent" title="{{ $sun['why'] }}">Sin enviar a SUNAT</a>@endif
                        @endif
                        <a href="{{ route('dashboard.pos.receipt', $order->id) }}" target="_blank" rel="noopener">Ticket</a>
                        @if($extras)<a href="{{ route('dashboard.returns.create', $order->id) }}">Devolver</a>@endif
                    </span>
                </div>
            </div>
        @empty
            <p class="sl-empty">No hay ventas {{ $period === 'hoy' && !$term && !$activeFilters ? 'hoy todavía' : 'con esos filtros' }}.</p>
        @endforelse
    </div>
    @if($orders->hasPages())<div class="mt-4">{{ $orders->links() }}</div>@endif

    @if($hasStorefront)
        <p class="sl-foot">¿Buscas los pedidos de tu tienda virtual? Están en <a href="{{ route('dashboard.pedidos.index') }}">Pedidos</a>.</p>
    @endif
</div>
@endsection
