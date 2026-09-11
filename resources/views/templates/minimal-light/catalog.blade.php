@extends('templates.minimal-light.layout')

@section('title', 'Catálogo | ' . $store->name)

@section('content')
<div class="bg-white" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    
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
                            <span class=" font-semibold text-2xl md:text-3xl tracking-wide text-[#1A1A1A]">{{ $store->name }}</span>
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
                    <button class="md:hidden text-[#1A1A1A]" @click="mobileMenuOpen = !mobileMenuOpen">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
            </div>

            <nav class="hidden md:flex justify-center space-x-10 mt-6 pb-2">
                <a href="{{ route('store.show', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Home</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="text-[#C8A68B] font-bold text-sm transition">Shop</a>
                @foreach($categories->take(3) as $cat)
                    <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">{{ $cat->name }}</a>
                @endforeach
                <a href="{{ route('store.contact', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Contact</a>
            </nav>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div class="md:hidden" x-show="mobileMenuOpen" style="display: none;">
            <div class="px-4 pt-2 pb-4 space-y-1 bg-white border-t border-gray-100 shadow-inner">
                <a href="{{ route('store.show', $store->slug) }}" class="block px-3 py-2 text-sm font-medium text-gray-800">Home</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="block px-3 py-2 text-sm font-medium text-[#C8A68B] font-bold">Shop</a>
                @foreach($categories as $cat)
                    <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="block px-3 py-2 text-sm font-medium text-gray-800">{{ $cat->name }}</a>
                @endforeach
            </div>
        </div>

        <!-- Search Overlay -->
        <div x-show="searchOpen" style="display: none;" 
             class="absolute top-0 inset-x-0 bg-white border-b border-gray-100 shadow-2xl z-50 p-6 md:p-10">
            <div class="max-w-4xl mx-auto relative">
                <form action="{{ route('store.catalog', $store->slug) }}" method="GET" class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400 absolute left-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" name="q" placeholder="Buscar productos..." class="w-full pl-14 pr-12 py-4 text-xl  border-none rounded-full bg-gray-50 focus:ring-0" autofocus>
                </form>
                <button @click="searchOpen = false" type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Page Header -->
    <div class="bg-[#FDF8EF] py-10 md:py-16 text-center border-b border-gray-200">
        <h1 class="text-4xl md:text-5xl  text-[#1A1A1A] mb-4">Catálogo de Productos</h1>
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
                <form action="{{ route('store.catalog', $store->slug) }}" method="GET" class="space-y-8 sticky top-32">
                    <!-- Búsqueda Activa -->
                    @if(request('q'))
                        <div class="mb-4">
                            <input type="hidden" name="q" value="{{ request('q') }}">
                            <span class="inline-flex items-center gap-2 bg-[#FDF8EF] px-3 py-1.5 rounded-full text-sm font-medium text-[#1A1A1A]">
                                "{{ request('q') }}"
                                <a href="{{ request()->fullUrlWithQuery(['q' => null]) }}" class="text-gray-400 hover:text-red-500">✕</a>
                            </span>
                        </div>
                    @endif

                    <!-- Categorías -->
                    <div>
                        <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider mb-4">Categorías</h3>
                        <div class="space-y-3">
                            <a href="{{ request()->fullUrlWithQuery(['category' => null]) }}" class="block text-sm {{ !request('category') ? 'text-[#C8A68B] font-bold' : 'text-gray-600 hover:text-[#1A1A1A]' }}">
                                Todas
                            </a>
                            @foreach($categories as $cat)
                                <a href="{{ request()->fullUrlWithQuery(['category' => $cat->slug]) }}" class="flex justify-between items-center text-sm {{ request('category') === $cat->slug ? 'text-[#C8A68B] font-bold' : 'text-gray-600 hover:text-[#1A1A1A]' }}">
                                    <span>{{ $cat->name }}</span>
                                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ $cat->active_products_count }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Precio -->
                    <div>
                        <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider mb-4">Precio</h3>
                        <div class="flex items-center gap-2">
                            <input type="number" name="min_price" value="{{ request('min_price') }}" placeholder="Min" class="w-full text-sm border-gray-300 rounded-lg focus:ring-[#C8A68B] focus:border-[#C8A68B]">
                            <span class="text-gray-400">-</span>
                            <input type="number" name="max_price" value="{{ request('max_price') }}" placeholder="Max" class="w-full text-sm border-gray-300 rounded-lg focus:ring-[#C8A68B] focus:border-[#C8A68B]">
                        </div>
                    </div>

                    <!-- Ordenar -->
                    <div>
                        <h3 class="text-sm font-bold text-[#1A1A1A] uppercase tracking-wider mb-4">Ordenar por</h3>
                        <select name="sort" class="w-full text-sm border-gray-300 rounded-lg focus:ring-[#C8A68B] focus:border-[#C8A68B]" onchange="this.form.submit()">
                            <option value="position" {{ request('sort') == 'position' ? 'selected' : '' }}>Recomendados</option>
                            <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>Precio: Menor a Mayor</option>
                            <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>Precio: Mayor a Menor</option>
                            <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Más nuevos</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] text-white font-bold text-sm rounded-lg transition-colors">
                        Aplicar Filtros
                    </button>
                </form>
            </aside>

            <!-- Product Grid -->
            <div class="flex-1">
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
    <footer class="bg-white border-t border-gray-100 py-12 mt-20">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h3 class=" text-2xl font-bold text-[#1A1A1A] mb-4">{{ $store->name }}</h3>
            <p class="text-gray-500 text-sm mb-6">Gracias por confiar en nosotros.</p>
            <p class="text-xs text-gray-400">© {{ date('Y') }} {{ $store->name }}. Todos los derechos reservados.</p>
        </div>
    </footer>
