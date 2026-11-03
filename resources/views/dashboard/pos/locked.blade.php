@extends('layouts.dashboard')
@section('title', 'Caja bloqueada')
@section('page_title', '🔒 Caja')
@section('content')
<div class="w-full max-w-sm mx-auto space-y-4" x-data="{ pin: '', add(d) { if (this.pin.length < 6) this.pin += d; }, back() { this.pin = this.pin.slice(0, -1); } }">
    @if($errors->any())
        <div class="glass-card p-3 text-center" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('dashboard.pos.unlock') }}" class="glass-card p-5 space-y-4 text-center" x-ref="form">
        @csrf
        @if($caja)<input type="hidden" name="caja" value="{{ $caja }}">@endif
        <p class="text-white font-bold text-lg">{{ $store->name }}</p>
        <p class="text-sm text-white/60">Ingresa tu PIN para usar la caja.</p>
        <label for="unlock-pin" class="sr-only">PIN</label>
        <input id="unlock-pin" name="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" class="input-field !text-2xl text-center tracking-[0.5em]"
               x-model="pin" autofocus required @keydown.enter.prevent="$refs.form.submit()">
        <div class="grid grid-cols-3 gap-2" aria-hidden="true">
            @foreach([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
                <button type="button" tabindex="-1" class="btn-secondary !py-3 text-lg" @click="add('{{ $digit }}')">{{ $digit }}</button>
            @endforeach
            <button type="button" tabindex="-1" class="btn-ghost !py-3" @click="back()">⌫</button>
            <button type="button" tabindex="-1" class="btn-secondary !py-3 text-lg" @click="add('0')">0</button>
            <button type="submit" tabindex="-1" class="btn-primary !py-3">Entrar</button>
        </div>
    </form>

    <details class="glass-card p-4">
        <summary class="text-xs text-white/60 cursor-pointer">Salir del modo caja (encargado)</summary>
        <form method="POST" action="{{ route('dashboard.pos.kiosk.disable') }}" class="flex items-end gap-2 mt-3">
            @csrf
            <div class="flex-1">
                <label class="input-label" for="exit-pin">PIN de un encargado</label>
                <input id="exit-pin" name="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" class="input-field" required>
            </div>
            <button type="submit" class="btn-secondary">Salir</button>
        </form>
    </details>
</div>
@endsection
