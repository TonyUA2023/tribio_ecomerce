@extends('templates.minimal-light.layout')

@section('title', $product->name . ' | ' . $store->name)

@section('content')
<div class="bg-[#FDF8EF] min-h-screen text-[#1A1A1A]" x-data="{ mobileMenuOpen: false, searchOpen: false }">
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
                            <span class=" font-semibold text-2xl md:text-3xl tracking-wide text-[#1A1A1A]">{{ $store->name }}</span>
                        @endif
                    </a>
                </div>

                <div class="flex-1 flex items-center justify-end space-x-4 md:space-x-5">
                    <button @click="$dispatch('open-customer-modal')" class="text-[#1A1A1A] hover:text-[#C8A68B] transition hidden md:block" title="Mi Cuenta / Pedidos">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </button>
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
            <!-- Product Gallery (Main + Thumbnails) -->
            @php
                $allImages = $product->all_images;
            @endphp
            <div class="space-y-4" x-data="{ activeImage: '{{ $product->image_url }}' }">
                <!-- Main Featured Image -->
                <div class="relative bg-white rounded-3xl overflow-hidden aspect-[4/5] shadow-sm border border-stone-200/60">
                    <img :src="activeImage" alt="{{ $product->name }}" class="w-full h-full object-cover transition-all duration-300">
                    @if($product->resolveComparePrice() > $product->resolvePrice())
                        <div class="absolute top-6 left-6 bg-red-600 text-white text-sm font-bold px-4 py-1.5 rounded-full uppercase tracking-wide">Oferta</div>
                    @endif
                </div>

                <!-- Secondary Image Thumbnails -->
                @if(count($allImages) > 1)
                <div class="flex items-center gap-3 overflow-x-auto pb-2 scrollbar-thin">
                    @foreach($allImages as $imgUrl)
                    <button type="button" @click="activeImage = '{{ $imgUrl }}'" 
                            class="relative w-20 h-20 rounded-xl overflow-hidden flex-shrink-0 border-2 transition-all cursor-pointer bg-white"
                            :class="activeImage === '{{ $imgUrl }}' ? 'border-[#C8A68B] shadow-md scale-105 ring-2 ring-[#C8A68B]/30' : 'border-stone-200/70 opacity-70 hover:opacity-100'">
                        <img src="{{ $imgUrl }}" class="w-full h-full object-cover">
                    </button>
                    @endforeach
                </div>
                @endif
            </div>

            <!-- Product Details -->
            <div class="flex flex-col justify-center" x-data="{
                hasVariants: {{ $product->has_variants ? 'true' : 'false' }},
                trackStock: {{ $product->track_stock ? 'true' : 'false' }},
                basePrice: {{ $product->resolvePrice() }},
                baseComparePrice: {{ $product->resolveComparePrice() }},
                baseStock: {{ (int) $product->stock }},
                currencySymbol: '{{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }}',
                options: {{ Js::from($product->variant_options ?? []) }},
                variants: {{ Js::from($product->activeVariants->map(function($v) {
                    return [
                        'id' => $v->id,
                        'title' => $v->title,
                        'attributes' => $v->attributes ?? [],
                        'sku' => $v->sku,
                        'price' => $v->resolvePrice(),
                        'compare_price' => $v->resolveComparePrice(),
                        'stock' => (int) $v->stock,
                        'image' => $v->image_url,
                    ];
                })) }},
                selectedAttributes: {},
                quantity: 1,
                init() {
                    if (this.hasVariants && this.options.length > 0) {
                        this.options.forEach(opt => {
                            if (opt.values && opt.values.length > 0) {
                                this.selectedAttributes[opt.name] = opt.values[0];
                            }
                        });
                    }
                },
                get currentVariant() {
                    if (!this.hasVariants || this.variants.length === 0) return null;
                    return this.variants.find(v => {
                        if (!v.attributes) return false;
                        return Object.entries(this.selectedAttributes).every(([key, val]) => v.attributes[key] === val);
                    }) || null;
                },
                get currentPrice() {
                    if (this.currentVariant && this.currentVariant.price > 0) {
                        return this.currentVariant.price;
                    }
                    return this.basePrice;
                },
                get currentComparePrice() {
                    if (this.currentVariant && this.currentVariant.compare_price > 0) {
                        return this.currentVariant.compare_price;
                    }
                    return this.baseComparePrice;
                },
                get isOutOfStock() {
                    if (!this.trackStock) return false;
                    if (this.hasVariants) {
                        return !this.currentVariant || this.currentVariant.stock <= 0;
                    }
                    return this.baseStock <= 0;
                },
                addToCart() {
                    if (this.isOutOfStock) return;
                    if (!window.TribioCart) return;

                    const variant = this.currentVariant ? {
                        id: this.currentVariant.id,
                        title: this.currentVariant.title,
                        attributes: this.currentVariant.attributes,
                        sku: this.currentVariant.sku
                    } : null;

                    for (let i = 0; i < this.quantity; i++) {
                        window.TribioCart.add(
                            {{ $product->id }},
                            '{{ addslashes($product->name) }}',
                            this.currentPrice,
                            '{{ $product->image_url }}',
                            variant
                        );
                    }
                    document.getElementById('cartDrawer').style.display = 'flex';
                }
            }">
                <nav class="flex text-sm text-gray-500 mb-6 font-medium">
                    <a href="{{ route('store.show', $store->slug) }}" class="hover:text-[#C8A68B]">Inicio</a>
                    <span class="mx-2">/</span>
                    <a href="{{ route('store.catalog', $store->slug) }}" class="hover:text-[#C8A68B]">Catálogo</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-800">{{ $product->name }}</span>
                </nav>

                <h1 class="text-3xl md:text-5xl text-[#1A1A1A] mb-4 font-bold">{{ $product->name }}</h1>
                
                <div class="flex items-center gap-4 mb-6">
                    <span class="text-2xl md:text-3xl font-bold text-[#C8A68B]" x-text="currencySymbol + ' ' + Number(currentPrice).toFixed(2)">
                        {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($product->resolvePrice(), 2) }}
                    </span>
                    <template x-if="currentComparePrice > currentPrice">
                        <span class="text-lg text-gray-400 line-through" x-text="currencySymbol + ' ' + Number(currentComparePrice).toFixed(2)">
                            {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($product->resolveComparePrice(), 2) }}
                        </span>
                    </template>
                </div>

                <div class="prose prose-sm text-gray-600 mb-6 leading-relaxed">
                    {!! nl2br(e($product->description)) !!}
                </div>

                {{-- Variant Selector --}}
                <template x-if="hasVariants && options.length > 0">
                    <div class="space-y-4 mb-8 pt-4 border-t border-gray-100">
                        <template x-for="opt in options" :key="opt.name">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                                        <span x-text="opt.name"></span>: <span class="text-[#C8A68B] font-semibold" x-text="selectedAttributes[opt.name]"></span>
                                    </label>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="val in opt.values" :key="val">
                                        <button type="button"
                                                @click="selectedAttributes[opt.name] = val"
                                                :class="selectedAttributes[opt.name] === val 
                                                    ? 'bg-[#1A1A1A] text-white border-[#1A1A1A] shadow-xs' 
                                                    : 'bg-white text-gray-700 border-gray-200 hover:border-gray-400'"
                                                class="px-4 py-2 text-xs font-bold rounded-xl border transition-all">
                                            <span x-text="val"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Stock badge for variant --}}
                        <template x-if="trackStock">
                            <div class="text-xs pt-1">
                                <template x-if="!isOutOfStock">
                                    <span class="text-emerald-700 font-semibold flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span>Disponible en stock</span>
                                    </span>
                                </template>
                                <template x-if="isOutOfStock">
                                    <span class="text-rose-600 font-semibold flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                        <span>Agotado en esta combinación</span>
                                    </span>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <div class="flex gap-4 mb-10">
                    <div class="flex items-center bg-gray-50 border border-gray-200 rounded-xl px-2">
                        <button type="button" @click="if(quantity > 1) quantity--" class="w-10 h-12 flex items-center justify-center text-gray-600 hover:text-[#C8A68B] text-xl font-bold">-</button>
                        <input type="number" x-model="quantity" min="1" class="w-12 h-12 text-center bg-transparent border-none focus:ring-0 text-lg font-bold text-[#1A1A1A]">
                        <button type="button" @click="quantity++" class="w-10 h-12 flex items-center justify-center text-gray-600 hover:text-[#C8A68B] text-xl font-bold">+</button>
                    </div>
                    <button type="button" 
                            @click="addToCart()" 
                            :disabled="isOutOfStock"
                            :class="isOutOfStock ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : 'bg-[#1A1A1A] hover:bg-[#C8A68B] text-white shadow-lg'"
                            class="flex-1 font-bold text-lg rounded-xl transition-colors flex items-center justify-center gap-2 py-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        <span x-text="isOutOfStock ? 'Agotado' : 'Añadir al carrito'"></span>
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
    <div class="bg-[#FDF8EF] py-20 border-t border-stone-200/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl  font-bold text-[#1A1A1A] mb-12 text-center">También te podría gustar</h2>
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
    <!-- Footer -->
    <footer class="bg-[#FDF8EF] border-t border-stone-200/60 py-8 text-center mt-12">
        <p class="text-xs font-semibold text-gray-500 tracking-wider">
            Impulsado por <span class="text-[#1A1A1A] font-bold">Tribio</span>
        </p>
    </footer>
</div>

<!-- Cart Drawer -->
@include('templates.minimal-light.cart-drawer')

@endsection
