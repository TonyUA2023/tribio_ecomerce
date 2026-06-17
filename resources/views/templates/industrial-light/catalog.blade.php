<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Repuestos — {{ $store->name }}</title>
    <meta name="description" content="Repuestos para tractores agrícolas y cosechadoras. Catálogo completo.">

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- AlpineJS v3 (CDN) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --accent: #E50914; /* Red corporate YEDPAR color */
            --accent-hover: #B80710;
            --secondary: #16A34A; /* Green agricultural color */
            --secondary-hover: #15803D;
            --bg: #FFFFFF;
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

        /* Double range slider custom styling */
        .slider-handle::-webkit-slider-thumb {
            pointer-events: auto;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #E50914;
            border: 2px solid #FFFFFF;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            cursor: pointer;
            -webkit-appearance: none;
            margin-top: -6px;
        }
        .slider-handle::-moz-range-thumb {
            pointer-events: auto;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #E50914;
            border: 2px solid #FFFFFF;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            cursor: pointer;
        }
        /* Remove default track styling so only handles are visible */
        .slider-handle::-webkit-slider-runnable-track {
            -webkit-appearance: none;
            background: none;
            height: 4px;
        }
        .slider-handle::-moz-range-track {
            background: none;
            height: 4px;
        }
    </style>
</head>
<body x-data="cartApp()" x-init="initCart()" class="antialiased bg-white">

    <!-- Top Red Banner (Same as home) -->
    <div class="bg-[#E50914] text-white text-center py-2 px-4 font-black uppercase italic tracking-[0.15em] text-xs sm:text-sm border-b border-red-700">
        “Todo lo que hace que un tractor se mueva”
    </div>

    <!-- Header / Navbar (Same as home) -->
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
                    <a href="{{ route('store.catalog', $store->slug) }}" class="hover:text-[#E50914] pb-1 border-b-2 border-[#E50914] transition-all">CATÁLOGO</a>
                    <a href="{{ route('store.show', $store->slug) }}#contacto" class="hover:text-[#E50914] pb-1 border-b-2 border-transparent hover:border-[#E50914] transition-all">CONTACTO</a>
                </nav>

                <!-- Search and Cart Group -->
                <div class="flex items-center gap-4 flex-1 max-w-sm justify-end">
                    <!-- Search Input -->
                    <form method="GET" action="{{ route('store.catalog', $store->slug) }}" class="flex border border-gray-300 rounded overflow-hidden h-10 w-full max-w-[240px]">
                        <input type="text" 
                               name="q" 
                               value="{{ request('q') }}"
                               placeholder="Buscar repuesto..." 
                               class="w-full px-3 text-xs text-gray-700 focus:outline-none">
                        <!-- Keep filters -->
                        @if(request('category'))
                            <input type="hidden" name="category" value="{{ request('category') }}">
                        @endif
                        @if(request('brand'))
                            <input type="hidden" name="brand" value="{{ request('brand') }}">
                        @endif
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

    <!-- Breadcrumbs -->
    <div class="bg-gray-50 border-b border-gray-100 py-3.5 px-4">
        <div class="max-w-7xl mx-auto text-[11px] font-black uppercase tracking-wider text-gray-500 flex items-center gap-2">
            <a href="{{ route('store.show', $store->slug) }}" class="hover:text-accent">INICIO</a>
            <span class="text-gray-300">&gt;</span>
            <span class="text-accent">REPUESTOS PARA TRACTORES AGRÍCOLAS Y COSECHADORAS</span>
        </div>
    </div>

    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 animate-fade-in-up">
            
            <!-- Left Sidebar (Anchored Filters) -->
            <aside class="lg:col-span-3 space-y-6">
                
                <!-- Category Filter Box -->
                <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                    <div class="bg-gray-50 px-4 py-3 font-black text-xs uppercase tracking-wider text-gray-800 border-b border-gray-200 flex justify-between items-center">
                        <span>Categoría</span>
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="p-2 divide-y divide-gray-100 text-xs font-bold text-gray-600">
                        @foreach($categories as $cat)
                            <a href="{{ request()->fullUrlWithQuery(['category' => $cat->slug, 'page' => null]) }}" 
                               class="flex justify-between items-center py-2.5 px-3 hover:bg-gray-50 hover:text-accent transition-colors {{ request('category') === $cat->slug ? 'text-accent bg-red-50/20' : '' }}">
                                <span>{{ $cat->name }}</span>
                                <span class="text-gray-400 font-semibold">({{ $cat->active_products_count }})</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Brand Filter Box -->
                <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                    <div class="bg-gray-50 px-4 py-3 font-black text-xs uppercase tracking-wider text-gray-800 border-b border-gray-200 flex justify-between items-center">
                        <span>Marca</span>
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div class="p-2 divide-y divide-gray-100 text-xs font-bold text-gray-600">
                        @php
                            $brands = ['JOHN DEERE', 'PERKINS', 'MASSEY FERGUSON', 'CUMMINS', 'DONALDSON', 'EATON'];
                        @endphp
                        @foreach($brands as $brand)
                            <a href="{{ request()->fullUrlWithQuery(['brand' => $brand, 'page' => null]) }}" 
                               class="flex justify-between items-center py-2.5 px-3 hover:bg-gray-50 hover:text-accent transition-colors {{ request('brand') === $brand ? 'text-accent bg-red-50/20' : '' }}">
                                <span>{{ $brand }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Price Filter Box -->
                <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                    <div class="bg-gray-50 px-4 py-3 font-black text-xs uppercase tracking-wider text-gray-800 border-b border-gray-200 flex justify-between items-center">
                        <span>Precio</span>
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <div x-data="rangeSlider()" x-init="initSlider()" class="p-4 space-y-4">
                        <!-- Double range slider input rails -->
                        <div class="relative h-1 bg-gray-200 rounded-full my-4">
                            <!-- Selected highlighted range -->
                            <div class="absolute h-1 bg-[#E50914] rounded-full" :style="'left: ' + minPercent + '%; right: ' + (100 - maxPercent) + '%'"></div>
                            
                            <!-- Overlapping range inputs -->
                            <input type="range" 
                                   x-model="minPrice" 
                                   :min="minLimit" 
                                   :max="maxLimit" 
                                   @input="mintrigger()" 
                                   class="absolute w-full h-1 appearance-none bg-transparent pointer-events-none cursor-pointer slider-handle"
                                   style="top: 0; left: 0; z-index: 21; margin: 0;">
                                   
                            <input type="range" 
                                   x-model="maxPrice" 
                                   :min="minLimit" 
                                   :max="maxLimit" 
                                   @input="maxtrigger()" 
                                   class="absolute w-full h-1 appearance-none bg-transparent pointer-events-none cursor-pointer slider-handle"
                                   style="top: 0; left: 0; z-index: 22; margin: 0;">
                        </div>
                        
                        <!-- Inputs for visual representation -->
                        <div class="flex items-center justify-between gap-2 text-[10px] font-black uppercase text-gray-700">
                            <div class="flex items-center gap-1 border border-gray-300 rounded px-1.5 py-1 bg-gray-50 flex-1">
                                <span class="text-gray-400 font-extrabold shrink-0">Min S/.</span>
                                <input type="number" x-model.number="minPrice" @change="mintrigger()" class="w-full focus:outline-none font-bold bg-transparent text-gray-800 text-xs">
                            </div>
                            <div class="flex items-center gap-1 border border-gray-300 rounded px-1.5 py-1 bg-gray-50 flex-1">
                                <span class="text-gray-400 font-extrabold shrink-0">Max S/.</span>
                                <input type="number" x-model.number="maxPrice" @change="maxtrigger()" class="w-full focus:outline-none font-bold bg-transparent text-gray-800 text-xs">
                            </div>
                        </div>
                        
                        <button @click="applyFilter()" class="w-full py-2 bg-[#E50914] hover:bg-[#B80710] text-white text-[9px] font-black uppercase tracking-widest rounded transition-colors text-center shadow-sm">
                            APLICAR RANGO
                        </button>
                    </div>
                </div>

                <!-- Featured Products List -->
                <div class="border border-gray-200 rounded-lg overflow-hidden bg-white shadow-sm">
                    <div class="bg-gray-50 px-4 py-3 font-black text-xs uppercase tracking-wider text-gray-800 border-b border-gray-200">
                        <span>Mejores Productos</span>
                    </div>
                    <div class="p-3 divide-y divide-gray-100">
                        @foreach($featuredProducts as $feat)
                            <div class="py-3 flex gap-3 items-center">
                                <div class="w-12 h-12 bg-gray-50 rounded border border-gray-100 flex items-center justify-center shrink-0 text-lg overflow-hidden">
                                    @if($feat->image_path)
                                        <img src="{{ $feat->image_url }}" alt="{{ $feat->name }}" class="w-full h-full object-contain">
                                    @else
                                        <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        </svg>
                                    @endif
                                </div>
                                <div class="space-y-0.5">
                                    <a href="{{ route('store.product', [$store->slug, $feat->slug]) }}" class="text-xs font-bold text-gray-800 hover:text-accent line-clamp-2 leading-snug">
                                        {{ $feat->name }}
                                    </a>
                                    <span class="block text-xs font-extrabold text-green-600">S/. {{ number_format($feat->price, 2) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>

            <!-- Right Content Area -->
            <section class="lg:col-span-9 space-y-6">
                <!-- Sorting & View Controls -->
                <div class="border border-gray-200 rounded-lg bg-gray-50 p-4 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs font-bold text-gray-600 shadow-sm">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <span>Se muestran {{ $allProducts->firstItem() ?? 0 }}-{{ $allProducts->lastItem() ?? 0 }} de {{ $allProducts->total() }} resultados</span>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span>Ordenar por:</span>
                            <select onchange="window.location.href = this.value" class="border border-gray-300 rounded px-2.5 py-1.5 focus:outline-none bg-white font-semibold">
                                <option value="{{ request()->fullUrlWithQuery(['sort' => 'position']) }}" {{ request('sort') === 'position' ? 'selected' : '' }}>Posición</option>
                                <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_asc']) }}" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Precio: menor a mayor</option>
                                <option value="{{ request()->fullUrlWithQuery(['sort' => 'price_desc']) }}" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Precio: mayor a menor</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Active Filters -->
                @if(request()->filled('category') || request()->filled('brand') || request()->filled('q') || request()->filled('min_price') || request()->filled('max_price'))
                    <div class="flex flex-wrap items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs font-bold text-gray-700 shadow-sm animate-fade-in-up">
                        <span class="text-[10px] font-black uppercase text-gray-400 tracking-wider">Filtros Activos:</span>
                        
                        <!-- Category Filter -->
                        @if(request()->filled('category'))
                            @php
                                $activeCat = $categories->firstWhere('slug', request('category'));
                            @endphp
                            @if($activeCat)
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-white border border-gray-200 text-gray-800 shadow-sm">
                                    <span>Categoría: {{ $activeCat->name }}</span>
                                    <a href="{{ route('store.catalog', array_merge(request()->except(['category', 'page']), ['slug' => $store->slug])) }}" class="text-gray-400 hover:text-[#E50914] font-black transition-colors" title="Eliminar filtro">
                                        ✕
                                    </a>
                                </div>
                            @endif
                        @endif

                        <!-- Brand Filter -->
                        @if(request()->filled('brand'))
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-white border border-gray-200 text-gray-800 shadow-sm">
                                <span>Marca: {{ request('brand') }}</span>
                                <a href="{{ route('store.catalog', array_merge(request()->except(['brand', 'page']), ['slug' => $store->slug])) }}" class="text-gray-400 hover:text-[#E50914] font-black transition-colors" title="Eliminar filtro">
                                    ✕
                                </a>
                            </div>
                        @endif

                        <!-- Search Filter -->
                        @if(request()->filled('q'))
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-white border border-gray-200 text-gray-800 shadow-sm">
                                <span>Búsqueda: "{{ request('q') }}"</span>
                                <a href="{{ route('store.catalog', array_merge(request()->except(['q', 'page']), ['slug' => $store->slug])) }}" class="text-gray-400 hover:text-[#E50914] font-black transition-colors" title="Eliminar filtro">
                                    ✕
                                </a>
                            </div>
                        @endif

                        <!-- Price Filter -->
                        @if(request()->filled('min_price') || request()->filled('max_price'))
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-white border border-gray-200 text-gray-800 shadow-sm">
                                <span>Precio: S/. {{ request('min_price', $minPricePossible) }} - S/. {{ request('max_price', $maxPricePossible) }}</span>
                                <a href="{{ route('store.catalog', array_merge(request()->except(['min_price', 'max_price', 'page']), ['slug' => $store->slug])) }}" class="text-gray-400 hover:text-[#E50914] font-black transition-colors" title="Eliminar filtro">
                                    ✕
                                </a>
                            </div>
                        @endif

                        <!-- Clear All Link -->
                        <a href="{{ route('store.catalog', $store->slug) }}" class="text-[10px] font-black text-[#E50914] hover:text-[#B80710] uppercase tracking-wider pl-1 transition-colors">
                            Limpiar todo
                        </a>
                    </div>
                @endif

                <!-- Products Grid -->
                @if($allProducts->isEmpty())
                    <div class="text-center py-20 bg-white border border-gray-200 rounded-lg shadow-sm">
                        <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-gray-400 font-bold text-sm mt-4">No se encontraron repuestos con los filtros seleccionados.</p>
                        <a href="{{ route('store.catalog', $store->slug) }}" class="mt-4 inline-block px-5 py-2.5 rounded btn-accent font-bold text-xs uppercase tracking-wider">Limpiar Filtros</a>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($allProducts as $product)
                            @php
                                $detectedBrand = 'EATON';
                                if (str_contains(strtoupper($product->name), 'JOHN DEERE')) {
                                    $detectedBrand = 'JOHN DEERE';
                                } elseif (str_contains(strtoupper($product->name), 'PERKINS')) {
                                    $detectedBrand = 'PERKINS';
                                } elseif (str_contains(strtoupper($product->name), 'CUMMINS')) {
                                    $detectedBrand = 'CUMMINS';
                                } elseif (str_contains(strtoupper($product->name), 'MASSEY FERGUSON')) {
                                    $detectedBrand = 'MASSEY FERGUSON';
                                } elseif (str_contains(strtoupper($product->name), 'DONALDSON')) {
                                    $detectedBrand = 'DONALDSON';
                                }
                            @endphp
                            <div class="bg-white rounded border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between group relative">
                                
                                <!-- Sale Discount Badge -->
                                @if($product->compare_price && $product->compare_price > $product->price)
                                    @php
                                        $discount = round((($product->compare_price - $product->price) / $product->compare_price) * 100);
                                    @endphp
                                    <span class="absolute top-3 left-3 px-2 py-1 text-[10px] font-black text-white bg-[#E50914] rounded">
                                        - {{ $discount }}%
                                    </span>
                                @endif

                                <div>
                                    <!-- Image Box -->
                                    <div class="aspect-square w-full bg-gray-50 flex items-center justify-center p-6 border-b border-gray-100">
                                        @if($product->image_path)
                                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-300">
                                        @else
                                            <div class="text-center text-gray-300">
                                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block">Imagen no disponible</span>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Content Info -->
                                    <div class="p-5 space-y-2">
                                        <a href="{{ route('store.product', [$store->slug, $product->slug]) }}" class="block font-black text-gray-800 hover:text-accent text-xs uppercase tracking-wide leading-snug line-clamp-2 transition-colors">
                                            {{ $product->name }}
                                        </a>
                                        <div class="text-[11px] text-gray-600 font-bold uppercase space-y-0.5">
                                            <p>SKU: <span class="text-gray-900 font-medium">{{ $product->sku ?? 'N/D' }}</span></p>
                                            <p>MARCA: <span class="text-gray-900 font-medium">{{ $detectedBrand }}</span></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Price and Add Button -->
                                <div class="p-5 pt-0">
                                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                        <div>
                                            @if($product->compare_price && $product->compare_price > $product->price)
                                                <span class="block text-sm font-black text-red-600">S/. {{ number_format($product->price, 2) }}</span>
                                                <span class="text-[10px] text-gray-400 line-through">S/. {{ number_format($product->compare_price, 2) }}</span>
                                            @else
                                                <span class="block text-sm font-black text-gray-800">S/. {{ number_format($product->price, 2) }}</span>
                                            @endif
                                        </div>
                                        <button @click="addToCart({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, '{{ $product->image_path ? $product->image_url : '' }}')"
                                                class="px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-wider btn-accent shadow-sm">
                                            + AÑADIR
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="pt-8">
                        {{ $allProducts->links() }}
                    </div>
                @endif
            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-500 text-xs py-8 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-2">
            <p>© {{ date('Y') }} {{ $store->name }} — Chiclayo. Todos los derechos reservados.</p>
            <p>Desarrollado en la plataforma multi-tienda <a href="{{ route('home') }}" class="text-gray-400 hover:text-white underline">Tribio</a></p>
        </div>
    </footer>

    <!-- Cart Drawer Container -->
    <div x-show="openCartDrawer"
         style="display: none;"
         class="fixed inset-0 z-50 overflow-hidden"
         role="dialog"
         aria-modal="true">
        <div class="absolute inset-0 overflow-hidden">
            <!-- Backdrop -->
            <div @click="openCartDrawer = false" class="absolute inset-0 transition-opacity" style="background-color: rgba(15, 15, 26, 0.45); backdrop-filter: blur(4px);"></div>

            <!-- Panel -->
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div class="pointer-events-auto w-screen max-w-md">
                    <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-2xl">
                        <!-- Drawer Header -->
                        <div class="flex items-center justify-between border-b border-gray-100 p-6">
                            <h2 class="text-sm font-black uppercase tracking-wider text-gray-900 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                Carrito de Pedidos B2B
                            </h2>
                            <button @click="openCartDrawer = false" class="p-2 text-gray-400 hover:text-gray-500">
                                ✕
                            </button>
                        </div>

                        <!-- Drawer Body -->
                        <div class="flex-1 overflow-y-auto p-6 space-y-6">
                            <template x-if="items.length === 0">
                                <div class="text-center py-12 space-y-3">
                                    <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    <p class="text-gray-500 font-bold text-xs">Tu carrito de repuestos está vacío.</p>
                                </div>
                            </template>

                            <template x-if="items.length > 0">
                                <div class="divide-y divide-gray-100">
                                    <template x-for="item in items" :key="item.id">
                                        <div class="flex py-4 gap-4">
                                            <div class="w-14 h-14 bg-gray-50 rounded border border-gray-100 shrink-0 flex items-center justify-center text-2xl overflow-hidden">
                                                <template x-if="item.image">
                                                    <img :src="item.image" class="w-full h-full object-contain">
                                                </template>
                                                <template x-if="!item.image">
                                                    <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                                    </svg>
                                                </template>
                                            </div>
                                            <div class="flex-1 flex flex-col justify-between">
                                                <div>
                                                    <h4 class="text-xs font-black text-gray-900 leading-tight" x-text="item.name"></h4>
                                                    <span class="text-xs text-green-600 font-bold" x-text="'S/. ' + item.price.toFixed(2)"></span>
                                                </div>
                                                <div class="flex items-center justify-between mt-2">
                                                    <div class="flex items-center border border-gray-200 rounded overflow-hidden bg-gray-50">
                                                        <button @click="updateQty(item.id, item.quantity - 1)" class="px-2 py-1 text-gray-500 hover:bg-gray-200">-</button>
                                                        <span class="px-3 text-xs font-bold text-gray-700" x-text="item.quantity"></span>
                                                        <button @click="updateQty(item.id, item.quantity + 1)" class="px-2 py-1 text-gray-500 hover:bg-gray-200">+</button>
                                                    </div>
                                                    <button @click="removeItem(item.id)" class="text-xs text-red-500 font-semibold hover:underline">Eliminar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- Form Checkout -->
                            <template x-if="items.length > 0">
                                <div class="pt-6 border-t border-gray-100 space-y-4">
                                    <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider">Detalles de Cotización</h3>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-600 mb-1">Nombre Comercial / Razón Social *</label>
                                            <input type="text" x-model="checkoutForm.customer_name" class="w-full px-3 py-2 rounded border border-gray-200 focus:outline-none focus:border-accent text-xs" placeholder="Ej. Corporación Agrícola S.A.">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-600 mb-1">Celular / WhatsApp *</label>
                                            <input type="text" x-model="checkoutForm.customer_phone" class="w-full px-3 py-2 rounded border border-gray-200 focus:outline-none focus:border-accent text-xs" placeholder="Ej. 987654321">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-600 mb-1">Dirección de Despacho *</label>
                                            <input type="text" x-model="checkoutForm.customer_address" class="w-full px-3 py-2 rounded border border-gray-200 focus:outline-none focus:border-accent text-xs" placeholder="Ej. Chiclayo o Provincia de destino">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-600 mb-1">Notas (Detalles de Tractor o Motor)</label>
                                            <textarea x-model="checkoutForm.customer_notes" rows="2" class="w-full px-3 py-2 rounded border border-gray-200 focus:outline-none focus:border-accent text-xs" placeholder="Ej. Filtro de aire para Massey Ferguson 290"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Drawer Footer -->
                        <template x-if="items.length > 0">
                            <div class="border-t border-gray-100 p-6 space-y-4 bg-gray-50">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-bold text-gray-600">Suma Cotizada:</span>
                                    <span class="text-lg font-black text-gray-900" x-text="'S/. ' + totalSum().toFixed(2)"></span>
                                </div>
                                <button @click="submitOrder()"
                                        :disabled="submitting"
                                        class="w-full py-3 px-4 font-bold bg-[#16A34A] hover:bg-green-700 text-white text-xs uppercase tracking-widest text-center flex items-center justify-center gap-2">
                                    <template x-if="submitting">
                                        <span>Procesando...</span>
                                    </template>
                                    <template x-if="!submitting">
                                        <span class="flex items-center gap-2">
                                            Confirmar Pedido por WhatsApp
                                        </span>
                                    </template>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Alpine.js App -->
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

        function rangeSlider() {
            return {
                minPrice: {{ request('min_price', $minPricePossible) }},
                maxPrice: {{ request('max_price', $maxPricePossible) }},
                minLimit: {{ $minPricePossible }},
                maxLimit: {{ $maxPricePossible }},
                minPercent: 0,
                maxPercent: 100,

                initSlider() {
                    this.mintrigger();
                    this.maxtrigger();
                },

                mintrigger() {
                    this.minPrice = Math.min(this.minPrice, this.maxPrice - 1);
                    this.minPercent = ((this.minPrice - this.minLimit) / (this.maxLimit - this.minLimit)) * 100;
                },

                maxtrigger() {
                    this.maxPrice = Math.max(this.maxPrice, this.minPrice + 1);
                    this.maxPercent = ((this.maxPrice - this.minLimit) / (this.maxLimit - this.minLimit)) * 100;
                },

                applyFilter() {
                    const url = new URL(window.location.href);
                    url.searchParams.set('min_price', this.minPrice);
                    url.searchParams.set('max_price', this.maxPrice);
                    url.searchParams.delete('page'); // Reset pagination
                    window.location.href = url.toString();
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