</div>

<!-- Render Cart Drawer logic (reused from store.blade.php but rendered manually here for simplicity if not abstracted) -->
<div id="cartDrawer" x-data="{
             checkoutStep: 1,
             customer: { name: '', email: '', phone: '', address: '', country: '{{ request()->cookie('user_country') ?? 'PE' }}', state: '', city: '', zipcode: '', notes: '', express_shipping: false },
             storeSlug: '{{ $store->slug }}',
             isExpressEnabled: {{ $store->is_express_shipping_enabled ? 'true' : 'false' }},
             expressCost: {{ $store->express_shipping_cost ?? 0 }},
             currencySymbol: '{{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }}',
             shippingCost: 0,
             cartItems: window.TribioCart ? window.TribioCart.items : [],
             get cartTotal() { 
                 let total = this.cartItems.reduce((acc, item) => acc + (item.price * item.quantity), 0);
                 if (this.customer.express_shipping) total += this.expressCost;
                 total += this.shippingCost;
                 return total;
             },
             updateShipping() {
                 if(!this.customer.country) return;
                 fetch(`/api/shipping-cost/${this.storeSlug}?country=${this.customer.country}&state=${this.customer.state}`)
                     .then(res => res.json())
                     .then(data => {
                         this.shippingCost = parseFloat(data.cost) || 0;
                     }).catch(() => this.shippingCost = 0);
             },
             init() {
                 this.updateShipping();
             },
             submitOrder() {
                 if(!this.customer.name || !this.customer.phone || !this.customer.email) {
                     alert('Por favor completa los campos obligatorios.');
                     return;
                 }
                 if(window.TribioCart) {
                     const btn = document.getElementById('btnSubmitOrderCat');
                     if(btn) { btn.innerText = 'Procesando...'; btn.disabled = true; }
                     window.TribioCart.checkout(this.storeSlug, this.customer);
                 }
             }
         }"
         @cart-updated.window="cartItems = $event.detail"
         style="display:none; position: fixed; inset: 0; z-index: 999; justify-content: flex-end;">
         <div style="background: rgba(0,0,0,0.5);" class="absolute inset-0" onclick="document.getElementById('cartDrawer').style.display='none'"></div>
         <div class="relative w-full max-w-md h-full flex flex-col bg-white border-l border-gray-200 shadow-2xl">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="text-gray-900 font-bold text-lg" x-text="checkoutStep === 1 ? '🛒 Mi carrito' : 'Finalizar Compra'"></h3>
                <button onclick="document.getElementById('cartDrawer').style.display='none'" class="text-gray-400 hover:text-gray-700">✕</button>
            </div>
            
            <div class="flex-1 p-5 overflow-y-auto">
                <template x-if="cartItems.length === 0">
                    <p class="text-gray-400 text-sm text-center mt-8">Tu carrito está vacío.</p>
                </template>

                <template x-if="cartItems.length > 0 && checkoutStep === 1">
                    <div class="space-y-4">
                        <template x-for="(item, index) in cartItems" :key="index">
                            <div class="flex gap-4 p-3 bg-gray-50 rounded-xl border border-gray-100 items-center">
                                <template x-if="item.image">
                                    <img :src="item.image" class="w-16 h-16 object-cover rounded-lg">
                                </template>
                                <div class="flex-1">
                                    <h4 class="text-gray-800 font-semibold text-sm leading-tight" x-text="item.name"></h4>
                                     <div class="flex justify-between items-center mt-2">
                                        <p class="text-[#C8A68B] font-bold text-sm" x-text="currencySymbol + ' ' + (item.price * item.quantity).toFixed(2)"></p>
                                        <div class="flex items-center gap-2 text-gray-600 text-xs bg-white rounded-full border border-gray-200 p-1">
                                            <button @click="window.TribioCart.updateQuantity(item.id, item.quantity - 1)" class="w-5 h-5 rounded-full hover:bg-gray-100 flex items-center justify-center font-bold">-</button>
                                            <span x-text="item.quantity" class="w-4 text-center font-medium"></span>
                                            <button @click="window.TribioCart.updateQuantity(item.id, item.quantity + 1)" class="w-5 h-5 rounded-full hover:bg-gray-100 flex items-center justify-center font-bold">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="cartItems.length > 0 && checkoutStep === 2">
                    <div class="space-y-4 text-gray-700">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Nombre Completo *</label>
                            <input type="text" x-model="customer.name" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none transition" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Correo Electrónico *</label>
                                <input type="email" x-model="customer.email" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none transition" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Teléfono *</label>
                                <input type="text" x-model="customer.phone" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none transition" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">País *</label>
                                <select x-model="customer.country" @change="updateShipping()" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none transition" required>
                                    <option value="PE">Perú</option>
                                    <option value="US">Estados Unidos</option>
                                    <option value="MX">México</option>
                                    <option value="CO">Colombia</option>
                                    <option value="ES">España</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Estado / Depto.</label>
                                <input type="text" x-model="customer.state" @change="updateShipping()" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Dirección</label>
                            <input type="text" x-model="customer.address" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none transition">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Ciudad</label>
                                <input type="text" x-model="customer.city" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Código Postal</label>
                                <input type="text" x-model="customer.zipcode" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm outline-none transition">
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            
            <template x-if="cartItems.length > 0">
                <div class="p-5 border-t border-gray-100 bg-gray-50">
                    <div class="flex justify-between items-center mb-2 text-gray-600 text-sm">
                        <span>Subtotal:</span>
                        <span x-text="currencySymbol + ' ' + (cartTotal - shippingCost - (customer.express_shipping ? expressCost : 0)).toFixed(2)"></span>
                    </div>
                    
                    <template x-if="shippingCost > 0">
                        <div class="flex justify-between items-center mb-2 text-gray-600 text-sm">
                            <span>Envío:</span>
                            <span x-text="'+ ' + currencySymbol + ' ' + shippingCost.toFixed(2)"></span>
                        </div>
                    </template>

                    <div class="flex justify-between items-center mb-4 text-gray-800 border-t border-gray-200 pt-2 mt-2">
                        <span class="font-bold text-sm">Total a pagar:</span>
                        <span class="font-black text-xl text-[#C8A68B]" x-text="currencySymbol + ' ' + cartTotal.toFixed(2)"></span>
                    </div>
                    
                    <template x-if="checkoutStep === 1">
                        <button @click="checkoutStep = 2" class="w-full py-3 rounded-xl font-bold text-white bg-[#1A1A1A] hover:bg-[#C8A68B] transition-colors shadow-md">
                            Siguiente Paso →
                        </button>
                    </template>
                    
                    <template x-if="checkoutStep === 2">
                        <div class="flex gap-2">
                            <button @click="checkoutStep = 1" class="px-4 py-3 bg-white border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">
                                ←
                            </button>
                            <button id="btnSubmitOrderCat" @click="submitOrder" class="flex-1 py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] rounded-xl font-bold text-white transition-colors shadow-md">
                                Confirmar y Pagar
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

@endsection
