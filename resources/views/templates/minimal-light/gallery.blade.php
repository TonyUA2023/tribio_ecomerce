@extends('templates.minimal-light.layout')

@section('title', 'Galería | ' . $store->name)

@section('content')
<div class="bg-white" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <!-- Unified Header -->
    @include('templates.minimal-light.header')

    <!-- Page Header -->
    <div class="bg-[#FDF8EF] py-10 md:py-16 text-center border-b border-gray-200">
        <h1 class="text-4xl md:text-5xl  text-[#1A1A1A] mb-4">Inspiración</h1>
        <p class="text-gray-600 max-w-2xl mx-auto px-4">Descubre cómo nuestros productos transforman espacios y estilos de vida.</p>
    </div>

    <!-- Gallery Grid -->
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 py-12 md:py-20">
        @if($galleryItems->isEmpty())
            <div class="text-center py-20 bg-gray-50 rounded-2xl border border-dashed border-gray-300">
                <h3 class="text-xl font-bold text-gray-800 mb-2">Galería en construcción</h3>
                <p class="text-gray-500">Pronto añadiremos inspiración visual.</p>
            </div>
        @else
            <!-- Masonry-like grid -->
            <div class="columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6">
                @foreach($galleryItems as $item)
                <div class="break-inside-avoid relative group rounded-2xl overflow-hidden shadow-sm bg-gray-100 cursor-pointer">
                    <img src="{{ Storage::url($item->image_path) }}" alt="{{ $item->title ?? 'Galería' }}" class="w-full h-auto object-cover transform group-hover:scale-105 transition-transform duration-700">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                        @if($item->link_url)
                            <a href="{{ $item->link_url }}" target="_blank" class="px-6 py-2 bg-white text-[#1A1A1A] font-bold rounded-full hover:bg-[#C8A68B] hover:text-white transition-colors transform translate-y-4 group-hover:translate-y-0 duration-300">
                                Ver Detalle
                            </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            
            <div class="mt-12 flex justify-center">
                {{ $galleryItems->links() }}
            </div>
        @endif
    </div>

    <!-- Footer -->
    <footer class="bg-[#FDF8EF] border-t border-stone-200/60 py-8 text-center mt-12">
        <p class="text-xs font-semibold text-gray-500 tracking-wider">
            {{ \App\Helpers\TranslationHelper::isEn() ? 'Powered by' : 'Impulsado por' }} <span class="text-[#1A1A1A] font-bold">Tribio</span>
        </p>
    </footer>
</div>

<!-- Cart Drawer -->
@include('templates.minimal-light.cart-drawer')
@endsection
