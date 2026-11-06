@extends('layouts.simple')
@section('title', 'Cómo voy hoy')
@section('top_right')<a href="{{ route('dashboard.simple.index') }}" class="sm-today is-back"><strong>Vender</strong></a>@endsection

@section('content')
@unless($ready)
    <div class="sm-notice"><strong>⏳ Estamos activando este módulo</strong><p>Falta una actualización en el servidor de Tribio.</p></div>
@else
@php
    $money = fn ($value) => (round((float) $value, 2) < 0 ? '−' : '') . 'S/ ' . number_format(abs((float) $value), 2);
    $labels = ['cash' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin'];
@endphp
<div class="sm-day">
    <h1>Cómo voy hoy</h1>
    <p class="sm-day-date">{{ ucfirst(now('America/Lima')->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p>

    <section class="sm-hero" aria-label="Resumen del día">
        <small>Vendiste</small>
        <strong>{{ $money($day['sold']) }}</strong>
        <span>{{ $day['sales'] }} {{ $day['sales'] === 1 ? 'venta' : 'ventas' }}@if($day['sales']) · promedio {{ $money($day['average']) }}@endif</span>
    </section>

    <div class="sm-pair">
        <div><small>Te costó</small><strong>{{ $money($day['cost']) }}</strong></div>
        <div class="{{ $day['profit'] >= 0 ? 'is-good' : 'is-bad' }}"><small>Te quedó</small><strong>{{ $money($day['profit']) }}</strong></div>
    </div>
    @if($day['partial'] && $day['sales'])
        <p class="sm-note">Es una estimación: a algunas ventas les falta el costo del producto. Anótalo en <a href="{{ route('dashboard.productos.index') }}">tus productos</a> para afinarla.</p>
    @endif

    <section class="sm-card" aria-labelledby="sm-how">
        <h2 id="sm-how">Cómo te pagaron</h2>
        <dl>
            @foreach($day['methods'] as $key => $method)<div><dt>{{ $method['label'] }}</dt><dd>{{ $money($method['amount']) }}</dd></div>@endforeach
            @if($day['other'] > 0)<div><dt>Otros medios</dt><dd>{{ $money($day['other']) }}</dd></div>@endif
        </dl>
    </section>

    <section class="sm-card" aria-labelledby="sm-last">
        <h2 id="sm-last">Últimas ventas</h2>
        @forelse($recent as $order)
            <div class="sm-sale">
                <span><strong>{{ $order->items->take(2)->map(fn ($i) => ($i->quantity > 1 ? $i->quantity . '× ' : '') . $i->product_name)->implode(', ') }}@if($order->items->count() > 2) +{{ $order->items->count() - 2 }}@endif</strong>
                    <small>{{ $order->created_at->copy()->setTimezone('America/Lima')->format('H:i') }}@if($order->payment_method) · {{ $labels[$order->payment_method] ?? ucfirst($order->payment_method) }}@endif</small></span>
                <b>{{ $money($order->total) }}</b>
            </div>
        @empty
            <p class="sm-note">Todavía no vendiste nada hoy.</p>
        @endforelse
        @if($recent->isNotEmpty())<a href="{{ route('dashboard.ventas.index') }}" class="sm-more-link">Ver todas mis ventas →</a>@endif
    </section>
</div>
@endunless
@endsection
