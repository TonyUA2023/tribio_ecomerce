<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->name }} — {{ $store->name }}</title>
    <meta name="description" content="{{ $product->short_description ?? $product->name }}">

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- AlpineJS v3 (CDN) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --accent: {{ $store->accent_color ?? '#DC2626' }};
            --accent-hover: #B91C1C;
            --secondary: {{ $store->secondary_color ?? '#16A34A' }};
            --secondary-hover: #15803D;
            --bg: {{ $store->bg_color ?? '#FFFFFF' }};
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg);
            color: #1F2937;
        }
        .btn-accent {
            background-color: var(--accent);
            color: white;
            transition: all 0.2s ease-in-out;
        }
        .btn-accent:hover {
            background-color: var(--accent-hover);
        }
        .btn-secondary {
            background-color: var(--secondary);
            color: white;
            transition: all 0.2s ease-in-out;
        }
        .btn-secondary:hover {
            background-color: var(--secondary-hover);
        }
        .text-accent {
            color: var(--accent);
        }
        .text-secondary {
            color: var(--secondary);
        }

        /* Subtle entrance animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* Pulsating ripple animation for floating WhatsApp button */
        @keyframes whatsapp-ripple {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.4), 0 0 0 0 rgba(37, 211, 102, 0.4);
            }
            40% {
                box-shadow: 0 0 0 8px rgba(37, 211, 102, 0), 0 0 0 0 rgba(37, 211, 102, 0.4);
            }
            80% {
                box-shadow: 0 0 0 8px rgba(37, 211, 102, 0), 0 0 0 16px rgba(37, 211, 102, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0), 0 0 0 0 rgba(37, 211, 102, 0);
            }
        }
        .whatsapp-btn {
            animation: whatsapp-ripple 2s infinite;
        }
    </style>
