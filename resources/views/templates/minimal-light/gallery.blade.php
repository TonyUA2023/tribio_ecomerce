@extends('templates.minimal-light.layout')

@section('title', 'Galería | ' . $store->name)

@section('content')
<div class="bg-white" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <!-- Header -->
    <header class="bg-[#FDF8EF] border-b border-gray-200/50 shadow-sm">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 py-4 md:py-5">
            <div class="flex justify-between items-center">
                <div class="flex-1 flex items-center space-x-4">
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center text-xs font-bold text-gray-800 tracking-wider">
                            {{ request()->cookie('user_country') === 'US' ? 'USD' : 'PEN' }} <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="flex-1 text-center">
                    <a href="{{ route('store.show', $store->slug) }}" class="inline-block">
                        @if($store->logo_path)
                            <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-10 md:h-12 w-auto mx-auto object-contain">
                        @else
                            <span class="font-serif font-semibold text-2xl md:text-3xl tracking-wide text-[#1A1A1A]">{{ $store->name }}</span>
                        @endif
                    </a>
                </div>

                <div class="flex-1 flex items-center justify-end space-x-4 md:space-x-5">
                    <button @click="searchOpen = true" class="text-[#1A1A1A] hover:text-[#C8A68B] transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                    <button onclick="document.getElementById('cartDrawer').style.display='flex'" class="text-[#1A1A1A] hover:text-[#C8A68B] transition relative">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <span data-cart-count class="absolute -top-1.5 -right-2 bg-red-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">0</span>
                    </button>
                </div>
            </div>
            
            <nav class="hidden md:flex justify-center space-x-10 mt-6 pb-2">
                <a href="{{ route('store.show', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Home</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Shop</a>
                <a href="{{ route('store.gallery', $store->slug) }}" class="text-[#C8A68B] font-bold text-sm transition">Gallery</a>
                <a href="{{ route('store.contact', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Contact</a>
            </nav>
        </div>
        
        <!-- Search Overlay -->
        <div x-show="searchOpen" style="display: none;" 
             class="absolute top-0 inset-x-0 bg-white border-b border-gray-100 shadow-2xl z-50 p-6 md:p-10">
            <div class="max-w-4xl mx-auto relative">
                <form action="{{ route('store.catalog', $store->slug) }}" method="GET" class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400 absolute left-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" name="q" placeholder="Buscar productos..." class="w-full pl-14 pr-12 py-4 text-xl font-serif border-none rounded-full bg-gray-50 focus:ring-0" autofocus>
                </form>
                <button @click="searchOpen = false" type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <div class="bg-[#FDF8EF] py-10 md:py-16 text-center border-b border-gray-200">
        <h1 class="text-4xl md:text-5xl font-serif text-[#1A1A1A] mb-4">Inspiración</h1>
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
</div>

<!-- Re-include Cart Drawer (Just like catalog) -->
@include('templates.minimal-light.store', ['only_cart_drawer' => true])
@endsection
