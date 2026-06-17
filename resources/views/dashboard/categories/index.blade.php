@extends('layouts.dashboard')
@section('title','Categorías') @section('page_title','🗂️ Categorías')
@section('content')
<div class="flex justify-between mb-4"><h2 class="text-white font-bold">{{ $categories->count() }} categorías</h2><a href="{{ route('dashboard.categorias.create') }}" class="btn-primary">+ Nueva</a></div>
<div class="glass-card overflow-hidden">
    @forelse($categories as $cat)
    <div class="flex items-center gap-4 p-4 border-b hover:bg-white/3 transition-colors" style="border-color:rgba(255,255,255,0.05);">
        <span class="text-2xl">{{ $cat->icon ?? '🗂️' }}</span>
        <div class="flex-1"><p class="text-white font-medium">{{ $cat->name }}</p><p class="text-white/40 text-xs">{{ $cat->active_products_count }} productos</p></div>
        <a href="{{ route('dashboard.categorias.edit', $cat) }}" class="btn-ghost py-1 px-3 text-xs">Editar</a>
        <form method="POST" action="{{ route('dashboard.categorias.destroy', $cat) }}" onsubmit="return confirm('¿Eliminar?')">@csrf @method('DELETE')<button type="submit" class="btn-ghost py-1 px-3 text-xs text-red-400">Eliminar</button></form>
    </div>
    @empty
    <div class="text-center py-12 text-white/40">Sin categorías. <a href="{{ route('dashboard.categorias.create') }}" class="text-tribio-cyan">Crear primera</a></div>
    @endforelse
</div>
@endsection
