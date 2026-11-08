@extends('layouts.dashboard')
@section('title', 'Cerrar caja')
@section('page_title', '🧾 Cerrar ' . $session->register->name)
@push('head')<script defer src="/pos/offline.js?v=4"></script>@endpush
@section('content')
@php $money = fn ($value) => 'S/ ' . number_format((float) $value, 2); @endphp
<div class="w-full max-w-[1600px] mx-auto space-y-5" x-data="{ counted: @js(old('counted_cash', '')), expected: {{ $summary['expected_cash'] }}, waiting: 0 }"
     x-init="window.addEventListener('load', async () => { if (window.TribioPosOffline) waiting = (await window.TribioPosOffline.queue()).filter(i => i.session === {{ $session->id }} && !i.error).length; })">
    <div class="glass-card p-4" role="alert" x-show="waiting > 0" x-cloak>
        <p class="text-sm text-amber-200" x-text="'Este equipo tiene ' + waiting + (waiting === 1 ? ' venta' : ' ventas') + ' sin conexión que aún no se enviaron. Vuelve a la caja con internet para enviarlas antes de cerrar.'"></p>
    </div>
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-xl">Cuenta el efectivo que hay en la caja y escribe el total. Comparamos con lo que debería haber.</p>
        <a href="{{ route('dashboard.pos.index', ['caja' => $session->register_id]) }}" class="text-xs text-tribio-cyan underline">← Seguir vendiendo</a>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="stat-card"><p class="text-white/50 text-xs">Ventas</p><p class="text-lg sm:text-xl font-black text-white mt-1">{{ $summary['sales'] }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Vendido</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($summary['sales_total']) }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Sencillo inicial</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($session->opening_float) }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Efectivo esperado</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($summary['expected_cash']) }}</p></div>
    </div>

    @if($profit)
        <div class="stat-card flex items-center justify-between gap-4 flex-wrap">
            <div>
                <p class="text-white/50 text-xs">Ganancia del turno</p>
                <p class="text-xl font-black mt-1 whitespace-nowrap {{ $profit['gross'] >= 0 ? 'text-emerald-300' : 'text-red-300' }}">{{ $money($profit['gross']) }}@if($profit['margin'] !== null) <span class="text-xs font-semibold text-white/50">· margen {{ number_format($profit['margin'], 1) }} %</span>@endif</p>
                <p class="text-[11px] text-white/40">Vendido {{ $money($profit['income']) }} − costo {{ $money($profit['cost']) }}@if($profit['partial']) · hay productos sin costo registrado: la ganancia real es algo menor @endif</p>
            </div>
            <a href="{{ route('dashboard.ganancias.index', ['periodo' => 'hoy']) }}" class="pos-chip"><x-dashboard-icon name="trend"/>Ver ganancias</a>
        </div>
    @endif

    <div class="glass-card p-4 sm:p-5">
        <h3 class="text-white font-bold text-sm mb-2">Cobrado por medio de pago</h3>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @foreach($methods as $key => $label)
                <div class="rounded-xl border border-white/10 bg-white/5 p-2"><dt class="text-[11px] text-white/50">{{ $label }}</dt><dd class="text-sm text-white font-semibold">{{ $money($summary['methods'][$key] ?? 0) }}</dd></div>
            @endforeach
        </dl>
        @if(($summary['credit'] ?? 0) > 0 || ($summary['collected'] ?? 0) > 0 || ($summary['refunds'] ?? 0) > 0)
            <p class="text-xs text-white/70 mt-3">Vendido al crédito: <strong class="text-white">{{ $money($summary['credit']) }}</strong> · Cobranzas de deudas: <strong class="text-white">{{ $money($summary['collected']) }}</strong> · Devuelto a clientes: <strong class="text-white">{{ $money($summary['refunds'] ?? 0) }}</strong> (ya incluidos arriba según el medio de pago)</p>
        @endif
        <p class="text-xs text-white/50 mt-3">Efectivo esperado = sencillo inicial + ventas en efectivo{{ $summary['cash_in'] > 0 ? ' + ingresos (' . $money($summary['cash_in']) . ')' : '' }}{{ $summary['cash_out'] > 0 ? ' − retiros (' . $money($summary['cash_out']) . ')' : '' }}. Revisa Yape, Plin y tarjeta en sus apps.</p>
        @if($session->movements->isNotEmpty())
            <ul class="mt-3 space-y-1">
                @foreach($session->movements as $movement)
                    <li class="text-xs text-white/70">{{ $movement->type === 'in' ? '＋' : '−' }} {{ $money($movement->amount) }} · {{ $movement->reason }}{{ $movement->user ? ' · ' . $movement->user->name : '' }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <form method="POST" action="{{ route('dashboard.pos.sessions.close.store', $session->id) }}" class="glass-card p-4 sm:p-5 space-y-3"
          onsubmit="return confirm('¿Cerrar la caja? Ya no se podrá vender en este turno.')">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
            <div>
                <label class="input-label" for="counted-cash">Efectivo contado (S/)</label>
                <input id="counted-cash" name="counted_cash" type="number" step="0.10" min="0" class="input-field !text-lg" x-model="counted" required autofocus>
            </div>
            <p class="text-sm font-semibold" x-show="counted !== ''"
               :class="Math.abs(counted - expected) < 0.005 ? 'text-emerald-300' : 'text-red-300'"
               x-text="Math.abs(counted - expected) < 0.005 ? 'Cuadra ✔' : (counted > expected ? 'Sobran S/ ' : 'Faltan S/ ') + Math.abs(counted - expected).toFixed(2)"></p>
        </div>
        <div>
            <label class="input-label" for="close-notes">Notas (opcional)</label>
            <textarea id="close-notes" name="notes" rows="2" maxlength="500" class="input-field" placeholder="Por ejemplo: faltan S/ 2 de un vuelto">{{ old('notes') }}</textarea>
        </div>
        <x-pos-approval id="close" />
        <button type="submit" class="btn-primary" :disabled="waiting > 0" :class="waiting > 0 && 'opacity-50 cursor-not-allowed'">Cerrar caja</button>
    </form>
</div>
@endsection
