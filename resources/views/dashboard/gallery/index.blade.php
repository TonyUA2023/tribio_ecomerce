@extends('layouts.dashboard')
@section('title','Galería') @section('page_title','🖼️ Galería')
@section('content')
<div class="glass-card p-6 mb-6">
    <form method="POST" action="{{ route('dashboard.galeria.store') }}" enctype="multipart/form-data" class="space-y-4">@csrf
        <x-image-picker name="images[]" label="Agregar fotos a la galería" :multiple="true" :max-mb="5" save-label="Subir fotos a la galería" />
    </form>
    <p class="text-xs text-white/50 mt-4">⭐ Marca las fotos que quieres usar en el banner principal (Hero) de tu tienda — se actualiza solo, en el orden en que las subiste.</p>
</div>
@if($items->isEmpty())
<div class="glass-card p-12 text-center"><p class="text-5xl mb-4">🖼️</p><p class="text-white/40">Sin imágenes aún. Sube fotos de tu negocio.</p></div>
@else
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
    @foreach($items as $item)
    @php $isHero = $item->type === 'hero'; @endphp
    <div class="relative group rounded-2xl overflow-hidden aspect-square bg-white/5">
        <img src="{{ $item->image_url }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
        @if($isHero)
            <span class="absolute top-2 left-2 bg-tribio-cyan text-black text-[10px] font-bold px-2 py-1 rounded-full shadow z-10">⭐ En el Hero</span>
        @endif
        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2">
            <form method="POST" action="{{ route('dashboard.galeria.toggle-hero', $item) }}">@csrf
                <button type="submit" class="{{ $isHero ? 'bg-white/20 text-white' : 'bg-tribio-cyan text-black' }} px-3 py-1.5 rounded-lg text-xs font-bold">
                    {{ $isHero ? '✕ Quitar del Hero' : '⭐ Usar en Hero' }}
                </button>
            </form>
            <form method="POST" action="{{ route('dashboard.galeria.destroy', $item) }}" onsubmit="return confirm('¿Eliminar?')">@csrf @method('DELETE')
                <button type="submit" class="bg-red-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Eliminar</button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
