@extends('templates.minimal-light.layout')

@section('title', 'Catálogo | ' . $store->name)

@section('content')
@php
    $products = $products ?? $allProducts ?? collect();
@endphp
<div class="bg-[#FDF8EF] min-h-screen text-[#1A1A1A]" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    
    <!-- Header Minimalista -->
    @include('templates.minimal-light.header')

    <!-- Page Title Banner -->
    <div class="bg-white border-b border-stone-200/60 py-12 text-center">
        <h1 class="text-4xl font-bold text-[#1A1A1A] mb-3">{{ \App\Helpers\TranslationHelper::trans('our_collection', 'Nuestra Colección') }}</h1>
        <p class="text-gray-600 max-w-2xl mx-auto px-4">{{ \App\Helpers\TranslationHelper::trans('collection_desc', 'Explora nuestra colección y encuentra piezas únicas para ti.') }}</p>
    </div>

    <!-- Main Catalog Content -->
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 py-10" x-data="{ filtersOpen: false }">
        <div class="flex flex-col lg:flex-row gap-10">
            
            <!-- Mobile Filter Toggle -->
            <div class="lg:hidden flex justify-between items-center mb-4">
                <span class="text-sm text-gray-500">{{ $products->total() }} {{ \App\Helpers\TranslationHelper::isEn() ? 'results' : 'resultados' }}</span>
                <button @click="filtersOpen = !filtersOpen" class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-bold text-[#1A1A1A]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                    {{ \App\Helpers\TranslationHelper::trans('filters', 'Filtros') }}
                </button>
            </div>

            <!-- Sidebar Filters -->
            <aside class="w-full lg:w-64 flex-shrink-0" :class="{'hidden lg:block': !filtersOpen}">
                <form id="catalogFilterForm" action="{{ route('store.catalog', $store->slug) }}" method="GET" class="space-y-6 sticky top-32">
                    <!-- Búsqueda Activa (Preservar) -->
                    @if(request('q'))
                        <input type="hidden" name="q" value="{{ request('q') }}">
                        <div class="mb-2">
                            <span class="inline-flex items-center gap-2 bg-white px-3 py-1.5 rounded-full text-xs font-semibold text-[#1A1A1A] border border-stone-200">
                                🔍 "{{ request('q') }}"
                                <a href="{{ request()->fullUrlWithQuery(['q' => null]) }}" class="text-gray-400 hover:text-red-500 font-bold">✕</a>
                            </span>
                        </div>
                    @endif

                    <input type="hidden" name="category" id="filterCategory" value="{{ request('category') }}">
                    <input type="hidden" name="brand" id="filterBrand" value="{{ request('brand') }}">

                    <!-- Categorías -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider">{{ \App\Helpers\TranslationHelper::trans('categories', 'Categorías') }}</h3>
                            @if(request('category'))
                                <button type="button" onclick="setCategoryFilter('')" class="text-[11px] font-medium text-gray-400 hover:text-red-500 transition">{{ \App\Helpers\TranslationHelper::isEn() ? 'Clear' : 'Quitar' }}</button>
                            @endif
                        </div>
                        <div class="space-y-1">
                            <button type="button" onclick="setCategoryFilter('')" 
                                    class="w-full flex justify-between items-center text-sm py-1.5 px-2.5 rounded-lg transition text-left {{ !request('category') ? 'text-[#C8A68B] font-bold bg-[#C8A68B]/10' : 'text-gray-600 hover:text-[#1A1A1A] hover:bg-stone-100/60' }}">
                                <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'All' : 'Todas' }}</span>
                            </button>
                            @foreach($categories as $cat)
                                @php
                                    $isSelected = request('category') === $cat->slug || request('category') == $cat->id;
                                @endphp
                                <button type="button" onclick="setCategoryFilter('{{ $isSelected ? '' : $cat->slug }}')" 
                                        class="w-full flex justify-between items-center text-sm py-1.5 px-2.5 rounded-lg transition text-left {{ $isSelected ? 'text-[#C8A68B] font-bold bg-[#C8A68B]/10' : 'text-gray-600 hover:text-[#1A1A1A] hover:bg-stone-100/60' }}">
                                    <span class="truncate pr-2">{{ $cat->getTranslatedName() }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full flex-shrink-0 transition {{ $isSelected ? 'bg-[#C8A68B] text-white font-bold' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $cat->active_products_count }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Marcas -->
                    @if($brands->isNotEmpty())
                    <div class="pt-4 border-t border-gray-200/60">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider">{{ \App\Helpers\TranslationHelper::trans('brands', 'Marcas') }}</h3>
                            @if(request('brand'))
                                <button type="button" onclick="setBrandFilter('')" class="text-[11px] font-medium text-gray-400 hover:text-red-500 transition">{{ \App\Helpers\TranslationHelper::isEn() ? 'Clear' : 'Quitar' }}</button>
                            @endif
                        </div>
                        <div class="space-y-1 max-h-52 overflow-y-auto pr-1">
                            <button type="button" onclick="setBrandFilter('')" 
                                    class="w-full flex justify-between items-center text-sm py-1.5 px-2.5 rounded-lg transition text-left {{ !request('brand') ? 'text-[#C8A68B] font-bold bg-[#C8A68B]/10' : 'text-gray-600 hover:text-[#1A1A1A] hover:bg-stone-100/60' }}">
                                <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'All brands' : 'Todas las marcas' }}</span>
                            </button>
                            @foreach($brands as $b)
                                @php
                                    $isBrandSelected = request('brand') === $b->slug || request('brand') == $b->id;
                                @endphp
                                <button type="button" onclick="setBrandFilter('{{ $isBrandSelected ? '' : $b->slug }}')" 
                                        class="w-full flex justify-between items-center text-sm py-1.5 px-2.5 rounded-lg transition text-left {{ $isBrandSelected ? 'text-[#C8A68B] font-bold bg-[#C8A68B]/10' : 'text-gray-600 hover:text-[#1A1A1A] hover:bg-stone-100/60' }}">
                                    <span class="truncate pr-2">{{ $b->name }}</span>
                                    <span class="text-xs px-2 py-0.5 rounded-full flex-shrink-0 transition {{ $isBrandSelected ? 'bg-[#C8A68B] text-white font-bold' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $b->products_count }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Precio -->
                    <div class="pt-4 border-t border-gray-200/60">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider">{{ \App\Helpers\TranslationHelper::trans('price', 'Precio') }}</h3>
                            @if(request('min_price') || request('max_price'))
                                <button type="button" onclick="clearPriceFilter()" class="text-[11px] font-medium text-gray-400 hover:text-red-500 transition">Reset</button>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 mb-2.5">
                            <div class="relative flex-1">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400">{{ $currencySymbol }}</span>
                                <input type="number" step="any" name="min_price" id="minPriceInput" value="{{ request('min_price') }}" placeholder="Min" class="w-full pl-7 pr-2 py-2 text-xs bg-white border border-gray-300 rounded-lg focus:ring-1 focus:ring-[#C8A68B] focus:border-[#C8A68B] outline-none">
                            </div>
                            <span class="text-gray-400 font-bold">-</span>
                            <div class="relative flex-1">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400">{{ $currencySymbol }}</span>
                                <input type="number" step="any" name="max_price" id="maxPriceInput" value="{{ request('max_price') }}" placeholder="Max" class="w-full pl-7 pr-2 py-2 text-xs bg-white border border-gray-300 rounded-lg focus:ring-1 focus:ring-[#C8A68B] focus:border-[#C8A68B] outline-none">
                            </div>
                        </div>
                        {{-- Presets rápidos de precio --}}
                        <div class="grid grid-cols-3 gap-1.5">
                            <button type="button" onclick="setQuickPrice('', 50)" class="px-2 py-1 text-[11px] font-medium border border-gray-200 rounded-lg bg-white hover:border-[#C8A68B] hover:text-[#C8A68B] transition text-center {{ request('max_price') == 50 && !request('min_price') ? 'border-[#C8A68B] text-[#C8A68B] font-bold bg-[#C8A68B]/10' : '' }}">&lt; 50</button>
                            <button type="button" onclick="setQuickPrice(50, 100)" class="px-2 py-1 text-[11px] font-medium border border-gray-200 rounded-lg bg-white hover:border-[#C8A68B] hover:text-[#C8A68B] transition text-center {{ request('min_price') == 50 && request('max_price') == 100 ? 'border-[#C8A68B] text-[#C8A68B] font-bold bg-[#C8A68B]/10' : '' }}">50 - 100</button>
                            <button type="button" onclick="setQuickPrice(100, '')" class="px-2 py-1 text-[11px] font-medium border border-gray-200 rounded-lg bg-white hover:border-[#C8A68B] hover:text-[#C8A68B] transition text-center {{ request('min_price') == 100 && !request('max_price') ? 'border-[#C8A68B] text-[#C8A68B] font-bold bg-[#C8A68B]/10' : '' }}">&gt; 100</button>
                        </div>
                    </div>

                    <!-- Disponibilidad y Ofertas -->
                    <div class="pt-4 border-t border-gray-200/60 space-y-2">
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer hover:text-black">
                            <input type="checkbox" name="on_sale" value="1" {{ request('on_sale') ? 'checked' : '' }} onchange="this.form.submit()" class="rounded border-gray-300 text-[#1A1A1A] focus:ring-[#C8A68B]">
                            <span>{{ \App\Helpers\TranslationHelper::isEn() ? '🔥 On sale / Discount' : '🔥 En oferta / descuento' }}</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer hover:text-black">
                            <input type="checkbox" name="in_stock" value="1" {{ request('in_stock') ? 'checked' : '' }} onchange="this.form.submit()" class="rounded border-gray-300 text-[#1A1A1A] focus:ring-[#C8A68B]">
                            <span>{{ \App\Helpers\TranslationHelper::isEn() ? '📦 In stock only' : '📦 Solo con stock disponible' }}</span>
                        </label>
                    </div>

                    <!-- Ordenar -->
                    <div class="pt-4 border-t border-gray-200/60">
                        <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider mb-2.5">{{ \App\Helpers\TranslationHelper::trans('sort_by', 'Ordenar por') }}</h3>
                        <select name="sort" class="w-full text-xs py-2.5 px-3 bg-white border border-gray-300 rounded-lg focus:ring-1 focus:ring-[#C8A68B] focus:border-[#C8A68B] outline-none" onchange="this.form.submit()">
                            <option value="position" {{ request('sort') == 'position' ? 'selected' : '' }}>{{ \App\Helpers\TranslationHelper::isEn() ? 'Featured / Recommended' : 'Recomendados' }}</option>
                            <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>{{ \App\Helpers\TranslationHelper::isEn() ? 'Price: Low to High' : 'Precio: Menor a Mayor' }}</option>
                            <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>{{ \App\Helpers\TranslationHelper::isEn() ? 'Price: High to Low' : 'Precio: Mayor a Menor' }}</option>
                            <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>{{ \App\Helpers\TranslationHelper::isEn() ? 'Newest' : 'Más nuevos' }}</option>
                            <option value="name_asc" {{ request('sort') == 'name_asc' ? 'selected' : '' }}>{{ \App\Helpers\TranslationHelper::isEn() ? 'Name: A to Z' : 'Nombre: A a Z' }}</option>
                        </select>
                    </div>

                    <div class="space-y-2 pt-2">
                        <button type="submit" class="w-full py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] text-white font-bold text-sm rounded-lg transition-colors shadow-sm">
                            {{ \App\Helpers\TranslationHelper::isEn() ? 'Apply Filters' : 'Aplicar Filtros' }}
                        </button>
                        @if(request()->hasAny(['category', 'brand', 'min_price', 'max_price', 'on_sale', 'in_stock', 'q']))
                            <a href="{{ route('store.catalog', $store->slug) }}" class="block text-center py-2 text-xs font-semibold text-gray-500 hover:text-red-500 transition">
                                {{ \App\Helpers\TranslationHelper::isEn() ? 'Clear all filters' : 'Limpiar todos los filtros' }}
                            </a>
                        @endif
                    </div>
                </form>
            </aside>

            <!-- Product Grid -->
            <div class="flex-1">
                @php
                    $activeFiltersCount = (request('q') ? 1 : 0)
                                        + (request('category') ? 1 : 0)
                                        + (request('brand') ? 1 : 0)
                                        + (request('min_price') || request('max_price') ? 1 : 0)
                                        + (request('on_sale') ? 1 : 0)
                                        + (request('in_stock') ? 1 : 0);
                @endphp
                @if($activeFiltersCount > 0)
                    <div class="mb-6 flex flex-wrap items-center gap-2 p-3 bg-white rounded-xl border border-stone-200/80 shadow-sm">
                        <span class="text-xs font-bold text-gray-500 mr-1">{{ \App\Helpers\TranslationHelper::isEn() ? 'Applied filters' : 'Filtros aplicados' }} ({{ $activeFiltersCount }}):</span>

                        @if(request('q'))
                            <a href="{{ request()->fullUrlWithQuery(['q' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-stone-100 hover:bg-stone-200 text-[#1A1A1A] rounded-full text-xs font-medium transition">
                                <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'Search' : 'Búsqueda' }}: "{{ request('q') }}"</span>
                                <span class="text-gray-400 hover:text-red-600 font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('category'))
                            @php
                                $activeCatName = $categories->firstWhere('slug', request('category'))?->getTranslatedName() ?? request('category');
                            @endphp
                            <a href="{{ request()->fullUrlWithQuery(['category' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#C8A68B]/15 text-[#C8A68B] rounded-full text-xs font-bold transition hover:bg-[#C8A68B]/25">
                                <span>{{ \App\Helpers\TranslationHelper::trans('categories', 'Categoría') }}: {{ $activeCatName }}</span>
                                <span class="font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('brand'))
                            @php
                                $activeBrandName = $brands->firstWhere('slug', request('brand'))?->name ?? request('brand');
                            @endphp
                            <a href="{{ request()->fullUrlWithQuery(['brand' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#C8A68B]/15 text-[#C8A68B] rounded-full text-xs font-bold transition hover:bg-[#C8A68B]/25">
                                <span>{{ \App\Helpers\TranslationHelper::trans('brands', 'Marca') }}: {{ $activeBrandName }}</span>
                                <span class="font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('min_price') || request('max_price'))
                            <a href="{{ request()->fullUrlWithQuery(['min_price' => null, 'max_price' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-stone-100 hover:bg-stone-200 text-[#1A1A1A] rounded-full text-xs font-medium transition">
                                <span>{{ \App\Helpers\TranslationHelper::trans('price', 'Precio') }}: {{ $currencySymbol }}{{ request('min_price', 0) }} - {{ request('max_price') ? $currencySymbol . request('max_price') : 'Max' }}</span>
                                <span class="text-gray-400 hover:text-red-600 font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('on_sale'))
                            <a href="{{ request()->fullUrlWithQuery(['on_sale' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 text-red-600 rounded-full text-xs font-semibold transition hover:bg-red-100">
                                <span>{{ \App\Helpers\TranslationHelper::isEn() ? '🔥 On sale' : '🔥 En oferta' }}</span>
                                <span class="font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('in_stock'))
                            <a href="{{ request()->fullUrlWithQuery(['in_stock' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold transition hover:bg-emerald-100">
                                <span>{{ \App\Helpers\TranslationHelper::isEn() ? '📦 In stock' : '📦 Con stock' }}</span>
                                <span class="font-bold">✕</span>
                            </a>
                        @endif

                        <a href="{{ route('store.catalog', $store->slug) }}" class="ml-auto text-xs font-bold text-red-500 hover:text-red-700 underline">
                            {{ \App\Helpers\TranslationHelper::isEn() ? 'Clear all' : 'Limpiar todo' }}
                        </a>
                    </div>
                @endif
                @if($products->isEmpty())
                    <div class="text-center py-20 bg-gray-50 rounded-2xl border border-dashed border-gray-300">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">{{ \App\Helpers\TranslationHelper::isEn() ? 'No products found' : 'No se encontraron productos' }}</h3>
                        <p class="text-gray-500 mb-6">{{ \App\Helpers\TranslationHelper::isEn() ? 'Try adjusting your filters or search to find what you are looking for.' : 'Intenta ajustar tus filtros o búsqueda para encontrar lo que buscas.' }}</p>
                        <a href="{{ route('store.catalog', $store->slug) }}" class="inline-block bg-[#1A1A1A] text-white px-6 py-2 rounded-full font-medium hover:bg-[#C8A68B] transition">{{ \App\Helpers\TranslationHelper::isEn() ? 'Clear Filters' : 'Limpiar Filtros' }}</a>
                    </div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-8">
                        @foreach($products as $p)
                            <div class="group block relative cursor-pointer" onclick="window.location='{{ route('store.product', ['slug' => $store->slug, 'product' => $p->slug]) }}'">
                                <div class="relative w-full aspect-[4/5] mb-4 bg-gray-100 rounded-xl overflow-hidden shadow-sm group-hover:shadow-lg transition-all duration-300">
                                    <img src="{{ $p->image_url }}" alt="{{ $p->name }}" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                                    @if($p->resolveComparePrice() > $p->resolvePrice())
                                        <div class="absolute top-3 left-3 bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">{{ \App\Helpers\TranslationHelper::trans('special_offer', 'Sale') }}</div>
                                    @endif
                                    
                                    <!-- Add to Cart Hover Button -->
                                    <div class="absolute inset-x-0 bottom-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">
                                        <button onclick="event.preventDefault(); event.stopPropagation(); window.TribioCart && window.TribioCart.add({{ $p->id }}, '{{ addslashes($p->name) }}', {{ $p->resolvePrice() }}, '{{ $p->image_url }}'); document.getElementById('cartDrawer').style.display='flex';" 
                                                class="w-full py-3 bg-white/90 backdrop-blur-sm text-[#1A1A1A] font-bold text-sm rounded-xl shadow-lg hover:bg-[#1A1A1A] hover:text-white transition-colors flex items-center justify-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                            {{ \App\Helpers\TranslationHelper::trans('add_to_cart', 'Añadir al carrito') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 truncate">{{ $p->categories && $p->categories->isNotEmpty() ? $p->categories->map(fn($c) => $c->getTranslatedName())->implode(', ') : ($p->category ? $p->category->getTranslatedName() : (\App\Helpers\TranslationHelper::isEn() ? 'Catalog' : 'Catálogo')) }}</p>
                                    <h3 class="text-[#1A1A1A] font-semibold text-sm md:text-base mb-1 truncate">{{ $p->name }}</h3>
                                    <div class="flex items-center gap-2">
                                        <p class="text-[#C8A68B] font-bold text-sm md:text-base">
                                             {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($p->resolvePrice(), 2) }}
                                        </p>
                                        @if($p->resolveComparePrice() > $p->resolvePrice())
                                            <p class="text-gray-400 text-xs md:text-sm line-through">
                                                {{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }} {{ number_format($p->resolveComparePrice(), 2) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-12 flex justify-center">
                        {{ $products->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
            
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-[#FDF8EF] border-t border-gray-200/60 py-8 mt-20 text-center">
        <p class="text-xs font-semibold text-gray-500 tracking-wider">
            {{ \App\Helpers\TranslationHelper::isEn() ? 'Powered by' : 'Impulsado por' }} <span class="text-[#1A1A1A] font-bold">Tribio</span>
        </p>
    </footer>
</div>

<!-- Cart Drawer -->
@include('templates.minimal-light.cart-drawer')

<script>
    function setCategoryFilter(slug) {
        const input = document.getElementById('filterCategory');
        if (input) input.value = slug;
        document.getElementById('catalogFilterForm').submit();
    }

    function setBrandFilter(slug) {
        const input = document.getElementById('filterBrand');
        if (input) input.value = slug;
        document.getElementById('catalogFilterForm').submit();
    }

    function clearPriceFilter() {
        const min = document.getElementById('minPriceInput');
        const max = document.getElementById('maxPriceInput');
        if (min) min.value = '';
        if (max) max.value = '';
        document.getElementById('catalogFilterForm').submit();
    }

    function setQuickPrice(min, max) {
        const minEl = document.getElementById('minPriceInput');
        const maxEl = document.getElementById('maxPriceInput');
        if (minEl) minEl.value = min;
        if (maxEl) maxEl.value = max;
        document.getElementById('catalogFilterForm').submit();
    }
</script>

@endsection
