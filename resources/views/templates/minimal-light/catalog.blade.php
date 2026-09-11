@extends('templates.minimal-light.layout')

@section('title', 'Catálogo | ' . $store->name)

@section('content')
@php
    $products = $products ?? $allProducts ?? collect();
@endphp
<div class="bg-[#FDF8EF] min-h-screen text-[#1A1A1A]" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    
    <!-- Header Minimalista -->
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
                            <span class="font-semibold text-2xl md:text-3xl tracking-wide text-[#1A1A1A]">{{ $store->name }}</span>
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
                    <button class="md:hidden text-[#1A1A1A]" @click="mobileMenuOpen = !mobileMenuOpen">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Navigation (Desktop) -->
            @php
                $headerCategories = $categories->where('show_in_header', true)->take(5);
                if ($headerCategories->isEmpty()) {
                    $headerCategories = $categories->take(5);
                }
            @endphp
            <nav class="hidden md:flex justify-center space-x-10 mt-6 pb-2">
                <a href="{{ route('store.show', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Home</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="text-[#C8A68B] font-bold text-sm transition">Shop</a>
                @foreach($headerCategories as $cat)
                    <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">{{ $cat->name }}</a>
                @endforeach
                <a href="{{ route('store.contact', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Contact</a>
            </nav>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div class="md:hidden" x-show="mobileMenuOpen" style="display: none;">
            <div class="px-4 pt-2 pb-4 space-y-1 bg-white border-t border-gray-100 shadow-inner">
                <button @click="$dispatch('open-customer-modal'); mobileMenuOpen = false" class="w-full text-left px-3 py-2 text-sm font-bold text-[#C8A68B] flex items-center gap-2 border-b border-gray-100 pb-2 mb-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    Mi Cuenta / Pedidos Tribio
                </button>
                <a href="{{ route('store.show', $store->slug) }}" class="block px-3 py-2 text-sm font-medium text-gray-800">Home</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="block px-3 py-2 text-sm font-bold text-[#C8A68B]">Shop</a>
                @foreach($headerCategories as $cat)
                    <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="block px-3 py-2 text-sm font-medium text-gray-800">{{ $cat->name }}</a>
                @endforeach
                <a href="{{ route('store.contact', $store->slug) }}" class="block px-3 py-2 text-sm font-medium text-gray-800">Contact</a>
            </div>
        </div>

        <!-- Search Overlay -->
        <div x-show="searchOpen" style="display: none;" 
             class="absolute top-0 inset-x-0 bg-white border-b border-gray-100 shadow-2xl z-50 p-6 md:p-10">
            <div class="max-w-4xl mx-auto relative">
                <form action="{{ route('store.catalog', $store->slug) }}" method="GET" class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400 absolute left-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" name="q" placeholder="Buscar productos..." class="w-full pl-14 pr-12 py-4 text-xl border-none rounded-full bg-gray-50 focus:ring-0" autofocus>
                </form>
                <button @click="searchOpen = false" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Page Title Banner -->
    <div class="bg-white border-b border-stone-200/60 py-12 text-center">
        <h1 class="text-4xl font-bold text-[#1A1A1A] mb-3">Nuestra Colección</h1>
        <p class="text-gray-600 max-w-2xl mx-auto px-4">Explora nuestra colección y encuentra piezas únicas para ti.</p>
    </div>

    <!-- Main Catalog Content -->
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 py-10" x-data="{ filtersOpen: false }">
        <div class="flex flex-col lg:flex-row gap-10">
            
            <!-- Mobile Filter Toggle -->
            <div class="lg:hidden flex justify-between items-center mb-4">
                <span class="text-sm text-gray-500">{{ $products->total() }} resultados</span>
                <button @click="filtersOpen = !filtersOpen" class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-bold text-[#1A1A1A]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                    Filtros
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
                            <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider">Categorías</h3>
                            @if(request('category'))
                                <button type="button" onclick="setCategoryFilter('')" class="text-[11px] font-medium text-gray-400 hover:text-red-500 transition">Quitar</button>
                            @endif
                        </div>
                        <div class="space-y-1">
                            <button type="button" onclick="setCategoryFilter('')" 
                                    class="w-full flex justify-between items-center text-sm py-1.5 px-2.5 rounded-lg transition text-left {{ !request('category') ? 'text-[#C8A68B] font-bold bg-[#C8A68B]/10' : 'text-gray-600 hover:text-[#1A1A1A] hover:bg-stone-100/60' }}">
                                <span>Todas</span>
                            </button>
                            @foreach($categories as $cat)
                                @php
                                    $isSelected = request('category') === $cat->slug || request('category') == $cat->id;
                                @endphp
                                <button type="button" onclick="setCategoryFilter('{{ $isSelected ? '' : $cat->slug }}')" 
                                        class="w-full flex justify-between items-center text-sm py-1.5 px-2.5 rounded-lg transition text-left {{ $isSelected ? 'text-[#C8A68B] font-bold bg-[#C8A68B]/10' : 'text-gray-600 hover:text-[#1A1A1A] hover:bg-stone-100/60' }}">
                                    <span class="truncate pr-2">{{ $cat->name }}</span>
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
                            <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider">Marcas</h3>
                            @if(request('brand'))
                                <button type="button" onclick="setBrandFilter('')" class="text-[11px] font-medium text-gray-400 hover:text-red-500 transition">Quitar</button>
                            @endif
                        </div>
                        <div class="space-y-1 max-h-52 overflow-y-auto pr-1">
                            <button type="button" onclick="setBrandFilter('')" 
                                    class="w-full flex justify-between items-center text-sm py-1.5 px-2.5 rounded-lg transition text-left {{ !request('brand') ? 'text-[#C8A68B] font-bold bg-[#C8A68B]/10' : 'text-gray-600 hover:text-[#1A1A1A] hover:bg-stone-100/60' }}">
                                <span>Todas las marcas</span>
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
                            <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider">Precio</h3>
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
                            <span>🔥 En oferta / descuento</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer hover:text-black">
                            <input type="checkbox" name="in_stock" value="1" {{ request('in_stock') ? 'checked' : '' }} onchange="this.form.submit()" class="rounded border-gray-300 text-[#1A1A1A] focus:ring-[#C8A68B]">
                            <span>📦 Solo con stock disponible</span>
                        </label>
                    </div>

                    <!-- Ordenar -->
                    <div class="pt-4 border-t border-gray-200/60">
                        <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider mb-2.5">Ordenar por</h3>
                        <select name="sort" class="w-full text-xs py-2.5 px-3 bg-white border border-gray-300 rounded-lg focus:ring-1 focus:ring-[#C8A68B] focus:border-[#C8A68B] outline-none" onchange="this.form.submit()">
                            <option value="position" {{ request('sort') == 'position' ? 'selected' : '' }}>Recomendados</option>
                            <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>Precio: Menor a Mayor</option>
                            <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>Precio: Mayor a Menor</option>
                            <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Más nuevos</option>
                            <option value="name_asc" {{ request('sort') == 'name_asc' ? 'selected' : '' }}>Nombre: A a Z</option>
                        </select>
                    </div>

                    <div class="space-y-2 pt-2">
                        <button type="submit" class="w-full py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] text-white font-bold text-sm rounded-lg transition-colors shadow-sm">
                            Aplicar Filtros
                        </button>
                        @if(request()->hasAny(['category', 'brand', 'min_price', 'max_price', 'on_sale', 'in_stock', 'q']))
                            <a href="{{ route('store.catalog', $store->slug) }}" class="block text-center py-2 text-xs font-semibold text-gray-500 hover:text-red-500 transition">
                                Limpiar todos los filtros
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
                        <span class="text-xs font-bold text-gray-500 mr-1">Filtros aplicados ({{ $activeFiltersCount }}):</span>

                        @if(request('q'))
                            <a href="{{ request()->fullUrlWithQuery(['q' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-stone-100 hover:bg-stone-200 text-[#1A1A1A] rounded-full text-xs font-medium transition">
                                <span>Búsqueda: "{{ request('q') }}"</span>
                                <span class="text-gray-400 hover:text-red-600 font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('category'))
                            @php
                                $activeCategoryName = $categories->firstWhere('slug', request('category'))?->name ?? (request('category') === 'destacados' ? 'Destacados' : request('category'));
                            @endphp
                            <a href="{{ request()->fullUrlWithQuery(['category' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#C8A68B]/15 text-[#C8A68B] rounded-full text-xs font-bold transition hover:bg-[#C8A68B]/25">
                                <span>Categoría: {{ $activeCategoryName }}</span>
                                <span class="font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('brand'))
                            @php
                                $activeBrandName = $brands->firstWhere('slug', request('brand'))?->name ?? request('brand');
                            @endphp
                            <a href="{{ request()->fullUrlWithQuery(['brand' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#C8A68B]/15 text-[#C8A68B] rounded-full text-xs font-bold transition hover:bg-[#C8A68B]/25">
                                <span>Marca: {{ $activeBrandName }}</span>
                                <span class="font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('min_price') || request('max_price'))
                            <a href="{{ request()->fullUrlWithQuery(['min_price' => null, 'max_price' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-stone-100 hover:bg-stone-200 text-[#1A1A1A] rounded-full text-xs font-medium transition">
                                <span>Precio: {{ $currencySymbol }}{{ request('min_price', 0) }} - {{ request('max_price') ? $currencySymbol . request('max_price') : 'Max' }}</span>
                                <span class="text-gray-400 hover:text-red-600 font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('on_sale'))
                            <a href="{{ request()->fullUrlWithQuery(['on_sale' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 text-red-600 rounded-full text-xs font-semibold transition hover:bg-red-100">
                                <span>🔥 En oferta</span>
                                <span class="font-bold">✕</span>
                            </a>
                        @endif

                        @if(request('in_stock'))
                            <a href="{{ request()->fullUrlWithQuery(['in_stock' => null]) }}" class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-semibold transition hover:bg-emerald-100">
                                <span>📦 Con stock</span>
                                <span class="font-bold">✕</span>
                            </a>
                        @endif

                        <a href="{{ route('store.catalog', $store->slug) }}" class="ml-auto text-xs font-bold text-red-500 hover:text-red-700 underline">
                            Limpiar todo
                        </a>
                    </div>
                @endif
                @if($products->isEmpty())
                    <div class="text-center py-20 bg-gray-50 rounded-2xl border border-dashed border-gray-300">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">No se encontraron productos</h3>
                        <p class="text-gray-500 mb-6">Intenta ajustar tus filtros o búsqueda para encontrar lo que buscas.</p>
                        <a href="{{ route('store.catalog', $store->slug) }}" class="inline-block bg-[#1A1A1A] text-white px-6 py-2 rounded-full font-medium hover:bg-[#C8A68B] transition">Limpiar Filtros</a>
                    </div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-8">
                        @foreach($products as $p)
                            <div class="group block relative cursor-pointer" onclick="window.location='{{ route('store.product', ['slug' => $store->slug, 'product' => $p->slug]) }}'">
                                <div class="relative w-full aspect-[4/5] mb-4 bg-gray-100 rounded-xl overflow-hidden shadow-sm group-hover:shadow-lg transition-all duration-300">
                                    <img src="{{ $p->image_url }}" alt="{{ $p->name }}" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500">
                                    @if($p->resolveComparePrice() > $p->resolvePrice())
                                        <div class="absolute top-3 left-3 bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wide">Sale</div>
                                    @endif
                                    
                                    <!-- Add to Cart Hover Button -->
                                    <div class="absolute inset-x-0 bottom-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300 transform translate-y-4 group-hover:translate-y-0">
                                        <button onclick="event.preventDefault(); event.stopPropagation(); window.TribioCart && window.TribioCart.add({{ $p->id }}, '{{ addslashes($p->name) }}', {{ $p->resolvePrice() }}, '{{ $p->image_url }}'); document.getElementById('cartDrawer').style.display='flex';" 
                                                class="w-full py-3 bg-white/90 backdrop-blur-sm text-[#1A1A1A] font-bold text-sm rounded-xl shadow-lg hover:bg-[#1A1A1A] hover:text-white transition-colors flex items-center justify-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                            Añadir al carrito
                                        </button>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">{{ $p->category->name ?? 'Catálogo' }}</p>
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
            Impulsado por <span class="text-[#1A1A1A] font-bold">Tribio</span>
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
