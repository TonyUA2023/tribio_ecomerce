@extends('layouts.dashboard')
@section('title', 'Conteo físico')
@section('page_title', '🧮 Conteo físico')
@section('content')
<div class="w-full max-w-[1600px] mx-auto space-y-5">

@unless($ready)
    <div class="glass-card p-4 sm:p-5">
        <p class="text-white font-bold text-sm">⏳ Estamos activando este módulo</p>
        <p class="text-xs text-white/50 mt-1">Falta una actualización en el servidor de Tribio. Apenas esté lista podrás contar tu inventario aquí; no tienes que hacer nada.</p>
    </div>
@else
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Cuenta lo que tienes en tus estantes desde el celular. Al terminar ves lo que falta o sobra, en unidades y en soles, y corriges el stock con un clic.</p>
        <a href="{{ route('dashboard.inventario.index') }}" class="text-xs text-tribio-cyan underline">← Inventario</a>
    </div>

    <form method="POST" action="{{ route('dashboard.inventario.conteos.store') }}" class="glass-card p-4 sm:p-6 space-y-4">
        @csrf
        <h3 class="text-white font-bold text-base">Nuevo conteo</h3>
        @if($errors->any())
            @foreach($errors->all() as $error)<p class="text-xs text-red-300" role="alert">{{ $error }}</p>@endforeach
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="input-label" for="count-category">¿Qué vas a contar?</label>
                <select id="count-category" name="category_id" class="input-field">
                    <option value="">Todos los productos con control de stock</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id') === $category->id)>Solo {{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="input-label" for="count-notes">Nota <span class="text-white/40 font-normal">(opcional)</span></label>
                <input id="count-notes" name="notes" class="input-field" maxlength="500" value="{{ old('notes') }}" placeholder="Ej.: cierre de mes, almacén del fondo">
            </div>
        </div>
        <label class="flex items-start gap-2 text-sm text-white/70">
            <input type="checkbox" name="blind" value="1" class="mt-1" @checked(old('blind', true))>
            <span>Conteo a ciegas <span class="block text-[11px] text-white/40">No muestra cuánto dice el sistema mientras cuentas: así el conteo es más honesto.</span></span>
        </label>
        <button type="submit" class="btn-primary text-xs sm:text-sm">Empezar a contar</button>
    </form>

    <div class="space-y-2">
        @forelse($counts as $count)
        <a href="{{ route('dashboard.inventario.conteos.show', $count->id) }}" class="glass-card p-3.5 sm:p-4 flex items-center justify-between gap-3 hover:border-tribio-cyan/40 transition">
            <div class="min-w-0">
                <p class="text-sm text-white font-semibold">{{ $count->number }} <span class="text-white/50 font-normal">· {{ $count->category?->name ?? 'Todos los productos' }}</span></p>
                <p class="text-[11px] text-white/40">{{ $count->counted_count }} de {{ $count->items_count }} contados · {{ $count->created_at->locale('es')->isoFormat('D MMM YYYY') }}</p>
            </div>
            <div class="text-right flex-shrink-0">
                @if($count->status === 'applied' && $count->variance_value !== null)
                    <p class="text-sm font-semibold whitespace-nowrap {{ (float) $count->variance_value < 0 ? 'text-red-400' : 'text-white' }}">S/ {{ number_format((float) $count->variance_value, 2) }}</p>
                @endif
                <span class="badge {{ $count->status_badge }}">{{ $count->status_label }}</span>
            </div>
        </a>
        @empty
            <div class="glass-card p-6 text-center"><p class="text-white font-semibold">Aún no haces conteos.</p></div>
        @endforelse
    </div>
    <div>{{ $counts->links() }}</div>
@endunless
</div>
@endsection
