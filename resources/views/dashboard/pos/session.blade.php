@extends('layouts.dashboard')
@section('title', 'Turno de caja')
@section('page_title', '🧾 ' . $session->register->name)
@section('content')
@php
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $lima = fn ($at) => $at?->copy()->setTimezone('America/Lima')->locale('es');
@endphp
<div class="w-full max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-xs text-white/60">
            Abierta el {{ $lima($session->opened_at)->isoFormat('D [de] MMMM, H:mm') }}{{ $session->opener ? ' por ' . $session->opener->name : '' }}
            @if($session->closed_at) · cerrada el {{ $lima($session->closed_at)->isoFormat('D [de] MMMM, H:mm') }}{{ $session->closer ? ' por ' . $session->closer->name : '' }}@endif
        </p>
        <div class="flex items-center gap-3 text-xs">
            @if($session->isOpen())
                <a href="{{ route('dashboard.pos.index', ['caja' => $session->register_id]) }}" class="text-tribio-cyan underline">← Seguir vendiendo</a>
                <a href="{{ route('dashboard.pos.sessions.close', $session->id) }}" class="btn-secondary !py-1 text-xs">Cerrar caja</a>
            @else
                <a href="{{ route('dashboard.pos.sessions.index') }}" class="text-tribio-cyan underline">← Turnos</a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="stat-card"><p class="text-white/50 text-xs">Ventas</p><p class="text-lg sm:text-xl font-black text-white mt-1">{{ $summary['sales'] ?? 0 }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Vendido</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($summary['sales_total'] ?? 0) }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Efectivo esperado</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($summary['expected_cash'] ?? 0) }}</p><p class="text-[11px] text-white/40">con {{ $money($session->opening_float) }} de sencillo</p></div>
        <div class="stat-card">
            <p class="text-white/50 text-xs">{{ $session->isOpen() ? 'Estado' : 'Contado' }}</p>
            @if($session->isOpen())
                <p class="text-lg sm:text-xl font-black text-emerald-300 mt-1">Abierta</p>
            @else
                <p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($session->counted_cash) }}</p>
                <p class="text-[11px] {{ (float) $session->difference == 0.0 ? 'text-emerald-300' : 'text-red-300' }}">{{ (float) $session->difference == 0.0 ? 'Cuadró' : ((float) $session->difference > 0 ? 'Sobró ' : 'Faltó ') . $money(abs((float) $session->difference)) }}</p>
            @endif
        </div>
    </div>

    <div class="glass-card p-4 sm:p-5">
        @if($byStaff->isNotEmpty())
            <h3 class="text-white font-bold text-sm mb-2">Por persona</h3>
            <ul class="mb-4 space-y-1">
                @foreach($byStaff as $row)
                    <li class="text-xs text-white/70">{{ $row->staff?->name ?? 'Persona eliminada' }} · {{ $row->sales }} {{ (int) $row->sales === 1 ? 'venta' : 'ventas' }} · <strong class="text-white">{{ $money($row->amount) }}</strong></li>
                @endforeach
            </ul>
        @endif
        <h3 class="text-white font-bold text-sm mb-2">Por medio de pago</h3>
        <dl class="grid grid-cols-2 sm:grid-cols-5 gap-2">
            @foreach($methods as $key => $label)
                <div class="rounded-xl border border-white/10 bg-white/5 p-2"><dt class="text-[11px] text-white/50">{{ $label }}</dt><dd class="text-sm text-white font-semibold">{{ $money($summary['methods'][$key] ?? 0) }}</dd></div>
            @endforeach
        </dl>
        @if(($summary['credit'] ?? 0) > 0 || ($summary['collected'] ?? 0) > 0 || ($summary['refunds'] ?? 0) > 0)
            <p class="text-xs text-white/70 mt-3">Vendido al crédito: <strong class="text-white">{{ $money($summary['credit']) }}</strong> · Cobranzas de deudas: <strong class="text-white">{{ $money($summary['collected']) }}</strong> · Devuelto a clientes: <strong class="text-white">{{ $money($summary['refunds'] ?? 0) }}</strong></p>
        @endif
        @if($session->movements->isNotEmpty())
            <h4 class="text-white/80 font-semibold text-xs mt-4 mb-1">Ingresos y retiros de efectivo</h4>
            <ul class="space-y-1">
                @foreach($session->movements as $movement)
                    <li class="text-xs text-white/70">{{ $lima($movement->created_at)->isoFormat('H:mm') }} · {{ $movement->type === 'in' ? '＋' : '−' }} {{ $money($movement->amount) }} · {{ $movement->reason }}{{ $movement->user ? ' · ' . $movement->user->name : '' }}</li>
                @endforeach
            </ul>
        @endif
        @if($session->notes)<p class="text-xs text-white/60 mt-3">Notas: {{ $session->notes }}</p>@endif
    </div>

    <div class="glass-card divide-y divide-white/10 overflow-hidden">
        @forelse($orders as $order)
            <div class="flex items-center justify-between gap-3 p-3 flex-wrap">
                <div class="min-w-0">
                    <a href="{{ route('dashboard.pedidos.show', $order->id) }}" class="text-sm text-white font-semibold hover:underline">{{ $order->order_number }}</a>
                    <p class="text-[11px] text-white/50">{{ $lima($order->created_at)->isoFormat('H:mm') }}{{ $order->relationLoaded('staff') && $order->staff ? ' · ' . $order->staff->name : '' }}{{ $order->relationLoaded('approver') && $order->approver ? ' (autorizó ' . $order->approver->name . ')' : '' }} · {{ $order->customer_name }} · {{ $order->payments->where('status', 'paid')->map(fn ($p) => $methods[$p->method] ?? 'Pago')->unique()->implode(' + ') ?: 'Sin cobro' }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-white font-bold">{{ $money($order->total) }}</span>
                    <a href="{{ route('dashboard.pos.receipt', $order->id) }}" target="_blank" rel="noopener" class="text-xs text-tribio-cyan underline">Ticket</a>
                    @if(app(\App\Services\Pos\PosSchema::class)->extrasReady() && !in_array($order->status, ['cancelled', 'refunded'], true))<a href="{{ route('dashboard.returns.create', $order->id) }}" class="text-xs text-tribio-cyan underline">Devolver</a>@endif
                    @if((float) ($order->returned_total ?? 0) > 0)<span class="badge badge-gray">Devuelto S/ {{ number_format((float) $order->returned_total, 2) }}</span>@endif
                </div>
            </div>
        @empty
            <p class="p-6 text-center text-sm text-white/60">No hubo ventas en este turno.</p>
        @endforelse
    </div>
    @if($orders->hasPages())<div>{{ $orders->links() }}</div>@endif
</div>
@endsection
