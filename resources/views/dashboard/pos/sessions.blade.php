@extends('layouts.dashboard')
@section('title', 'Turnos de caja')
@section('page_title', '🧾 Turnos de caja')
@section('content')
@php
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $lima = fn ($at) => $at?->copy()->setTimezone('America/Lima')->locale('es');
@endphp
<div class="w-full max-w-[1600px] mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60">Cada apertura y cierre de caja, con lo vendido y si el efectivo cuadró.</p>
        <a href="{{ route('dashboard.pos.index', ['cajas' => 1]) }}" class="text-xs text-tribio-cyan underline">← Caja</a>
    </div>

    <div class="glass-card divide-y divide-white/10 overflow-hidden">
        @forelse($sessions as $session)
            <a href="{{ route('dashboard.pos.sessions.show', $session->id) }}" class="flex items-center justify-between gap-3 p-3 sm:p-4 hover:bg-white/5 flex-wrap">
                <span class="min-w-0">
                    <span class="block text-sm text-white font-semibold">{{ $session->register->name }} · {{ $lima($session->opened_at)->isoFormat('ddd D MMM, H:mm') }}{{ $session->closed_at ? ' – ' . $lima($session->closed_at)->isoFormat('H:mm') : '' }}</span>
                    <span class="block text-xs text-white/50">{{ $session->orders_count }} {{ $session->orders_count === 1 ? 'venta' : 'ventas' }} · {{ $money($session->orders_sum_total) }}{{ $session->opener ? ' · ' . $session->opener->name : '' }}</span>
                </span>
                @if($session->isOpen())
                    <span class="badge badge-green">Abierta</span>
                @elseif((float) $session->difference == 0.0)
                    <span class="badge badge-green">Cuadró</span>
                @else
                    <span class="badge badge-red">{{ (float) $session->difference > 0 ? 'Sobró' : 'Faltó' }} {{ $money(abs((float) $session->difference)) }}</span>
                @endif
            </a>
        @empty
            <p class="p-6 text-center text-sm text-white/60">Aún no abriste ninguna caja.</p>
        @endforelse
    </div>
    @if($sessions->hasPages())<div>{{ $sessions->links() }}</div>@endif
</div>
@endsection
