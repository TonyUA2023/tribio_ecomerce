@extends('layouts.dashboard')
@section('title', 'Nuevo cliente')
@section('page_title', '👥 Clientes')
@section('content')
<div class="w-full max-w-3xl mx-auto">
    <form method="POST" action="{{ route('dashboard.clientes.store') }}" class="space-y-5">
        @csrf
        <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
            <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
                <a href="{{ route('dashboard.clientes.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a Clientes" title="Volver a Clientes">←</a>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Clientes</p>
                    <p class="text-white font-bold text-sm">Nuevo cliente</p>
                </div>
                <button type="submit" class="btn-primary flex-shrink-0 text-xs sm:text-sm">Guardar</button>
            </div>
        </div>

        @if($errors->any())
            <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
        @endif

        <div class="glass-card p-4 sm:p-6">
            <p class="text-xs text-white/50 mb-4">Para prospectos que aún no te compran: alguien que preguntó por WhatsApp, un contacto de una feria. Cuando compre, sus pedidos se unen solos a esta ficha por su celular o correo.</p>
            @include('dashboard.contacts._form')
            <div class="mt-4">
                <label class="input-label" for="ct-first-note">Primera nota <span class="text-white/40 font-normal">(opcional)</span></label>
                <textarea id="ct-first-note" name="first_note" rows="2" maxlength="2000" class="input-field" placeholder="Ej.: preguntó por el batido de vainilla, le interesa para bajar de peso">{{ old('first_note') }}</textarea>
            </div>
        </div>
    </form>
</div>
@endsection