</head>
<body x-data="cartApp()" x-init="initCart()" class="antialiased bg-gray-50">

    <!-- Top Red Banner -->
    <div class="bg-[#E50914] text-white text-center py-2 px-4 font-black uppercase italic tracking-[0.15em] text-xs sm:text-sm border-b border-red-700">
        “Todo lo que hace que un tractor se mueva”
    </div>

    <!-- Header / Navbar (Unified) -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20 gap-4">
                <!-- Logo -->
                <a href="{{ route('store.show', $store->slug) }}" class="shrink-0">
                    @if($store->logo_path)
                        <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-14 w-auto object-contain">
                    @else
                        <span class="text-2xl font-black tracking-tight text-gray-900">TFL <span class="text-[#E50914]">PARTS</span></span>
                    @endif
                </a>

                <!-- Centered Navigation Links -->
                <nav class="hidden lg:flex items-center gap-6 text-xs font-black uppercase tracking-wider text-gray-700">
                    <a href="{{ route('store.show', $store->slug) }}" class="hover:text-[#E50914] pb-1 border-b-2 border-transparent hover:border-[#E50914] transition-all">INICIO</a>
                    <a href="{{ route('store.catalog', $store->slug) }}" class="hover:text-[#E50914] pb-1 border-b-2 border-transparent hover:border-[#E50914] transition-all">CATÁLOGO</a>
                    <a href="{{ route('store.show', $store->slug) }}#contacto" class="hover:text-[#E50914] pb-1 border-b-2 border-transparent hover:border-[#E50914] transition-all">CONTACTO</a>
                </nav>

                <!-- Search and Cart Group -->
                <div class="flex items-center gap-4 flex-1 max-w-sm justify-end">
                    <!-- Search Input -->
                    <form method="GET" action="{{ route('store.catalog', $store->slug) }}" class="hidden sm:flex border border-gray-300 rounded overflow-hidden h-10 w-full max-w-[240px]">
                        <input type="text" 
                               name="q" 
                               placeholder="Buscar repuesto..." 
                               class="w-full px-3 text-xs text-gray-700 focus:outline-none">
                        <button type="submit" class="bg-[#E50914] hover:bg-red-700 text-white px-3 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </button>
                    </form>

                    <!-- Cart Indicator Button & Dropdown -->
                    <div class="relative">
                        <button @click="openCartDropdown = !openCartDropdown" class="relative p-2.5 rounded-full bg-gray-50 border border-gray-100 hover:bg-gray-100 transition-colors shrink-0">
                            <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <span x-show="cartCount > 0" x-text="cartCount" class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full text-white text-[10px] font-bold flex items-center justify-center bg-[#E50914] shadow-md animate-pulse"></span>
                        </button>

                        <!-- Mini Dropdown Cart Container -->
                        <div x-show="openCartDropdown"
                             @click.outside="openCartDropdown = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1"
                             style="display: none;"
                             class="absolute right-0 mt-3 w-80 sm:w-96 bg-white border border-gray-200 rounded-2xl shadow-xl z-50 p-4 space-y-4 text-left">
                            
                            <!-- Step 1: Resumen de Compra -->
                            <div x-show="checkoutStep === 1" class="space-y-4">
                                <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-900">
                                        Mi carrito (<span x-text="cartCount"></span>)
                                    </h3>
                                    <button @click="openCartDropdown = false" class="text-gray-400 hover:text-gray-500 text-xs">✕</button>
                                </div>

                                <!-- Items List -->
                                <div class="max-h-60 overflow-y-auto divide-y divide-gray-100 pr-1">
                                    <template x-if="items.length === 0">
                                        <div class="text-center py-8 space-y-2">
                                            <svg class="w-8 h-8 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                            </svg>
                                            <p class="text-gray-500 font-bold text-[10px]">Tu carrito está vacío.</p>
                                        </div>
                                    </template>

                                    <template x-if="items.length > 0">
                                        <template x-for="item in items" :key="item.id">
                                            <div class="flex py-3 gap-3">
                                                <div class="w-12 h-12 bg-gray-50 rounded border border-gray-100 shrink-0 flex items-center justify-center overflow-hidden">
                                                    <template x-if="item.image">
                                                        <img :src="item.image" class="w-full h-full object-contain">
                                                    </template>
                                                    <template x-if="!item.image">
                                                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                                        </svg>
                                                    </template>
                                                </div>
                                                <div class="flex-1 flex flex-col justify-between min-w-0">
                                                    <div>
                                                        <h4 class="text-[10px] font-black text-gray-900 leading-tight truncate uppercase" x-text="item.name"></h4>
                                                        <span class="text-[10px] text-green-600 font-bold" x-text="'S/. ' + item.price.toFixed(2)"></span>
                                                    </div>
                                                    <div class="flex items-center justify-between mt-1">
                                                        <!-- Quantity Buttons -->
                                                        <div class="flex items-center border border-gray-200 rounded overflow-hidden bg-gray-50">
                                                            <button @click="updateQty(item.id, item.quantity - 1)" class="px-1.5 py-0.5 text-gray-500 hover:bg-gray-200 text-[10px]">-</button>
                                                            <span class="px-2 text-[10px] font-bold text-gray-700" x-text="item.quantity"></span>
                                                            <button @click="updateQty(item.id, item.quantity + 1)" class="px-1.5 py-0.5 text-gray-500 hover:bg-gray-200 text-[10px]">+</button>
                                                        </div>
                                                        <!-- Delete -->
                                                        <button @click="removeItem(item.id)" class="text-[10px] text-red-500 font-semibold hover:underline">Eliminar</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </template>
                                </div>

                                <!-- Subtotal & Continue Button -->
                                <template x-if="items.length > 0">
                                    <div class="pt-3 border-t border-gray-100 space-y-3">
                                        <div class="flex justify-between items-center text-xs">
                                            <span class="font-bold text-gray-600">Total Estimado:</span>
                                            <span class="font-black text-gray-900 text-sm" x-text="'S/. ' + totalSum().toFixed(2)"></span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button @click="checkoutStep = 2" class="flex-1 py-2 px-3 font-bold bg-[#E50914] hover:bg-red-700 text-white text-[10px] uppercase tracking-wider text-center rounded">
                                                Continuar pedido
                                            </button>
                                            <button @click="openCartDropdown = false" class="py-2 px-3 font-bold bg-gray-100 hover:bg-gray-200 text-gray-800 text-[10px] uppercase tracking-wider text-center rounded">
                                                Cerrar
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Step 2: Formulario de Cotización -->
                            <div x-show="checkoutStep === 2" class="space-y-4">
                                <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-900">
                                        Detalles de Cotización
                                    </h3>
                                    <button @click="checkoutStep = 1" class="text-[#E50914] hover:underline text-[10px] font-bold">Volver</button>
                                </div>

                                <!-- Form Fields -->
                                <div class="space-y-2">
                                    <div>
                                        <label class="block text-[9px] font-bold text-gray-600 mb-0.5">Nombre Comercial / Razón Social *</label>
                                        <input type="text" x-model="checkoutForm.customer_name" class="w-full px-2 py-1.5 rounded border border-gray-200 focus:outline-none focus:border-[#E50914] text-[10px]" placeholder="Ej. Corporación Agrícola S.A.">
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-bold text-gray-600 mb-0.5">Celular / WhatsApp *</label>
                                        <input type="text" x-model="checkoutForm.customer_phone" class="w-full px-2 py-1.5 rounded border border-gray-200 focus:outline-none focus:border-[#E50914] text-[10px]" placeholder="Ej. 987654321">
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-bold text-gray-600 mb-0.5">Dirección de Despacho *</label>
                                        <input type="text" x-model="checkoutForm.customer_address" class="w-full px-2 py-1.5 rounded border border-gray-200 focus:outline-none focus:border-[#E50914] text-[10px]" placeholder="Ej. Chiclayo o Provincia de destino">
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-bold text-gray-600 mb-0.5">Notas (Detalles de Tractor o Motor)</label>
                                        <textarea x-model="checkoutForm.customer_notes" rows="2" class="w-full px-2 py-1.5 rounded border border-gray-200 focus:outline-none focus:border-[#E50914] text-[10px]" placeholder="Ej. Filtro para MF 290 del año 2012"></textarea>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="pt-2 border-t border-gray-100 space-y-2">
                                    <div class="flex justify-between items-center text-xs mb-1">
                                        <span class="font-bold text-gray-600">Total Estimado:</span>
                                        <span class="font-black text-gray-900 text-sm" x-text="'S/. ' + totalSum().toFixed(2)"></span>
                                    </div>
                                    <button @click="submitOrder()"
                                            :disabled="submitting"
                                            class="w-full py-2.5 px-3 font-bold bg-[#16A34A] hover:bg-green-700 text-white text-[10px] uppercase tracking-wider text-center flex items-center justify-center gap-2 rounded">
                                        <template x-if="submitting">
                                            <span>Procesando...</span>
                                        </template>
                                        <template x-if="!submitting">
                                            <span>Confirmar Pedido por WhatsApp</span>
                                        </template>
                                    </button>
                                    <button @click="checkoutStep = 1" class="w-full py-2 px-3 font-bold bg-gray-100 hover:bg-gray-200 text-gray-800 text-[10px] uppercase tracking-wider text-center rounded">
                                        Volver al resumen
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Breadcrumbs -->
        <nav class="flex text-xs text-gray-500 font-bold uppercase tracking-wider mb-8 gap-2">
            <a href="{{ route('store.show', $store->slug) }}" class="hover:text-accent">Inicio</a>
            <span>/</span>
            <span class="text-gray-400">Detalle del Producto</span>
        </nav>

        <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-sm p-6 sm:p-10 animate-fade-in-up">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                <!-- Image Section -->
                <div class="lg:col-span-6 flex items-center justify-center bg-gray-50 rounded-2xl border border-gray-100 p-8 min-h-[350px]">
                    @if($product->image_path)
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="max-h-[400px] object-contain rounded-lg">
                    @else
                        <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        </svg>
                    @endif
                </div>

                <!-- Product Info Section -->
                <div class="lg:col-span-6 space-y-6">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                        <span class="px-3 py-1 rounded bg-gray-100 text-xs font-bold text-gray-500 uppercase tracking-widest">
                            SKU: {{ $product->sku ?? 'N/D' }}
                        </span>
                        <span class="text-xs font-bold text-green-600 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            En Stock ({{ $product->stock }} unidades)
                        </span>
                    </div>

                    <h1 class="text-3xl font-black text-gray-900 leading-tight">
                        {{ $product->name }}
                    </h1>

                    @if($product->short_description)
                        <p class="text-gray-600 text-sm leading-relaxed">
                            {{ $product->short_description }}
                        </p>
                    @endif

                    <div class="p-6 bg-gray-50 rounded-2xl border border-gray-100 space-y-3">
                        <div class="flex items-baseline gap-3">
                            <span class="text-3xl font-black text-secondary">S/. {{ number_format($product->price, 2) }}</span>
                            @if($product->compare_price)
                                <span class="text-sm text-gray-400 line-through">S/. {{ number_format($product->compare_price, 2) }}</span>
                            @endif
                        </div>
                        <p class="text-[11px] text-gray-400 font-bold uppercase tracking-wider">Precios incluyen IGV • Envío gratis en Chiclayo urbano</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        <button @click="addToCart({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, '{{ $product->image_path ? $product->image_url : '' }}')"
                                class="px-6 py-4 font-bold rounded-xl btn-accent text-center flex-1 shadow-lg shadow-red-600/20">
                            Añadir al Carrito
                        </button>
                        @if($store->whatsapp_phone)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp_phone) }}?text={{ urlencode('Hola, estoy interesado en el repuesto: ' . $product->name . ' (SKU: ' . $product->sku . '). ¿Tienen disponibilidad?') }}"
                               target="_blank"
                               class="px-6 py-4 font-bold rounded-xl btn-secondary text-center flex-1 shadow-lg shadow-green-600/20 flex items-center justify-center gap-2">
                                Consultar por WhatsApp
                            </a>
                        @endif
                    </div>

                    <!-- Technical Specs Accordion/Toggles -->
                    <div class="border-t border-gray-100 pt-6">
                        <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest mb-3">Descripción Técnica</h3>
                        <p class="text-gray-600 text-sm leading-relaxed whitespace-pre-line">
                            {{ $product->description ?? 'No hay descripción técnica adicional disponible para este componente.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        @if($relatedProducts->isNotEmpty())
            <div class="mt-16">
                <h3 class="text-2xl font-black text-gray-900 mb-8 border-b border-gray-200 pb-2">Repuestos Relacionados</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($relatedProducts as $rel)
                        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col justify-between group">
                            <div>
                                <div class="aspect-video w-full bg-gray-50 relative overflow-hidden flex items-center justify-center p-4 border-b border-gray-100">
                                    @if($rel->image_path)
                                        <img src="{{ $rel->image_url }}" alt="{{ $rel->name }}" class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-300">
                                    @else
                                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="p-5 space-y-2">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Repuesto Relacionado</span>
                                    <a href="{{ route('store.product', [$store->slug, $rel->slug]) }}" class="block font-bold text-gray-900 hover:text-accent text-sm leading-snug line-clamp-2 transition-colors">
                                        {{ $rel->name }}
                                    </a>
                                </div>
                            </div>
                            <div class="p-5 pt-0">
                                <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                    <span class="text-base font-black text-secondary">S/. {{ number_format($rel->price, 2) }}</span>
                                    <button @click="addToCart({{ $rel->id }}, '{{ addslashes($rel->name) }}', {{ $rel->price }}, '{{ $rel->image_path ? $rel->image_url : '' }}')"
                                            class="p-2 rounded-lg btn-accent shadow-sm hover:scale-105 transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white pt-16 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-center border-t border-gray-800 pt-8 gap-4 text-xs text-gray-500">
                <p>© {{ date('Y') }} {{ $store->name }} — Chiclayo. Todos los derechos reservados.</p>
                <p>Desarrollado en la plataforma multi-tienda <a href="{{ route('home') }}" class="text-gray-400 hover:text-white underline">Tribio</a></p>
            </div>
        </div>
    </footer>

    <!-- Floating Toast Notification -->
    <div x-show="showToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2 translate-x-2"
         x-transition:enter-end="opacity-100 translate-y-0 translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 translate-x-0"
         x-transition:leave-end="opacity-0 translate-y-2 translate-x-2"
         style="display: none;"
         class="fixed bottom-24 right-6 z-50 max-w-sm bg-gray-900 text-white px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3 border border-gray-800">
        <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-[10px] font-bold uppercase tracking-wider" x-text="toastMessage"></span>
    </div>

    <!-- Alpine App -->
    <script>
        function cartApp() {
            return {
                openCartDrawer: false,
                openCartDropdown: false,
                showToast: false,
                toastMessage: '',
                items: [],
                cartCount: 0,
                submitting: false,
                checkoutStep: 1,
                checkoutForm: {
                    customer_name: '',
                    customer_phone: '',
                    customer_address: '',
                    customer_notes: ''
                },

                initCart() {
                    const loadCart = () => {
                        if (window.TribioCart) {
                            this.items = [...window.TribioCart.items];
                            this.cartCount = window.TribioCart.count();
                        }
                    };
                    loadCart();
                    document.addEventListener('DOMContentLoaded', loadCart);
                },

                showToastNotification(message) {
                    this.toastMessage = message;
                    this.showToast = true;
                    setTimeout(() => {
                        this.showToast = false;
                    }, 3000);
                },

                addToCart(id, name, price, image) {
                    if (window.TribioCart) {
                        window.TribioCart.add(id, name, price, image);
                        this.items = [...window.TribioCart.items];
                        this.cartCount = window.TribioCart.count();
                        this.showToastNotification(name + ' añadido al carrito');
                    }
                },

                removeItem(id) {
                    if (window.TribioCart) {
                        window.TribioCart.remove(id);
                        this.items = [...window.TribioCart.items];
                        this.cartCount = window.TribioCart.count();
                    }
                },

                updateQty(id, qty) {
                    if (window.TribioCart) {
                        window.TribioCart.updateQuantity(id, qty);
                        this.items = [...window.TribioCart.items];
                        this.cartCount = window.TribioCart.count();
                    }
                },

                totalSum() {
                    if (window.TribioCart) {
                        return window.TribioCart.total();
                    }
                    return 0;
                },

                async submitOrder() {
                    if (!this.checkoutForm.customer_name || !this.checkoutForm.customer_phone || !this.checkoutForm.customer_address) {
                        alert('Por favor complete todos los campos obligatorios (*)');
                        return;
                    }

                    this.submitting = true;

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const payload = {
                            customer_name: this.checkoutForm.customer_name,
                            customer_phone: this.checkoutForm.customer_phone,
                            customer_address: this.checkoutForm.customer_address,
                            customer_notes: this.checkoutForm.customer_notes,
                            items: this.items.map(item => ({ id: item.id, quantity: item.quantity })),
                            _token: token
                        };

                        const response = await fetch('{{ route("store.checkout", $store->slug) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await response.json();

                        if (data.success) {
                            if (window.TribioCart) {
                                window.TribioCart.clear();
                            }
                            window.open(data.whatsapp_url, '_blank');
                            window.location.href = data.redirect_url;
                        } else {
                            alert(data.error || 'Ocurrió un error al procesar el pedido.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error al enviar el pedido. Por favor intente nuevamente.');
                    } finally {
                        this.submitting = false;
                    }
                }
            }
        }
    </script>

    <!-- Floating WhatsApp Button with Pulsating Effect -->
    @if($store->whatsapp_phone)
        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp_phone) }}?text={{ urlencode('Hola, me gustaría recibir más información.') }}"
           target="_blank"
           class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-14 h-14 bg-[#25D366] rounded-full text-white shadow-2xl hover:bg-[#20ba5a] transition-all duration-300 hover:scale-110 whatsapp-btn"
           aria-label="Contactar por WhatsApp">
            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.746.953 3.71 1.458 5.704 1.463h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
        </a>
    @endif

    <!-- Meta CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

</body>
</html>
