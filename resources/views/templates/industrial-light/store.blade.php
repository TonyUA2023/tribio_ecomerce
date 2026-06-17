<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} — Everything That Makes a Tractor Move</title>
    <meta name="description" content="{{ $store->description }}">

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- AlpineJS v3 (CDN) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --accent: #E50914; /* Red corporate color */
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
        
        /* Hexagon background pattern */
        .hex-bg {
            background-color: #0b0b0b;
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(20,20,20,0.9), rgba(0,0,0,0.95)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='28' height='49' viewBox='0 0 28 49'%3E%3Cpath fill='%231f1f1f' fill-opacity='0.4' fill-rule='evenodd' d='M0 0h28v49H0V0zm14 2.5L26.5 10v15L14 32.5 1.5 25V10L14 2.5zM14 35l12.5 7.5v15L14 65 1.5 57.5v-15L14 35z'/%3E%3C/svg%3E");
            background-size: auto;
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
<body x-data="cartApp()" x-init="initCart()" class="antialiased scroll-smooth bg-white">

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
                <nav class="hidden lg:flex items-center gap-7 text-xs font-black uppercase tracking-wider text-gray-700">
                    <a href="{{ route('store.show', $store->slug) }}" class="hover:text-[#E50914] pb-1 border-b-2 border-transparent hover:border-[#E50914] transition-all">INICIO</a>
                    <a href="{{ route('store.catalog', $store->slug) }}" class="hover:text-[#E50914] pb-1 border-b-2 border-transparent hover:border-[#E50914] transition-all">CATÁLOGO</a>
                    <a href="#contacto" class="hover:text-[#E50914] pb-1 border-b-2 border-transparent hover:border-[#E50914] transition-all">CONTACTO</a>
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

    <!-- Slider / Hero Banner Section with Hex Sidebars -->
    <section class="relative bg-black border-b border-gray-900 overflow-hidden select-none">
        <div class="grid grid-cols-12 w-full h-[320px] sm:h-[420px] lg:h-[500px]">
            <!-- Left Honeycomb Sidebar -->
            <div class="hidden md:block col-span-2 hex-bg border-r border-gray-900 relative">
                <button class="absolute right-4 top-1/2 -translate-y-1/2 text-white/40 hover:text-white transition-colors">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
            </div>

            <!-- Central Sunset Banner Slider -->
            <div class="col-span-12 md:col-span-8 relative bg-cover bg-center flex items-center justify-center p-6 text-center"
                 style="background-image: linear-gradient(rgba(0,0,0,0.1), rgba(0,0,0,0.4)), url('{{ asset('storage/images/tfl_parts_hero.png') }}');">
                <div class="space-y-4 max-w-2xl text-white">
                    <h2 class="text-2xl sm:text-4xl lg:text-5xl font-black uppercase italic tracking-wider drop-shadow-md text-white">
                        “Todo lo que hace <br class="hidden sm:block">
                        que un tractor se mueva”
                    </h2>
                    <p class="text-xs sm:text-sm font-medium tracking-widest uppercase text-gray-200 drop-shadow">
                        {{ $store->tagline }}
                    </p>
                    <div class="pt-4">
                        <a href="{{ route('store.catalog', $store->slug) }}" class="inline-block px-6 py-3 font-bold text-xs uppercase tracking-widest bg-[#E50914] hover:bg-red-700 text-white rounded transition-all">
                            Explorar Repuestos
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Honeycomb Sidebar -->
            <div class="hidden md:block col-span-2 hex-bg border-l border-gray-900 relative">
                <button class="absolute left-4 top-1/2 -translate-y-1/2 text-white/40 hover:text-white transition-colors">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>
    </section>

    <!-- Core Features Section -->
    <section class="py-16 bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10 animate-fade-in-up">
                <!-- Expedited Shipping -->
                <div class="flex items-start gap-5">
                    <div class="p-3 border border-red-500 rounded-xl text-red-600 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 011 1v2.5a.5.5 0 01-.5.5h-2a.5.5 0 01-.5-.5V16m5 0h2a1 1 0 001-1v-4a1 1 0 00-.293-.707l-2-2A1 1 0 0016.5 8H14M14 16a2 2 0 11-4 0M6 16a2 2 0 11-4 0"/>
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h4 class="text-sm font-black uppercase tracking-wider text-gray-900">ENVÍO RÁPIDO</h4>
                        <p class="text-xs text-gray-500 leading-relaxed font-semibold">
                            Preparamos minuciosamente tus pedidos y los despachamos de inmediato con nuestros transportistas de confianza, garantizando una entrega rápida y segura. Tu satisfacción es nuestra prioridad.
                        </p>
                    </div>
                </div>

                <!-- Professional Support -->
                <div class="flex items-start gap-5">
                    <div class="p-3 border border-red-500 rounded-xl text-red-600 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h4 class="text-sm font-black uppercase tracking-wider text-gray-900">SOPORTE PROFESIONAL</h4>
                        <p class="text-xs text-gray-500 leading-relaxed font-semibold">
                            Nuestro equipo de soporte dedicado está a tu servicio los 7 días de la semana, asegurando una asistencia rápida y personalizada siempre que lo necesites.
                        </p>
                    </div>
                </div>

                <!-- Convenient Payment Solutions -->
                <div class="flex items-start gap-5">
                    <div class="p-3 border border-red-500 rounded-xl text-red-600 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h4 class="text-sm font-black uppercase tracking-wider text-gray-900">MÉTODOS DE PAGO SEGUROS</h4>
                        <p class="text-xs text-gray-500 leading-relaxed font-semibold">
                            Ofrecemos una variedad de opciones de pago seguras y flexibles, adaptándonos a transferencias, depósitos y tarjetas bancarias para garantizar un proceso de cotización sencillo.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Banner "TODO LO QUE NECESITAS ESTÁ A UN SOLO CLIC" -->
    <section class="py-10 bg-gray-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-4">
            <h3 class="text-xl sm:text-2xl lg:text-3xl font-black uppercase tracking-wider text-gray-900">
                TODO LO QUE NECESITAS ESTÁ <span class="text-[#E50914]">A UN SOLO CLIC</span>
            </h3>
            <p class="text-xs text-gray-500 max-w-xl mx-auto leading-relaxed">
                Accede a nuestro catálogo de distribución B2B y cotiza en línea directo a nuestro WhatsApp de atención rápida.
            </p>
        </div>
    </section>

    <!-- Categories / Sections -->
    <section class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="border-b border-gray-200 pb-4 mb-8">
                <h2 class="text-xl font-black uppercase tracking-widest text-gray-900">Categorías de Repuestos</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 animate-fade-in-up">
                @foreach($categories as $cat)
                    <a href="{{ route('store.catalog', [$store->slug, 'category' => $cat->slug]) }}" class="p-6 rounded-lg border border-gray-200 hover:border-[#E50914] bg-white transition-all flex flex-col justify-between group shadow-sm">
                        <div>
                            @if($cat->slug === 'repuestos-de-tractores')
                                <svg class="w-7 h-7 text-gray-400 group-hover:text-[#E50914] transition-colors mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0 M13 17a5 5 0 1 0 10 0a5 5 0 1 0 -10 0 M9 14h6 M18 12V8h-5v4 M9 14V10h4"/>
                                </svg>
                            @elseif($cat->slug === 'motores-y-partes')
                                <svg class="w-7 h-7 text-gray-400 group-hover:text-[#E50914] transition-colors mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                </svg>
                            @elseif($cat->slug === 'retenes-y-sellos')
                                <svg class="w-7 h-7 text-gray-400 group-hover:text-[#E50914] transition-colors mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            @elseif($cat->slug === 'sistemas-hidraulicos')
                                <svg class="w-7 h-7 text-gray-400 group-hover:text-[#E50914] transition-colors mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8M12 4v16M4 20h16"/>
                                </svg>
                            @else
                                <svg class="w-7 h-7 text-gray-400 group-hover:text-[#E50914] transition-colors mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            @endif
                            <h3 class="text-xs font-black uppercase tracking-wider text-gray-900 group-hover:text-[#E50914] transition-colors">{{ $cat->name }}</h3>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-[9px] text-gray-400 font-extrabold uppercase tracking-widest group-hover:text-[#E50914] transition-colors">VER REPUESTOS</span>
                            <svg class="w-3.5 h-3.5 text-gray-400 group-hover:text-[#E50914] group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Corporate About Us Section (Clean & Minimal) -->
    <section id="nosotros" class="py-20 bg-white scroll-mt-20 border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center animate-fade-in-up">
                <div class="lg:col-span-7 space-y-6">
                    <span class="text-[10px] font-black uppercase tracking-widest text-[#E50914] block">TFL PARTS CORPORATIVO</span>
                    <h2 class="text-3xl font-black text-gray-900 leading-tight uppercase tracking-tight">
                        Quiénes Somos & Misión
                    </h2>
                    <p class="text-gray-600 text-xs leading-relaxed font-semibold">
                        {{ $store->description }}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 text-xs font-bold text-gray-700">
                        <div class="flex items-start gap-4 border-l-2 border-[#E50914] pl-4">
                            <div>
                                <h4 class="text-gray-900 uppercase font-black tracking-wider text-[11px]">Distribuidor Autorizado B2B</h4>
                                <p class="text-gray-500 font-medium text-[11px] mt-0.5">Distribución directa para empresas y distribuidores autorizados.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 border-l-2 border-[#E50914] pl-4">
                            <div>
                                <h4 class="text-gray-900 uppercase font-black tracking-wider text-[11px]">Repuestos Certificados</h4>
                                <p class="text-gray-500 font-medium text-[11px] mt-0.5">Componentes certificados que garantizan la operatividad en el campo.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Right Side (Sleek minimalist panel) -->
                <div class="lg:col-span-5 hex-bg rounded-xl p-8 border border-gray-900 text-center min-h-[280px] flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(var(--accent) 1px, transparent 1px); background-size: 20px 20px;"></div>
                    <div class="my-auto relative z-10">
                        <svg class="w-12 h-12 mx-auto text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div class="space-y-1 relative z-10">
                        <h4 class="text-[10px] font-black uppercase text-white tracking-widest">SHOWROOM CENTRAL</h4>
                        <p class="text-[11px] text-gray-400 font-semibold">Av. Agricultura 450, Lambayeque</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Global Distributors Section -->
    @if(!empty($store->distributors))
    <section class="py-16 bg-gray-50 border-t border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="border-b border-gray-200 pb-4 mb-10">
                <h2 class="text-xs font-black uppercase tracking-widest text-gray-900">RED DE DISTRIBUIDORES GLOBALES</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-xs font-semibold text-gray-600 animate-fade-in-up">
                @foreach($store->distributors as $dist)
                    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm space-y-4">
                        <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">{{ $dist['region'] ?? '' }}</h3>
                        <ul class="space-y-3 font-semibold text-gray-500">
                            @if(!empty($dist['locations']))
                                @foreach($dist['locations'] as $loc)
                                    <li class="flex items-center gap-2 pl-2 border-l border-gray-200">
                                        <span>{{ $loc }}</span>
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Photo Gallery / Showroom Section -->
    <section id="galeria" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="border-b border-gray-200 pb-4 mb-12">
                <h2 class="text-sm font-black uppercase tracking-widest text-gray-900">GALERÍA / PORTAFOLIO</h2>
            </div>

            @if($galleryItems->isEmpty())
                <div class="text-center py-12 bg-white rounded border border-gray-200">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-gray-400 text-xs font-semibold">Próximamente fotos de la galería...</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 animate-fade-in-up">
                    @foreach($galleryItems as $item)
                        <div class="bg-white rounded border border-gray-200 overflow-hidden shadow-sm group hover:shadow-md transition-all">
                            <div class="aspect-square bg-gray-50 flex items-center justify-center relative overflow-hidden border-b border-gray-100">
                                @if($item->image_path)
                                    <img src="{{ $item->image_url }}" alt="{{ $item->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                @else
                                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="p-4">
                                <h4 class="font-black text-xs uppercase tracking-wider text-gray-900 leading-tight">{{ $item->title }}</h4>
                                @if($item->description)
                                    <p class="text-[11px] text-gray-500 mt-1 leading-snug">{{ $item->description }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- Dedicated Suppliers Area (Integrate Brand Images) -->
    <section class="py-16 bg-gray-50 border-t border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-8">
            <h4 class="text-[10px] font-black uppercase tracking-widest text-[#E50914]">NUESTROS PROVEEDORES CERTIFICADOS</h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-6 animate-fade-in-up">
                @php
                    $supplierBrands = [
                        ['id' => 'john_deere', 'name' => 'JOHN DEERE'],
                        ['id' => 'perkins', 'name' => 'PERKINS'],
                        ['id' => 'massey_ferguson', 'name' => 'MASSEY FERGUSON'],
                        ['id' => 'cummins', 'name' => 'CUMMINS'],
                        ['id' => 'donaldson', 'name' => 'DONALDSON'],
                    ];
                @endphp
                @foreach($supplierBrands as $brand)
                    <div class="h-16 border border-gray-200 bg-white rounded flex items-center justify-center p-4 hover:border-[#E50914] transition-colors relative group">
                        <!-- Try to load image if exists, else fall back to styling -->
                        <img src="{{ asset('storage/suppliers/' . $brand['id'] . '.svg') }}" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" 
                             alt="{{ $brand['name'] }}" 
                             class="max-h-full max-w-full object-contain grayscale opacity-50 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-300">
                        <span class="hidden font-extrabold tracking-widest text-gray-400 text-xs uppercase">{{ $brand['name'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Footer / Contact -->
    <footer id="contacto" class="bg-gray-900 text-white pt-16 pb-8 scroll-mt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 pb-12 border-b border-gray-800">
                <!-- Brand Info -->
                <div class="space-y-4">
                    <a href="#" class="flex items-center">
                        @if($store->logo_path)
                            <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-10 w-auto bg-white p-1 rounded">
                        @else
                            <span class="text-xl font-black text-white">TFL <span class="text-[#E50914]">PARTS</span></span>
                        @endif
                    </a>
                    <p class="text-[11px] text-gray-400 leading-relaxed font-semibold">
                        Importadores y distribuidores premium de repuestos para maquinaria agrícola. Garantizamos durabilidad y un despacho inmediato.
                    </p>
                </div>

                <!-- Fast Links -->
                <div class="space-y-4">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[#E50914]">CORPORATIVO</h4>
                    <ul class="space-y-2 text-[11px] text-gray-400 uppercase tracking-wider font-bold">
                        <li><a href="#" class="hover:text-white transition-colors">HOME</a></li>
                        <li><a href="#nosotros" class="hover:text-white transition-colors">CORPORATE</a></li>
                        <li><a href="{{ route('store.catalog', $store->slug) }}" class="hover:text-white transition-colors">SHOWROOM</a></li>
                        <li><a href="#galeria" class="hover:text-white transition-colors">GALERÍA</a></li>
                    </ul>
                </div>

                <!-- Contact Detail -->
                <div class="space-y-4">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[#E50914]">CONTACTO</h4>
                    <ul class="space-y-2 text-[11px] text-gray-400 font-semibold uppercase tracking-wider">
                        @if($store->phone)
                            <li>VENTAS: {{ $store->phone }}</li>
                        @endif
                        @if($store->whatsapp_phone)
                            <li>WHATSAPP: +{{ $store->whatsapp_phone }}</li>
                        @endif
                        @if($store->email)
                            <li>CORREO: {{ $store->email }}</li>
                        @endif
                        <li>DIRECCIÓN: {{ $store->address ?? 'Chiclayo, Lambayeque' }}</li>
                    </ul>
                </div>

                <!-- Socials -->
                <div class="space-y-4">
                    <h4 class="text-xs font-black uppercase tracking-widest text-[#E50914]">REDES SOCIALES</h4>
                    <div class="flex gap-3 text-[11px] text-gray-400 font-bold uppercase tracking-wider">
                        @if($store->facebook_url)
                            <a href="{{ $store->facebook_url }}" target="_blank" class="hover:text-white">Facebook</a>
                        @endif
                        @if($store->instagram_url)
                            <a href="{{ $store->instagram_url }}" target="_blank" class="hover:text-white">Instagram</a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Bottom footer -->
            <div class="flex flex-col sm:flex-row justify-between items-center pt-8 gap-4 text-xs text-gray-500">
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
