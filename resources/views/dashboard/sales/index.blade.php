@extends('layouts.dashboard')
@section('title', 'Ventas')
@section('page_title', '🧾 Ventas')
@section('content')
@php
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $lima = fn ($at) => $at?->copy()->setTimezone('America/Lima')->locale('es');
    $keep = fn (array $over = []) => array_filter(array_merge(request()->except('page'), $over), fn ($v) => $v !== null && $v !== '');
    $typeLabels = ['' => 'Todos'] + \App\Services\Pos\SalesDocuments::labels();
@endphp
<div class="sl-app w-full max-w-6xl mx-auto space-y-4">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Todas tus ventas de caja. Busca por número de comprobante, cliente o DNI/RUC.</p>
        <a href="{{ route('dashboard.pos.index') }}" class="btn-primary">Nueva venta</a>
    </div>

    <form method="GET" action="{{ route('dashboard.ventas.index') }}" class="sl-search" role="search">
        <x-dashboard-icon name="search" class="sl-search-icon"/>
        <label for="sl-q" class="sr-only">Buscar venta</label>
        <input id="sl-q" type="search" name="q" value="{{ $term }}" class="input-field" placeholder="B001-12, nombre del cliente, DNI o RUC…" autocomplete="off">
        @foreach(['periodo', 'tipo', 'medio', 'desde', 'hasta'] as $field)@if(request($field))<input type="hidden" name="{{ $field }}" value="{{ request($field) }}">@endif @endforeach
        <button type="submit" class="btn-secondary">Buscar</button>
    </form>

    <div class="flex items-center gap-2 flex-wrap" aria-label="Filtros">
        @foreach($periods as $key => $label)
            <a href="{{ route('dashboard.ventas.index', $keep(['periodo' => $key, 'desde' => null, 'hasta' => null])) }}" class="pos-chip {{ $period === $key ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach
        <form method="GET" action="{{ route('dashboard.ventas.index') }}" class="pl-range" style="margin-left: 0">
            @foreach(['q', 'tipo', 'medio'] as $field)@if(request($field))<input type="hidden" name="{{ $field }}" value="{{ request($field) }}">@endif @endforeach
            <label class="sr-only" for="sl-from">Desde</label><input id="sl-from" type="date" name="desde" value="{{ $from?->toDateString() }}">
            <span aria-hidden="true">→</span>
            <label class="sr-only" for="sl-to">Hasta</label><input id="sl-to" type="date" name="hasta" value="{{ $to?->toDateString() }}">
            <button type="submit" class="pos-chip {{ $period === null ? 'is-active' : '' }}">Ver</button>
        </form>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
        @if($docsReady)
            @foreach($typeLabels as $key => $label)
                <a href="{{ route('dashboard.ventas.index', $keep(['tipo' => $key ?: null])) }}" class="pos-chip {{ ($type ?? '') === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
            <span class="text-white/30" aria-hidden="true">|</span>
        @endif
        <a href="{{ route('dashboard.ventas.index', $keep(['medio' => null])) }}" class="pos-chip {{ $method === null ? 'is-active' : '' }}">Todo pago</a>
        @foreach($methods as $key => $label)
            <a href="{{ route('dashboard.ventas.index', $keep(['medio' => $key])) }}" class="pos-chip {{ $method === $key ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <p class="text-sm text-white/70" role="status">
        <strong>{{ $summary['count'] }}</strong> {{ $summary['count'] === 1 ? 'venta' : 'ventas' }} · <strong>{{ $money($summary['total']) }}</strong>
        @if($summary['cancelled'] > 0)<span class="text-white/50"> · {{ $summary['cancelled'] }} {{ $summary['cancelled'] === 1 ? 'anulada' : 'anuladas' }}</span>@endif
    </p>

    <div class="glass-card divide-y divide-white/10 overflow-hidden">
        @forelse($orders as $order)
            @php $cancelled = $order->status === 'cancelled'; $paid = $order->payments->where('status', 'paid')->map(fn ($p) => $methods[$p->method] ?? 'Pago')->unique()->implode(' + '); @endphp
            <div class="sl-row {{ $cancelled ? 'is-cancelled' : '' }}">
                <div class="sl-main">
                    <a href="{{ route('dashboard.pos.receipt', $order->id) }}" target="_blank" rel="noopener" class="sl-code">{{ $order->document_code ?? $order->order_number }}</a>
                    <span class="sl-kind">{{ $order->document_label }}</span>
                    @if($cancelled)<span class="badge badge-red">Anulada</span>@elseif((float) $order->balance_due > 0.004)<span class="badge badge-gold">Debe {{ $money($order->balance_due) }}</span>@endif
                    <p class="sl-meta">{{ $lima($order->created_at)->isoFormat('ddd D MMM, H:mm') }} · {{ $order->customer_name }}@if($order->customer_document_number) · {{ $order->customer_document_type ?: 'Doc.' }} {{ $order->customer_document_number }}@endif · {{ $paid ?: 'Sin cobro' }}</p>
                </div>
                <div class="sl-side">
                    <strong class="sl-total">{{ $money($order->total) }}</strong>
                    <a href="{{ route('dashboard.pos.receipt', $order->id) }}" target="_blank" rel="noopener" class="pos-chip">Ticket</a>
                    @if($extras && !$cancelled)<a href="{{ route('dashboard.returns.create', $order->id) }}" class="pos-chip">Devolver</a>@endif
                </div>
            </div>
        @empty
            <p class="p-8 text-center text-sm text-white/60">No hay ventas con esos filtros.</p>
        @endforelse
    </div>
    @if($orders->hasPages())<div>{{ $orders->links() }}</div>@endif
</div>
@endsection
