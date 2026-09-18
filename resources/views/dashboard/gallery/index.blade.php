@extends('layouts.dashboard')
@section('title','Galería') @section('page_title','🖼️ Galería')
@section('content')
<div class="glass-card p-6 mb-6">
    <form method="POST" action="{{ route('dashboard.galeria.store') }}" enctype="multipart/form-data" class="space-y-4">@csrf
        <x-image-picker name="images[]" label="Agregar fotos a la galería" :multiple="true" :max-mb="5" save-label="Subir fotos a la galería" />
    </form>
</div>
@if($items->isEmpty())
<div class="glass-card p-12 text-center"><p class="text-5xl mb-4">🖼️</p><p class="text-white/40">Sin imágenes aún. Sube fotos de tu negocio.</p></div>
@else
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
    @foreach($items as $item)
    <div class="relative group rounded-2xl overflow-hidden aspect-square bg-white/5">
        <img src="{{ $item->image_url }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
            <form method="POST" action="{{ route('dashboard.galeria.destroy', $item) }}" onsubmit="return confirm('¿Eliminar?')">@csrf @method('DELETE')
                <button type="submit" class="bg-red-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Eliminar</button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection
