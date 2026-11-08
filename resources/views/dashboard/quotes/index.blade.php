@extends('layouts.dashboard')
@section('title', 'Proformas')
@section('page_title', '📝 Proformas')
@section('content')
@php $money = fn ($value) => 'S/ ' . number_format((float) $value, 2); @endphp
<div class="w-full max-w-[1600px] mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Cotizaciones que armaste en la caja con «Guardar como proforma». Cuando el cliente acepte, cóbrala en la caja con los mismos precios.</p>
        <a href="{{ route('dashboard.pos.index') }}" class="text-xs text-tribio-cyan underline">← Caja</a>
    </div>

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('dashboard.quotes.index') }}" class="{{ $filter ? 'btn-ghost' : 'btn-secondary' }} !py-1">Todas</a>
            <a href="{{ route('dashboard.quotes.index', ['ver' => 'vigentes']) }}" class="{{ $filter === 'vigentes' ? 'btn-secondary' : 'btn-ghost' }} !py-1">Vigentes</a>
            <a href="{{ route('dashboard.quotes.index', ['ver' => 'vendidas']) }}" class="{{ $filter === 'vendidas' ? 'btn-secondary' : 'btn-ghost' }} !py-1">Vendidas</a>
        </div>
        <form method="GET" action="{{ route('dashboard.quotes.index') }}" role="search">
            @if($filter)<input type="hidden" name="ver" value="{{ $filter }}">@endif
            <label for="quote-search" class="sr-only">Buscar proforma</label>
            <input id="quote-search" name="q" value="{{ request('q') }}" class="input-field !py-1 !text-xs w-44" placeholder="Número o cliente">
        </form>
    </div>

    <div class="glass-card divide-y divide-white/10 overflow-hidden">
        @forelse($quotes as $quote)
            <div class="flex items-center justify-between gap-3 p-3 sm:p-4 flex-wrap">
                <div class="min-w-0">
                    <a href="{{ route('dashboard.quotes.show', $quote->id) }}" class="text-sm text-white font-semibold hover:underline">{{ $quote->number }}</a>
                    <span class="badge {{ $quote->state_badge }}">{{ $quote->state_label }}</span>
                    <p class="text-xs text-white/50">{{ $quote->customer_name }} · {{ count($quote->items) }} {{ count($quote->items) === 1 ? 'producto' : 'productos' }} · válida hasta {{ $quote->valid_until->format('d/m/Y') }}</p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-sm text-white font-bold whitespace-nowrap">{{ $money($quote->total) }}</span>
                    @if($quote->status === 'open')
                        @if($canSell)<a href="{{ route('dashboard.pos.index', ['proforma' => $quote->id]) }}" class="btn-primary !py-1 text-xs">Cobrar en caja</a>@endif
                        <form method="POST" action="{{ route('dashboard.quotes.cancel', $quote->id) }}" onsubmit="return confirm('¿Anular la proforma?')" class="flex items-center gap-2">@csrf<x-pos-approval id="quote-{{ $quote->id }}" class="!p-1" /><button type="submit" class="text-xs text-white/40 hover:text-red-300">Anular</button></form>
                    @elseif($quote->order_id)
                        <a href="{{ route('dashboard.pedidos.show', $quote->order_id) }}" class="text-xs text-tribio-cyan underline">Ver venta</a>
                    @endif
                </div>
            </div>
        @empty
            <p class="p-6 text-center text-sm text-white/60">{{ $filter || request('q') ? 'Ninguna proforma con ese filtro.' : 'Aún no hay proformas. En la caja, arma el pedido y pulsa «Guardar como proforma».' }}</p>
        @endforelse
    </div>
    @if(!$canSell)<p class="text-[11px] text-white/50">Para cobrar una proforma, primero abre una caja.</p>@endif
    @if($quotes->hasPages())<div>{{ $quotes->links() }}</div>@endif
</div>
@endsection
