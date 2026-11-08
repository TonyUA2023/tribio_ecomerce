@extends('layouts.dashboard')
@section('title', 'Devoluciones')
@section('page_title', '↩️ Devoluciones')
@section('content')
@php $money = fn ($value) => 'S/ ' . number_format((float) $value, 2); @endphp
<div class="w-full max-w-[1600px] mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Cuando un cliente devuelve algo de una venta de caja: el producto vuelve al stock (si está en buen estado) y le devuelves el dinero, o se descuenta de lo que te debe.</p>
        <a href="{{ route('dashboard.pos.index') }}" class="text-xs text-tribio-cyan underline">← Caja</a>
    </div>

    <form method="GET" action="{{ route('dashboard.returns.index') }}" class="glass-card p-4 flex items-end gap-3 flex-wrap" role="search">
        <div class="flex-1 min-w-[12rem]">
            <label class="input-label" for="sale-number">Número de la venta (está en el ticket)</label>
            <input id="sale-number" name="venta" class="input-field" value="{{ request('venta') }}" placeholder="TRB-2026-000008 o 000008" required>
        </div>
        <button type="submit" class="btn-primary">Buscar venta</button>
        @if($notFound)<p class="w-full text-xs text-red-300" role="alert">No encontramos una venta de caja con ese número.</p>@endif
    </form>

    @if($recent->isNotEmpty())
        <div class="glass-card p-4 sm:p-5">
            <h3 class="text-white font-bold text-sm mb-2">Últimas ventas de caja</h3>
            <div class="divide-y divide-white/10">
                @foreach($recent as $order)
                    <a href="{{ route('dashboard.returns.create', $order->id) }}" class="flex items-center justify-between gap-3 py-2 hover:bg-white/5">
                        <span class="text-xs text-white/80">{{ $order->order_number }} · {{ $order->customer_name }}</span>
                        <span class="text-xs text-white font-semibold">{{ $money($order->total) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="glass-card divide-y divide-white/10 overflow-hidden">
        <h3 class="text-white font-bold text-sm p-3 sm:p-4">Devoluciones registradas</h3>
        @forelse($returns as $return)
            <a href="{{ route('dashboard.returns.show', $return->id) }}" class="flex items-center justify-between gap-3 p-3 sm:p-4 hover:bg-white/5 flex-wrap">
                <span class="min-w-0">
                    <span class="block text-sm text-white font-semibold">{{ $return->number }} <span class="text-white/50 font-normal">· venta {{ $return->order?->order_number }}</span></span>
                    <span class="block text-xs text-white/50">{{ $return->created_at->copy()->setTimezone('America/Lima')->locale('es')->isoFormat('D MMM, H:mm') }} · {{ $return->order?->customer_name }} · {{ $return->reason }}</span>
                </span>
                <span class="text-sm text-white font-bold whitespace-nowrap">{{ $money($return->total) }}</span>
            </a>
        @empty
            <p class="p-6 text-center text-sm text-white/60">Aún no hay devoluciones.</p>
        @endforelse
    </div>
    @if($returns->hasPages())<div>{{ $returns->links() }}</div>@endif
</div>
@endsection
