@extends('layouts.dashboard')
@section('title', 'Marcas')
@section('page_title', '🏷️ Marcas')
@section('content')
<div class="flex justify-between mb-4">
    <h2 class="text-white font-bold">{{ $brands->count() }} marcas</h2>
    <a href="{{ route('dashboard.marcas.create') }}" class="btn-primary">+ Nueva</a>
</div>

<div class="glass-card overflow-hidden">
    @forelse($brands as $brand)
    <div class="flex items-center gap-4 p-4 border-b hover:bg-white/3 transition-colors" style="border-color:rgba(255,255,255,0.05);">
        <span class="text-2xl">🏷️</span>
        <div class="flex-1">
            <p class="text-white font-medium">{{ $brand->name }}</p>
            <p class="text-white/40 text-xs">{{ $brand->products_count }} productos asociados</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('dashboard.marcas.edit', $brand) }}" class="btn-ghost py-1 px-3 text-xs">Editar</a>
            <form method="POST" action="{{ route('dashboard.marcas.destroy', $brand) }}" onsubmit="return confirm('¿Seguro que deseas eliminar esta marca?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-ghost py-1 px-3 text-xs text-red-400">Eliminar</button>
            </form>
        </div>
    </div>
    @empty
    <div class="text-center py-12 text-white/40">
        Sin marcas registradas. <a href="{{ route('dashboard.marcas.create') }}" class="text-tribio-cyan">Crear primera</a>
    </div>
    @endforelse
</div>
@endsection
