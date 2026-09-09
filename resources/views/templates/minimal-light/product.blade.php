@extends('templates.minimal-light.layout')

@section('title', $product->name . ' | ' . $store->name)

@section('content')
<div class="bg-white" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <!-- Header (Same as store.blade.php) -->
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
        </div>
    </header>

    <!-- Product Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-20">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20">
            <!-- Product Image -->
            <div class="relative bg-gray-100 rounded-3xl overflow-hidden aspect-[4/5] shadow-sm">
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                @if($product->resolveComparePrice() > $product->resolvePrice())
                    <div class="absolute top-6 left-6 bg-red-600 text-white text-sm font-bold px-4 py-1.5 rounded-full uppercase tracking-wide">Oferta</div>
                @endif
            </div>

            <!-- Product Details -->
            <div class="flex flex-col justify-center">
                <nav class="flex text-sm text-gray-500 mb-6 font-medium">
                    <a href="{{ route('store.show', $store->slug) }}" class="hover:text-[#C8A68B]">Inicio</a>
                    <span class="mx-2">/</span>
                    <a href="{{ route('store.catalog', $store->slug) }}" class="hover:text-[#C8A68B]">Catálogo</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-800">{{ $product->name }}</span>
                </nav>

                <h1 class="text-3xl md:text-5xl font-serif text-[#1A1A1A] mb-4">{{ $product->name }}</h1>
                
                <div class="flex items-center gap-4 mb-8">
                    <span class="text-2xl md:text-3xl font-bold text-[#C8A68B]">
                        {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($product->resolvePrice(), 2) }}
                    </span>
                    @if($product->resolveComparePrice() > $product->resolvePrice())
                        <span class="text-lg text-gray-400 line-through">
                            {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($product->resolveComparePrice(), 2) }}
                        </span>
                    @endif
                </div>

                <div class="prose prose-sm text-gray-600 mb-10 leading-relaxed">
                    {!! nl2br(e($product->description)) !!}
                </div>

                <div class="flex gap-4 mb-12" x-data="{ quantity: 1 }">
                    <div class="flex items-center bg-gray-50 border border-gray-200 rounded-xl px-2">
                        <button @click="if(quantity > 1) quantity--" class="w-10 h-12 flex items-center justify-center text-gray-600 hover:text-[#C8A68B] text-xl font-bold">-</button>
                        <input type="number" x-model="quantity" min="1" class="w-12 h-12 text-center bg-transparent border-none focus:ring-0 text-lg font-bold text-[#1A1A1A]">
                        <button @click="quantity++" class="w-10 h-12 flex items-center justify-center text-gray-600 hover:text-[#C8A68B] text-xl font-bold">+</button>
                    </div>
                    <button @click="if(window.TribioCart){ for(let i=0; i<quantity; i++) window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->resolvePrice() }}, '{{ $product->image_url }}'); document.getElementById('cartDrawer').style.display='flex'; }" 
                            class="flex-1 bg-[#1A1A1A] hover:bg-[#C8A68B] text-white font-bold text-lg rounded-xl transition-colors shadow-lg flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        Añadir al carrito
                    </button>
                </div>

                <div class="space-y-4 pt-8 border-t border-gray-100">
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <svg class="w-5 h-5 text-[#C8A68B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"></path></svg>
                        <span>Pago 100% seguro y garantizado.</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-gray-600">
                        <svg class="w-5 h-5 text-[#C8A68B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span>Envíos rápidos a nivel nacional e internacional.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    @if($relatedProducts->count() > 0)
    <div class="bg-gray-50 py-20 border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-serif font-bold text-[#1A1A1A] mb-12 text-center">También te podría gustar</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8">
                @foreach($relatedProducts as $related)
                <div class="group block relative cursor-pointer" onclick="window.location='{{ route('store.product', ['slug' => $store->slug, 'product' => $related->slug]) }}'">
                    <div class="relative w-full aspect-[4/5] mb-4 bg-white rounded-xl overflow-hidden shadow-sm group-hover:shadow-lg transition-all duration-300">
                        <img src="{{ $related->image_url }}" alt="{{ $related->name }}" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                    </div>
                    <div class="text-left px-2">
                        <h3 class="text-[#1A1A1A] font-semibold text-sm truncate mb-1">{{ $related->name }}</h3>
                        <p class="text-[#C8A68B] font-bold text-sm">
                            {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($related->resolvePrice(), 2) }}
                        </p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Re-include Cart Drawer (Just like catalog) -->
@include('templates.minimal-light.store', ['only_cart_drawer' => true])

@endsection
