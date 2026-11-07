@extends('layouts.dashboard')
@section('title', 'Caja')
@section('page_title', '🧾 Caja')
@section('content')
@php $money = fn ($value) => 'S/ ' . number_format((float) $value, 2); @endphp
<div class="w-full max-w-4xl mx-auto space-y-5">
    <p class="text-sm text-white/60 max-w-2xl">Vende en tu local: abre la caja con el sencillo que tienes, cobra en efectivo, Yape, Plin o tarjeta, y al final del día cuenta el efectivo para cuadrar.</p>

    <nav class="pos-shortcuts" aria-label="Accesos de la caja">
        @if(app(\App\Services\Pos\PosSchema::class)->extrasReady())
            <x-pos-shortcut :href="route('dashboard.quotes.index')" icon="receipt" title="Proformas" hint="Cotiza y cobra después con los mismos precios"/>
            <x-pos-shortcut :href="route('dashboard.returns.index')" icon="refresh" title="Devoluciones" hint="Vuelve el producto al stock y devuelve el dinero"/>
            <x-pos-shortcut :href="route('dashboard.price-lists.index')" icon="tag" title="Listas de precios" hint="Mayorista y distribuidor"/>
        @endif
        <x-pos-shortcut :href="route('dashboard.pos.sessions.index')" icon="clock" title="Historial de turnos" hint="Aperturas, cierres y si el efectivo cuadró"/>
        @if(($staffReady ?? false) && !($kiosk ?? false))
            <x-pos-shortcut :href="route('dashboard.pos.staff.index')" icon="users" title="Personal y modo caja" hint="Cajeros con PIN y bloqueo del equipo"/>
        @endif
        @if(app(\App\Services\Pos\SalesDocuments::class)->ready() && !($kiosk ?? false))
            <x-pos-shortcut :href="route('dashboard.pos.documents.edit')" icon="receipt" title="Comprobantes" hint="RUC, boleta, factura y nota de venta"/>
        @endif
    </nav>
    @if($kiosk ?? false)
        <div class="glass-card p-3 flex items-center justify-between gap-3 flex-wrap" role="status">
            <p class="text-xs text-white/70">🔒 Modo caja{{ ($staffMember ?? null) ? ' · ' . $staffMember->name . ' (' . (\App\Services\Pos\StaffService::ROLES[$staffMember->role] ?? '') . ')' : '' }}</p>
            <div class="flex items-center gap-2 flex-wrap">
                <form method="POST" action="{{ route('dashboard.pos.lock') }}">@csrf<button type="submit" class="btn-secondary !py-1 text-xs">Bloquear / cambiar de persona</button></form>
                <form method="POST" action="{{ route('dashboard.pos.kiosk.disable') }}" class="flex items-center gap-1">@csrf
                    <label for="kiosk-exit" class="sr-only">PIN de encargado para salir</label>
                    <input id="kiosk-exit" name="pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" class="input-field !py-1 !w-24 !text-xs" placeholder="PIN encargado" required>
                    <button type="submit" class="btn-ghost !py-1 text-xs">Salir del modo caja</button>
                </form>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($registers as $register)
            <div class="glass-card p-4 sm:p-5 space-y-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0"><h3 class="text-white font-bold text-base">{{ $register->name }}</h3>@if(!empty($sedes[$register->id]))<p class="text-[11px] text-white/50 mt-0.5">📍 {{ $sedes[$register->id] }}</p>@endif</div>
                    <span class="badge {{ $register->openSession ? 'badge-green' : 'badge-gray' }}">{{ $register->openSession ? 'Abierta' : 'Cerrada' }}</span>
                </div>
                @if($session = $register->openSession)
                    <p class="text-xs text-white/60">Abierta {{ $session->opener ? 'por ' . $session->opener->name : '' }} {{ $session->opened_at->locale('es')->diffForHumans() }}, con {{ $money($session->opening_float) }} de sencillo.</p>
                    <a href="{{ route('dashboard.pos.index', ['caja' => $register->id]) }}" class="btn-primary w-full text-center">Ir a vender</a>
                @else
                    <form method="POST" action="{{ route('dashboard.pos.registers.open', $register->id) }}" class="space-y-2">
                        @csrf
                        <label class="input-label" for="float-{{ $register->id }}">Efectivo para dar vuelto (S/)</label>
                        <input id="float-{{ $register->id }}" name="opening_float" type="number" step="0.10" min="0" class="input-field" value="{{ old('opening_float', '0.00') }}" required>
                        <button type="submit" class="btn-primary w-full">Abrir caja</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('dashboard.pos.registers.store') }}" class="glass-card p-4 sm:p-5 flex items-end gap-3 flex-wrap">
        @csrf
        <div class="flex-1 min-w-[12rem]">
            <label class="input-label" for="register-name">¿Otra caja o vendedor? Agrega una caja</label>
            <input id="register-name" name="name" class="input-field" maxlength="60" placeholder="Caja 2" required>
        </div>
        <button type="submit" class="btn-secondary">Agregar</button>
    </form>

    @if($recent->isNotEmpty())
        <div class="glass-card p-4 sm:p-5">
            <h3 class="text-white font-bold text-sm mb-2">Últimos cierres</h3>
            <div class="divide-y divide-white/10">
                @foreach($recent as $closed)
                    <a href="{{ route('dashboard.pos.sessions.show', $closed->id) }}" class="flex items-center justify-between gap-3 py-2 hover:bg-white/5">
                        <span class="text-xs text-white/80">{{ $closed->register->name }} · {{ $closed->closed_at->copy()->setTimezone('America/Lima')->locale('es')->isoFormat('D MMM, H:mm') }}</span>
                        <span class="text-xs {{ (float) $closed->difference == 0.0 ? 'text-emerald-300' : 'text-red-300' }}">{{ (float) $closed->difference == 0.0 ? 'Cuadró' : ((float) $closed->difference > 0 ? 'Sobró ' : 'Faltó ') . $money(abs((float) $closed->difference)) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
