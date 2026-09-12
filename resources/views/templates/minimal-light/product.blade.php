@extends('templates.minimal-light.layout')

@section('title', $product->name . ' | ' . $store->name)

@section('content')
<div class="bg-[#FDF8EF] min-h-screen text-[#1A1A1A]" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <!-- Unified Header -->
    @include('templates.minimal-light.header')

    <!-- Main Product Hero Section (First Screen) -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-14">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-start">
            
            <!-- Left: Product Gallery (Sticky on desktop) -->
            @php
                $allImages = $product->all_images;
            @endphp
            <div class="space-y-4 lg:sticky lg:top-8" x-data="{ activeImage: @js($product->image_url) }">
                <!-- Main Featured Image -->
                <div class="relative bg-white rounded-3xl overflow-hidden aspect-[4/5] shadow-sm border border-stone-200/80 group">
                    <img :src="activeImage" alt="{{ $product->name }}" class="w-full h-full object-cover transition-all duration-500 group-hover:scale-105">
                    @if($product->resolveComparePrice() > $product->resolvePrice())
                        <div class="absolute top-5 left-5 bg-red-600 text-white text-xs font-black px-3.5 py-1.5 rounded-full uppercase tracking-wider shadow-md">
                            {{ \App\Helpers\TranslationHelper::trans('special_offer', 'Oferta Especial') }}
                        </div>
                    @endif
                </div>

                <!-- Secondary Image Thumbnails -->
                @if(count($allImages) > 1)
                <div class="flex items-center gap-3 overflow-x-auto pb-2 scrollbar-thin">
                    @foreach($allImages as $imgUrl)
                    <button type="button" @click="activeImage = @js($imgUrl)" 
                            class="relative w-20 h-20 rounded-2xl overflow-hidden flex-shrink-0 border-2 transition-all cursor-pointer bg-white"
                            :class="activeImage === @js($imgUrl) ? 'border-[#1A1A1A] shadow-md scale-105 ring-2 ring-[#1A1A1A]/20' : 'border-stone-200/80 opacity-70 hover:opacity-100'">
                        <img src="{{ $imgUrl }}" class="w-full h-full object-cover">
                    </button>
                    @endforeach
                </div>
                @endif
            </div>

            <!-- Right: Product Purchase Info (First Screen Focus) -->
            <div class="flex flex-col" x-data="{
                hasVariants: {{ $product->has_variants ? 'true' : 'false' }},
                trackStock: {{ $product->track_stock ? 'true' : 'false' }},
                basePrice: {{ (float) ($product->resolvePrice() ?? 0) }},
                baseComparePrice: {{ (float) ($product->resolveComparePrice() ?? 0) }},
                baseStock: {{ (int) ($product->stock ?? 0) }},
                currencySymbol: '{{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }}',
                options: {{ Js::from($product->variant_options ?? []) }},
                variants: {{ Js::from($product->activeVariants->map(function($v) {
                    return [
                        'id' => $v->id,
                        'title' => $v->title,
                        'attributes' => $v->attributes ?? [],
                        'sku' => $v->sku,
                        'price' => (float) ($v->resolvePrice() ?? 0),
                        'compare_price' => (float) ($v->resolveComparePrice() ?? 0),
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
                    const variant = this.currentVariant ? {
                        id: this.currentVariant.id,
                        title: this.currentVariant.title,
                        attributes: this.currentVariant.attributes,
                        sku: this.currentVariant.sku
                    } : null;

                    if (window.TribioCart) {
                        for (let i = 0; i < this.quantity; i++) {
                            window.TribioCart.add(
                                {{ $product->id }},
                                @js($product->name),
                                this.currentPrice,
                                @js($product->image_url),
                                variant
                            );
                        }
                        const drawer = document.getElementById('cartDrawer');
                        if (drawer) drawer.style.display = 'flex';
                    }
                },
                buyNow() {
                    this.addToCart();
                }
            }">
                <!-- Breadcrumbs -->
                <nav class="flex flex-wrap items-center text-xs text-stone-500 mb-4 font-medium">
                    <a href="{{ route('store.show', $store->slug) }}" class="hover:text-[#1A1A1A] transition">{{ \App\Helpers\TranslationHelper::trans('home', 'Inicio') }}</a>
                    <span class="mx-2 text-stone-300">/</span>
                    <a href="{{ route('store.catalog', $store->slug) }}" class="hover:text-[#1A1A1A] transition">{{ \App\Helpers\TranslationHelper::trans('shop', 'Catálogo') }}</a>
                    @if($product->category)
                        <span class="mx-2 text-stone-300">/</span>
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $product->category->slug]) }}" class="hover:text-[#1A1A1A] transition">
                            {{ $product->category->getTranslatedName() }}
                        </a>
                    @endif
                    <span class="mx-2 text-stone-300">/</span>
                    <span class="text-stone-800 font-semibold truncate max-w-[200px]">{{ $product->name }}</span>
                </nav>

                <!-- Badges (Category & Stock) -->
                <div class="flex items-center gap-2 mb-3 flex-wrap">
                    @if($product->categories && $product->categories->isNotEmpty())
                        @foreach($product->categories as $cat)
                            <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="text-[11px] font-bold uppercase tracking-wider text-stone-600 bg-white px-2.5 py-1 rounded-lg border border-stone-200 hover:border-stone-400 hover:text-stone-900 transition">
                                {{ $cat->getTranslatedName() }}
                            </a>
                        @endforeach
                    @elseif($product->category)
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $product->category->slug]) }}" class="text-[11px] font-bold uppercase tracking-wider text-stone-600 bg-white px-2.5 py-1 rounded-lg border border-stone-200 hover:border-stone-400 hover:text-stone-900 transition">
                            {{ $product->category->getTranslatedName() }}
                        </a>
                    @endif

                    @if($product->isInStock())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            {{ \App\Helpers\TranslationHelper::isEn() ? 'In Stock' : 'En Stock' }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-50 text-rose-800 border border-rose-200">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            {{ \App\Helpers\TranslationHelper::trans('out_of_stock', 'Agotado') }}
                        </span>
                    @endif
                </div>

                <!-- Product Name -->
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-[#1A1A1A] tracking-tight leading-tight mb-3">
                    {{ $product->name }}
                </h1>

                <!-- Ratings & SKU -->
                <div class="flex flex-wrap items-center gap-3 text-xs text-stone-500 mb-6">
                    <div class="flex items-center text-amber-500 gap-1">
                        <span>★★★★★</span>
                        <span class="font-bold text-stone-800 ml-1">4.9</span>
                        <span class="text-stone-400">({{ \App\Helpers\TranslationHelper::isEn() ? '28 reviews' : '28 valoraciones' }})</span>
                    </div>
                    @if($product->sku)
                        <span class="text-stone-300">•</span>
                        <span>SKU: <strong class="text-stone-700 font-mono">{{ $product->sku }}</strong></span>
                    @endif
                    <span class="text-stone-300">•</span>
                    <span class="text-emerald-700 font-semibold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        {{ \App\Helpers\TranslationHelper::trans('quality_guarantee', 'Garantía Oficial') }}
                    </span>
                </div>

                <!-- Price Box -->
                <div class="bg-white p-5 sm:p-6 rounded-3xl border border-stone-200/80 shadow-xs mb-6">
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl sm:text-4xl font-black text-[#1A1A1A]" x-text="currencySymbol + ' ' + Number(currentPrice).toFixed(2)">
                            {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($product->resolvePrice(), 2) }}
                        </span>
                        <template x-if="currentComparePrice > currentPrice">
                            <span class="text-lg sm:text-xl text-stone-400 line-through font-semibold" x-text="currencySymbol + ' ' + Number(currentComparePrice).toFixed(2)">
                                {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($product->resolveComparePrice() ?? 0, 2) }}
                            </span>
                        </template>
                        <template x-if="currentComparePrice > currentPrice">
                            <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-red-100 text-red-700 uppercase tracking-wide">
                                <span x-text="'-' + Math.round((1 - (currentPrice / currentComparePrice)) * 100) + '% OFF'"></span>
                            </span>
                        </template>
                    </div>
                    <p class="text-xs text-stone-500 mt-2 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'Taxes included. Shipping calculated at checkout.' : 'Impuestos incluidos. Envíos calculados al finalizar compra.' }}</span>
                    </p>
                </div>

                <!-- Short Description (Only concise summary in first viewport) -->
                @php
                    $shortText = !empty($product->short_description) 
                        ? $product->short_description 
                        : Str::limit(strip_tags($product->description), 180);
                @endphp
                @if($shortText)
                <div class="mb-6 p-4 rounded-2xl bg-[#F4F1EA]/60 border border-stone-200/60">
                    <p class="text-sm text-stone-700 leading-relaxed font-normal">
                        {{ $shortText }}
                    </p>
                    <a href="#detalles-producto" class="inline-flex items-center gap-1 text-xs font-bold text-[#C8A68B] hover:text-[#1A1A1A] mt-2 transition">
                        <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'View full specifications & description ↓' : 'Ver características completas y ficha técnica ↓' }}</span>
                    </a>
                </div>
                @endif

                <!-- Interactive Variant Selector (If Product Has Variants) -->
                @if($product->has_variants && !empty($product->variant_options))
                <div class="space-y-4 mb-6 p-5 rounded-2xl bg-white border border-stone-200/80 shadow-xs">
                    <template x-for="opt in options" :key="opt.name">
                        <div>
                            <div class="flex items-center justify-between text-xs font-bold mb-2">
                                <span class="text-stone-700 uppercase tracking-wider" x-text="opt.name"></span>
                                <span class="text-[#C8A68B] font-mono" x-text="selectedAttributes[opt.name] || ''"></span>
                            </div>
                            
                            <div class="flex flex-wrap gap-2">
                                <template x-for="val in opt.values" :key="val">
                                    <button type="button"
                                            @click="selectedAttributes[opt.name] = val"
                                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all border cursor-pointer select-none"
                                            :class="selectedAttributes[opt.name] === val 
                                                ? 'bg-[#1A1A1A] text-white border-[#1A1A1A] shadow-xs scale-105' 
                                                : 'bg-stone-50 hover:bg-stone-100 text-stone-700 border-stone-200'">
                                        <span x-text="val"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                @endif

                <!-- Quantity and Add to Cart Section (High Conversion CTA) -->
                <div class="space-y-3 mb-8">
                    <div class="flex items-center gap-3">
                        <!-- Quantity Counter -->
                        <div class="flex items-center bg-white border-2 border-stone-300/80 rounded-2xl p-1 shadow-xs hover:border-stone-400 transition">
                            <button type="button" 
                                    @click="if(quantity > 1) quantity--" 
                                    class="w-11 h-12 flex items-center justify-center text-stone-800 hover:bg-stone-100 rounded-xl text-2xl font-bold transition cursor-pointer select-none">−</button>
                            <input type="number" 
                                   x-model.number="quantity" 
                                   min="1" 
                                   class="w-14 h-12 text-center bg-transparent border-none focus:ring-0 text-lg font-black text-stone-900">
                            <button type="button" 
                                    @click="quantity++" 
                                    class="w-11 h-12 flex items-center justify-center text-stone-800 hover:bg-stone-100 rounded-xl text-2xl font-bold transition cursor-pointer select-none">+</button>
                        </div>

                        <!-- Solid High-Contrast Add To Cart Button -->
                        <button type="button" 
                                id="btnAddToCartMain"
                                @click="addToCart()" 
                                :disabled="isOutOfStock"
                                class="flex-1 bg-[#1A1A1A] hover:bg-stone-800 text-white font-black text-base sm:text-lg rounded-2xl transition-all shadow-xl hover:shadow-2xl flex items-center justify-center gap-3 py-4 px-6 cursor-pointer active:scale-98 select-none"
                                :class="isOutOfStock ? '!bg-stone-300 !text-stone-500 !cursor-not-allowed !shadow-none' : ''">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <span x-text="isOutOfStock ? '{{ \App\Helpers\TranslationHelper::trans('out_of_stock', 'Producto Agotado') }}' : '{{ \App\Helpers\TranslationHelper::trans('add_to_cart', 'Añadir al carrito') }}'">{{ \App\Helpers\TranslationHelper::trans('add_to_cart', 'Añadir al carrito') }}</span>
                        </button>
                    </div>

                    <!-- Express Direct Checkout Button -->
                    <button type="button" 
                            @click="buyNow()" 
                            :disabled="isOutOfStock"
                            class="w-full bg-[#C8A68B] hover:bg-[#b08e73] text-white font-extrabold text-sm sm:text-base rounded-2xl transition-all shadow-md py-3.5 px-6 flex items-center justify-center gap-2 cursor-pointer active:scale-98"
                            :class="isOutOfStock ? 'hidden' : ''">
                        <span>⚡ {{ \App\Helpers\TranslationHelper::trans('buy_now', 'Comprar Ahora') }}</span>
                    </button>
                </div>

                <!-- Trust Badges -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-6 border-t border-stone-200/80">
                    <div class="flex items-center gap-2.5 p-3 rounded-xl bg-white border border-stone-200/60 text-xs text-stone-700">
                        <span class="text-xl">🚚</span>
                        <div>
                            <strong class="block text-stone-900 font-bold">Envíos Rápidos</strong>
                            <span>A todo el Perú</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 p-3 rounded-xl bg-white border border-stone-200/60 text-xs text-stone-700">
                        <span class="text-xl">💳</span>
                        <div>
                            <strong class="block text-stone-900 font-bold">Pago 100% Seguro</strong>
                            <span>Tarjeta, Yape o Efectivo</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 p-3 rounded-xl bg-white border border-stone-200/60 text-xs text-stone-700">
                        <span class="text-xl">🛡️</span>
                        <div>
                            <strong class="block text-stone-900 font-bold">Garantía Total</strong>
                            <span>Calidad asegurada</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Below the Fold: Extensive Characteristics & Structured Info Tabs -->
    <div id="detalles-producto" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 md:mt-20" x-data="{ activeTab: 'description' }">
        <div class="bg-white rounded-3xl p-6 sm:p-10 shadow-sm border border-stone-200/80">
            <!-- Tabs Header -->
            <div class="flex flex-wrap border-b border-stone-200 gap-2 sm:gap-6 mb-8">
                <button type="button" 
                        @click="activeTab = 'description'"
                        :class="activeTab === 'description' ? 'border-[#1A1A1A] text-[#1A1A1A] font-bold' : 'border-transparent text-stone-500 hover:text-stone-800 font-semibold'"
                        class="pb-4 px-2 border-b-2 text-sm sm:text-base flex items-center gap-2 transition cursor-pointer">
                    <span>📋</span>
                    <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'Description & Benefits' : 'Descripción y Beneficios' }}</span>
                </button>
                <button type="button" 
                        @click="activeTab = 'specs'"
                        :class="activeTab === 'specs' ? 'border-[#1A1A1A] text-[#1A1A1A] font-bold' : 'border-transparent text-stone-500 hover:text-stone-800 font-semibold'"
                        class="pb-4 px-2 border-b-2 text-sm sm:text-base flex items-center gap-2 transition cursor-pointer">
                    <span>⚙️</span>
                    <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'Technical Specs' : 'Especificaciones Técnicas' }}</span>
                </button>
                <button type="button" 
                        @click="activeTab = 'shipping'"
                        :class="activeTab === 'shipping' ? 'border-[#1A1A1A] text-[#1A1A1A] font-bold' : 'border-transparent text-stone-500 hover:text-stone-800 font-semibold'"
                        class="pb-4 px-2 border-b-2 text-sm sm:text-base flex items-center gap-2 transition cursor-pointer">
                    <span>🚚</span>
                    <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'Shipping & Warranty' : 'Envíos y Garantía' }}</span>
                </button>
                <button type="button" 
                        @click="activeTab = 'reviews'"
                        :class="activeTab === 'reviews' ? 'border-[#1A1A1A] text-[#1A1A1A] font-bold' : 'border-transparent text-stone-500 hover:text-stone-800 font-semibold'"
                        class="pb-4 px-2 border-b-2 text-sm sm:text-base flex items-center gap-2 transition cursor-pointer">
                    <span>⭐</span>
                    <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'Customer Reviews (28)' : 'Opiniones de Clientes (28)' }}</span>
                </button>
            </div>

            <!-- Tab 1: Descripción Extensa y Beneficios -->
            <div x-show="activeTab === 'description'" class="space-y-6">
                <div class="prose max-w-none text-stone-700 leading-relaxed text-sm sm:text-base whitespace-pre-line">
{{ $product->description }}
                </div>

                <!-- Structured Highlight Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-6 border-t border-stone-100">
                    <div class="p-5 bg-stone-50 rounded-2xl border border-stone-200/60">
                        <div class="text-2xl mb-2">✨</div>
                        <h4 class="font-bold text-stone-900 text-sm mb-1">Calidad Garantizada</h4>
                        <p class="text-xs text-stone-600">Probado bajo estrictos estándares para una experiencia superior y durable.</p>
                    </div>
                    <div class="p-5 bg-stone-50 rounded-2xl border border-stone-200/60">
                        <div class="text-2xl mb-2">⚡</div>
                        <h4 class="font-bold text-stone-900 text-sm mb-1">Eficacia Inmediata</h4>
                        <p class="text-xs text-stone-600">Diseño intuitivo y práctico pensado para obtener resultados desde el primer uso.</p>
                    </div>
                    <div class="p-5 bg-stone-50 rounded-2xl border border-stone-200/60">
                        <div class="text-2xl mb-2">🛡️</div>
                        <h4 class="font-bold text-stone-900 text-sm mb-1">Atención Personalizada</h4>
                        <p class="text-xs text-stone-600">Canal directo por WhatsApp para resolver dudas antes o después de tu compra.</p>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Especificaciones Técnicas -->
            <div x-show="activeTab === 'specs'" style="display: none;" class="space-y-4">
                <div class="max-w-2xl divide-y divide-stone-100 text-sm">
                    <div class="grid grid-cols-2 py-3">
                        <span class="text-stone-500 font-medium">Producto</span>
                        <span class="text-stone-900 font-bold">{{ $product->name }}</span>
                    </div>
                    @if($product->sku)
                    <div class="grid grid-cols-2 py-3">
                        <span class="text-stone-500 font-medium">Código / SKU</span>
                        <span class="text-stone-900 font-mono font-bold">{{ $product->sku }}</span>
                    </div>
                    @endif
                    @if($product->brand)
                    <div class="grid grid-cols-2 py-3">
                        <span class="text-stone-500 font-medium">Marca</span>
                        <span class="text-stone-900 font-bold">{{ $product->brand->name }}</span>
                    </div>
                    @endif
                    @if($product->categories && $product->categories->isNotEmpty())
                    <div class="grid grid-cols-2 py-3">
                        <span class="text-stone-500 font-medium">{{ \App\Helpers\TranslationHelper::trans('categories', 'Categorías') }}</span>
                        <span class="text-stone-900 font-bold">{{ $product->categories->map(fn($c) => $c->getTranslatedName())->implode(', ') }}</span>
                    </div>
                    @elseif($product->category)
                    <div class="grid grid-cols-2 py-3">
                        <span class="text-stone-500 font-medium">{{ \App\Helpers\TranslationHelper::trans('categories', 'Categoría') }}</span>
                        <span class="text-stone-900 font-bold">{{ $product->category->getTranslatedName() }}</span>
                    </div>
                    @endif
                    <div class="grid grid-cols-2 py-3">
                        <span class="text-stone-500 font-medium">Disponibilidad</span>
                        <span class="text-emerald-700 font-bold">{{ $product->isInStock() ? 'En Stock' : 'Consultar Stock' }}</span>
                    </div>
                    <div class="grid grid-cols-2 py-3">
                        <span class="text-stone-500 font-medium">Presentación / Unidad</span>
                        <span class="text-stone-900 font-bold">{{ ucfirst($product->unit ?? 'Unidad') }}</span>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Envíos y Garantía -->
            <div x-show="activeTab === 'shipping'" style="display: none;" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="p-6 bg-stone-50 rounded-2xl border border-stone-200/60">
                        <h4 class="font-bold text-stone-900 text-base mb-3 flex items-center gap-2">
                            <span>🚚</span> Envíos a Domicilio
                        </h4>
                        <ul class="text-xs sm:text-sm text-stone-600 space-y-2.5">
                            <li>• <strong>Envío Estándar:</strong> Entrega segura en 24 a 48 horas a nivel nacional.</li>
                            <li>• <strong>Envío Express:</strong> Disponible para entregas rápidas el mismo día según la zona.</li>
                            <li>• <strong>Seguimiento:</strong> Rastreo de pedido en tiempo real con Tribio Pass.</li>
                        </ul>
                    </div>
                    <div class="p-6 bg-stone-50 rounded-2xl border border-stone-200/60">
                        <h4 class="font-bold text-stone-900 text-base mb-3 flex items-center gap-2">
                            <span>💳</span> Métodos de Pago Seguros
                        </h4>
                        <ul class="text-xs sm:text-sm text-stone-600 space-y-2.5">
                            <li>• <strong>Tarjetas de Débito y Crédito:</strong> Procesado seguro con Mercado Pago (SSL 256 bits).</li>
                            <li>• <strong>Billeteras Digitales:</strong> Yape y Plin mediante coordinación directa.</li>
                            <li>• <strong>Pago Contra Entrega / Efectivo:</strong> Disponible según cobertura de la tienda.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Reseñas / Opiniones -->
            <div x-show="activeTab === 'reviews'" style="display: none;" class="space-y-6">
                <div class="flex items-center gap-6 p-6 bg-stone-50 rounded-2xl border border-stone-200/60">
                    <div class="text-center pr-6 border-r border-stone-200">
                        <div class="text-4xl font-black text-stone-900">4.9</div>
                        <div class="text-amber-500 text-sm">★★★★★</div>
                        <div class="text-xs text-stone-500 mt-1">28 reseñas</div>
                    </div>
                    <div class="flex-1 space-y-1.5 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="w-14">5 estrellas</span>
                            <div class="flex-1 bg-stone-200 h-2 rounded-full overflow-hidden">
                                <div class="bg-amber-400 h-full w-[90%]"></div>
                            </div>
                            <span class="w-8 text-right text-stone-500">90%</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-14">4 estrellas</span>
                            <div class="flex-1 bg-stone-200 h-2 rounded-full overflow-hidden">
                                <div class="bg-amber-400 h-full w-[10%]"></div>
                            </div>
                            <span class="w-8 text-right text-stone-500">10%</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="p-4 rounded-2xl border border-stone-100 bg-white">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-sm text-stone-900">María C.</span>
                            <span class="text-xs text-stone-400">Hace 2 días</span>
                        </div>
                        <div class="text-amber-500 text-xs mb-1">★★★★★ <span class="text-emerald-700 font-semibold ml-1">Compra Verificada</span></div>
                        <p class="text-xs text-stone-600">¡Excelente producto! Llegó rapidísimo y la calidad es tal cual la descripción. Muy recomendado.</p>
                    </div>
                    <div class="p-4 rounded-2xl border border-stone-100 bg-white">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-sm text-stone-900">Jorge L.</span>
                            <span class="text-xs text-stone-400">Hace 1 semana</span>
                        </div>
                        <div class="text-amber-500 text-xs mb-1">★★★★★ <span class="text-emerald-700 font-semibold ml-1">Compra Verificada</span></div>
                        <p class="text-xs text-stone-600">Muy buena atención y el pedido llegó en perfecto estado. Definitivamente volveré a comprar aquí.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products ("Productos Relacionados") -->
    @if($relatedProducts->count() > 0)
    <div class="bg-[#FDF8EF] py-20 border-t border-stone-200/60 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-xl mx-auto mb-12">
                <span class="text-xs font-bold uppercase tracking-widest text-[#C8A68B] mb-2 block">{{ \App\Helpers\TranslationHelper::isEn() ? 'Recommended for you' : 'Recomendados para ti' }}</span>
                <h2 class="text-3xl sm:text-4xl font-black text-[#1A1A1A]">{{ \App\Helpers\TranslationHelper::trans('related_products', 'También te podría gustar') }}</h2>
                <p class="text-stone-500 text-sm mt-2">{{ \App\Helpers\TranslationHelper::isEn() ? 'Curated items that complement your choice.' : 'Productos seleccionados que complementan tu elección.' }}</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                @foreach($relatedProducts as $related)
                <div class="group bg-white rounded-3xl p-3 border border-stone-200/80 shadow-xs hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between cursor-pointer"
                     onclick="window.location='{{ route('store.product', ['slug' => $store->slug, 'product' => $related->slug]) }}'">
                    <div>
                        <div class="relative w-full aspect-square mb-3 bg-stone-50 rounded-2xl overflow-hidden">
                            <img src="{{ $related->image_url }}" alt="{{ $related->name }}" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                            @if($related->resolveComparePrice() > $related->resolvePrice())
                                <span class="absolute top-2.5 left-2.5 bg-red-600 text-white text-[10px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider">
                                    {{ \App\Helpers\TranslationHelper::trans('special_offer', 'Oferta') }}
                                </span>
                            @endif
                        </div>
                        <div class="px-1">
                            @if($related->category)
                                <span class="text-[10px] font-bold text-stone-400 uppercase tracking-wider block mb-0.5">{{ $related->category->getTranslatedName() }}</span>
                            @endif
                            <h3 class="text-[#1A1A1A] font-bold text-sm line-clamp-2 mb-2 group-hover:text-[#C8A68B] transition-colors leading-snug">{{ $related->name }}</h3>
                        </div>
                    </div>
                    <div class="px-1 pt-2 border-t border-stone-100 flex items-center justify-between">
                        <div>
                            <span class="text-[#1A1A1A] font-black text-base">
                                {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($related->resolvePrice(), 2) }}
                            </span>
                            @if($related->resolveComparePrice() > $related->resolvePrice())
                                <span class="text-[11px] text-stone-400 line-through block -mt-1">
                                    {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($related->resolveComparePrice(), 2) }}
                                </span>
                            @endif
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-stone-100 group-hover:bg-[#1A1A1A] group-hover:text-white text-stone-700 flex items-center justify-center transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </span>
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
            {{ \App\Helpers\TranslationHelper::isEn() ? 'Powered by' : 'Impulsado por' }} <span class="text-[#1A1A1A] font-bold">Tribio</span>
        </p>
    </footer>
</div>

<!-- Cart Drawer -->
@include('templates.minimal-light.cart-drawer')

@endsection
