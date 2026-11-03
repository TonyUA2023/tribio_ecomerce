@extends('layouts.dashboard')
@section('title', 'Personal de caja')
@section('page_title', '👥 Personal de caja')
@section('content')
<div class="w-full max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Las personas que atienden la caja entran con su PIN, sin usar tu cuenta. Cada venta, cobro y devolución queda con su nombre.</p>
        <a href="{{ route('dashboard.pos.index', ['cajas' => 1]) }}" class="text-xs text-tribio-cyan underline">← Caja</a>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="glass-card p-4 sm:p-5 space-y-3">
        <h3 class="text-white font-bold text-base">Modo caja en este dispositivo</h3>
        <p class="text-xs text-white/60">Úsalo en la tablet o la computadora del mostrador. Mientras esté activo solo se abre la caja (nada de productos, reportes ni configuración), cada persona entra con su PIN y la caja se bloquea sola tras {{ $idle }} minutos sin uso. Para salir se necesita el PIN de un encargado.</p>
        <form method="POST" action="{{ route('dashboard.pos.kiosk.enable') }}">
            @csrf
            <button type="submit" class="btn-primary text-xs sm:text-sm" @disabled($staff->where('is_active', true)->where('role', 'encargado')->isEmpty())>Activar modo caja aquí</button>
            @if($staff->where('is_active', true)->where('role', 'encargado')->isEmpty())<span class="text-[11px] text-amber-300 ml-2">Primero agrega un encargado.</span>@endif
        </form>
        <div class="text-[11px] text-white/50">
            <p class="font-semibold text-white/70">Un cajero necesita el PIN de un encargado para:</p>
            <p>{{ implode(' · ', $restricted) }}.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('dashboard.pos.staff.store') }}" class="glass-card p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
        @csrf
        <div class="sm:col-span-2">
            <label class="input-label" for="staff-name">Nombre</label>
            <input id="staff-name" name="name" maxlength="80" class="input-field" value="{{ old('name') }}" placeholder="Ana Torres" required>
        </div>
        <div>
            <label class="input-label" for="staff-role">Rol</label>
            <select id="staff-role" name="role" class="input-field">@foreach($roles as $key => $label)<option value="{{ $key }}" @selected(old('role') === $key)>{{ $label }}</option>@endforeach</select>
        </div>
        <div>
            <label class="input-label" for="staff-pin">PIN (4 a 6 números)</label>
            <input id="staff-pin" name="pin" type="password" inputmode="numeric" autocomplete="new-password" maxlength="6" class="input-field" required>
        </div>
        <div>
            <label class="input-label" for="staff-pin2">Repite el PIN</label>
            <input id="staff-pin2" name="pin_confirmation" type="password" inputmode="numeric" autocomplete="new-password" maxlength="6" class="input-field" required>
        </div>
        <button type="submit" class="btn-primary sm:col-span-5 sm:justify-self-start">Agregar persona</button>
    </form>

    <div class="glass-card divide-y divide-white/10 overflow-hidden">
        @forelse($staff as $person)
            <form method="POST" action="{{ route('dashboard.pos.staff.update', $person->id) }}" class="p-3 sm:p-4 flex items-end gap-3 flex-wrap {{ $person->is_active ? '' : 'opacity-60' }}">
                @csrf @method('PUT')
                <div class="min-w-[10rem] flex-1">
                    <p class="text-sm text-white font-semibold">{{ $person->name }} <span class="badge {{ $person->role === 'encargado' ? 'badge-purple' : 'badge-blue' }}">{{ $roles[$person->role] ?? $person->role }}</span>@unless($person->is_active) <span class="badge badge-gray">Inactivo</span>@endunless</p>
                    <p class="text-[11px] text-white/50">{{ $person->last_used_at ? 'Usó la caja ' . $person->last_used_at->locale('es')->diffForHumans() : 'Aún no usó la caja' }}</p>
                </div>
                <div>
                    <label class="input-label" for="role-{{ $person->id }}">Rol</label>
                    <select id="role-{{ $person->id }}" name="role" class="input-field !py-1 !text-xs">@foreach($roles as $key => $label)<option value="{{ $key }}" @selected($person->role === $key)>{{ $label }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="input-label" for="pin-{{ $person->id }}">PIN nuevo</label>
                    <input id="pin-{{ $person->id }}" name="pin" type="password" inputmode="numeric" autocomplete="new-password" maxlength="6" class="input-field !py-1 !w-24" placeholder="—">
                </div>
                <div>
                    <label class="input-label" for="pin2-{{ $person->id }}">Repite</label>
                    <input id="pin2-{{ $person->id }}" name="pin_confirmation" type="password" inputmode="numeric" autocomplete="new-password" maxlength="6" class="input-field !py-1 !w-24">
                </div>
                <label class="flex items-center gap-1 text-xs text-white/70 pb-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($person->is_active)> Activo</label>
                <button type="submit" class="btn-secondary !py-1 text-xs">Guardar</button>
            </form>
        @empty
            <p class="p-6 text-center text-sm text-white/60">Aún no agregaste personal. Empieza por un encargado (puedes ser tú, con tu propio PIN).</p>
        @endforelse
    </div>
</div>
@endsection
