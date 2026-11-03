@extends('layouts.dashboard')
@section('title', 'Oportunidades')
@section('page_title', '💼 Oportunidades')
@section('content')
@php
    use App\Models\Deal;
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
@endphp
<div class="w-full max-w-6xl mx-auto space-y-5">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Negocios en curso: un pedido grande, una empresa que pidió cotización, un distribuidor que evalúa unirse. Muévelos de etapa hasta ganarlos.</p>
        <a href="{{ route('dashboard.clientes.index') }}" class="text-xs text-tribio-cyan underline">← Clientes</a>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="stat-card"><p class="text-white/50 text-xs">En el embudo</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($summary['open_amount']) }}</p><p class="text-[11px] text-white/40">{{ collect($summary['stages'])->sum('count') }} abiertas</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Ganado · 90 días</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($summary['won_amount']) }}</p><p class="text-[11px] text-white/40">{{ $summary['won'] }} {{ $summary['won'] === 1 ? 'ganada' : 'ganadas' }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Perdidas · 90 días</p><p class="text-lg sm:text-xl font-black text-white mt-1">{{ $summary['lost'] }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Tasa de cierre</p><p class="text-lg sm:text-xl font-black text-white mt-1">{{ $summary['win_rate'] !== null ? $summary['win_rate'] . '%' : '—' }}</p><p class="text-[11px] text-white/40">ganadas de las cerradas</p></div>
    </div>

    <form method="POST" action="{{ route('dashboard.clientes.deals.store') }}" class="glass-card p-4 sm:p-5" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
        @csrf
        <button type="button" class="w-full flex items-center justify-between text-left" @click="open = !open" :aria-expanded="open">
            <span class="text-white font-bold text-base">+ Nueva oportunidad</span>
            <span class="text-white/40 text-sm" x-text="open ? 'Cerrar' : 'Abrir'"></span>
        </button>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end mt-3" x-show="open" x-cloak>
            <div>
                <label class="input-label" for="deal-contact">Cliente</label>
                <select id="deal-contact" name="contact_id" class="input-field">
                    @foreach($contacts as $contact)<option value="{{ $contact->id }}" @selected((int) old('contact_id') === $contact->id)>{{ $contact->name }}</option>@endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="input-label" for="deal-title">¿De qué se trata?</label>
                <input id="deal-title" name="title" class="input-field" maxlength="120" value="{{ old('title') }}" placeholder="Ej.: 50 packs para el gimnasio">
            </div>
            <div>
                <label class="input-label" for="deal-amount">Monto estimado (S/)</label>
                <input id="deal-amount" type="number" name="amount" min="0" step="0.01" class="input-field" value="{{ old('amount') }}">
            </div>
            <div>
                <label class="input-label" for="deal-date">Cierre esperado</label>
                <input id="deal-date" type="date" name="expected_close_at" class="input-field" value="{{ old('expected_close_at') }}">
            </div>
            <button type="submit" class="btn-primary text-xs sm:text-sm">Crear</button>
        </div>
        @if($contacts->isEmpty())<p class="text-xs text-white/50 mt-2" x-show="open">Primero agrega un cliente o prospecto en Clientes.</p>@endif
    </form>

    {{-- Embudo: columnas en pantalla grande, apiladas en el celular --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach(Deal::OPEN_STAGES as $stage => $label)
        <section class="glass-card p-3 sm:p-4" aria-label="{{ $label }}">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h3 class="text-white font-bold text-sm">{{ $label }} <span class="text-white/40 font-normal">{{ $summary['stages'][$stage]['count'] }}</span></h3>
                <span class="text-[11px] text-white/50 whitespace-nowrap">{{ $money($summary['stages'][$stage]['amount']) }}</span>
            </div>
            <div class="space-y-2">
                @forelse($columns[$stage] as $deal)
                    @include('dashboard.contacts._deal_card', ['deal' => $deal, 'showStage' => false])
                @empty
                    <p class="text-xs text-white/40">Nada aquí.</p>
                @endforelse
            </div>
        </section>
        @endforeach
    </div>

    @if($closed->isNotEmpty())
    <div class="glass-card p-4 sm:p-5">
        <h3 class="text-white font-bold text-base mb-3">Cerradas recientemente</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach($closed as $deal)
                @include('dashboard.contacts._deal_card', ['deal' => $deal])
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
