<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} - Tienda Online</title>
    <!-- Use a modern, friendly sans-serif font similar to the screenshot -->
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --accent: {{ $store->accent_color ?? '#8BC34A' }};
            --secondary: {{ $store->secondary_color ?? '#FF9800' }};
            --bg: {{ $store->bg_color ?? '#ffffff' }};
            --text-dark: #333333;
            --text-light: #666666;
        }
        body {
            background-color: #f9f9f9;
            font-family: 'Quicksand', sans-serif;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Custom Utilities */
        .hover-text-accent:hover { color: var(--accent); }
        .bg-accent { background-color: var(--accent); }
        .text-accent { color: var(--accent); }
    </style>
</head>
<body class="antialiased">

    <!-- Header -->
    <header class="bg-white sticky top-0 z-50 border-b border-gray-100 shadow-sm" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ route('store.show', $store->slug) }}">
                        @if($store->logo_path)
                            <img class="h-12 w-auto" src="{{ $store->logo_url }}" alt="{{ $store->name }}">
                        @else
                            <span class="font-bold text-2xl tracking-tight text-accent">{{ $store->name }}</span>
                        @endif
                    </a>
                </div>

                <!-- Desktop Navigation -->
                <nav class="hidden md:flex space-x-6">
                    <a href="#" class="text-gray-700 hover-text-accent font-semibold text-sm transition flex items-center gap-1">Dormitorio <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></a>
                    <a href="#" class="text-gray-700 hover-text-accent font-semibold text-sm transition flex items-center gap-1">Cocina <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></a>
                    <a href="#" class="text-gray-700 hover-text-accent font-semibold text-sm transition flex items-center gap-1">Baño <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></a>
                    <a href="#" class="text-gray-700 hover-text-accent font-semibold text-sm transition flex items-center gap-1">Lavandería <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></a>
                    <a href="#" class="text-gray-700 hover-text-accent font-semibold text-sm transition flex items-center gap-1">Mascotas <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></a>
                    <a href="#" class="text-gray-700 hover-text-accent font-semibold text-sm transition flex items-center gap-1">Promociones <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></a>
                    <a href="#" class="text-gray-700 hover-text-accent font-semibold text-sm transition">Lo nuevo</a>
                    <a href="{{ route('store.catalog', $store->slug) }}" class="text-gray-700 hover-text-accent font-semibold text-sm transition">Todos</a>
                </nav>

                <!-- Icons (Search, User, Wishlist, Cart) -->
                <div class="flex items-center space-x-5">
                    <button class="text-gray-700 hover-text-accent transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </button>
                    <button class="text-gray-700 hover-text-accent transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </button>
                    <button class="text-gray-700 hover-text-accent transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    </button>
                    <button onclick="document.getElementById('cartDrawer').style.display='flex'" class="text-gray-700 hover-text-accent transition relative" x-data>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span data-cart-count class="absolute -top-2 -right-2 bg-accent text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">0</span>
                    </button>
                    <!-- Mobile Menu Button -->
                    <button class="md:hidden text-gray-700" @click="mobileMenuOpen = !mobileMenuOpen">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div class="md:hidden" x-show="mobileMenuOpen" style="display: none;">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-white border-b border-gray-100">
                <a href="#" class="block px-3 py-2 text-base font-medium text-gray-700">Dormitorio</a>
                <a href="#" class="block px-3 py-2 text-base font-medium text-gray-700">Cocina</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="block px-3 py-2 text-base font-medium text-gray-700">Todos</a>
            </div>
        </div>
    </header>

    <main class="flex-grow">
        <!-- Highlights/Categories Row -->
        <section class="py-8 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap justify-center gap-4 md:gap-8">
                    @php
                        $highlights = [
                            ['title' => 'Nuevo', 'bg' => '#F59E0B', 'text' => 'white', 'icon' => 'New'],
                            ['title' => 'Outlet', 'bg' => '#fef08a', 'text' => 'gray-800', 'icon' => '🏷️'],
                            ['title' => 'Esenciales', 'bg' => '#f3f4f6', 'text' => 'gray-800', 'icon' => '🗄️'],
                            ['title' => 'De regreso', 'bg' => '#dcfce7', 'text' => 'gray-800', 'icon' => '🍽️'],
                            ['title' => 'Deco', 'bg' => '#bbf7d0', 'text' => 'gray-800', 'icon' => '🪴'],
                            ['title' => 'Por mayor', 'bg' => '#ddd6fe', 'text' => 'gray-800', 'icon' => '📦'],
                            ['title' => 'De S/5', 'bg' => '#84cc16', 'text' => 'white', 'icon' => 'S/ 5'],
                            ['title' => 'De S/10', 'bg' => '#0ea5e9', 'text' => 'white', 'icon' => 'S/ 10'],
                        ];
                    @endphp
                    @foreach($highlights as $h)
                        <div class="flex flex-col items-center gap-2 cursor-pointer group">
                            <div class="w-16 h-16 md:w-20 md:h-20 rounded-xl flex items-center justify-center text-lg md:text-xl font-bold shadow-sm group-hover:shadow-md transition-shadow" 
                                 style="background-color: {{ $h['bg'] }}; color: {{ $h['text'] === 'white' ? '#fff' : '#1f2937' }}">
                                {{ $h['icon'] }}
                            </div>
                            <span class="text-xs md:text-sm font-medium text-gray-700">{{ $h['title'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Hero Banner -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="relative w-full rounded-2xl overflow-hidden shadow-sm bg-gradient-to-r from-green-500 to-green-600 h-[250px] md:h-[350px] flex items-center">
                <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
                <div class="relative z-10 p-8 md:p-12 text-white w-full">
                    <div class="max-w-xl">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-8 h-8 md:w-12 md:h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            <h2 class="text-3xl md:text-5xl font-black italic tracking-tight">ENVÍO EXPRESS</h2>
                        </div>
                        <div class="inline-block bg-orange-500 text-white font-black px-4 py-1 rounded-full text-xl md:text-3xl mb-6 shadow-md transform -rotate-2">
                            SIN COSTO
                        </div>
                        
                        <div class="bg-white/20 backdrop-blur-sm rounded-full px-4 py-2 inline-flex items-center gap-2 mb-8 border border-white/30">
                            <span class="font-bold">COMPRA HOY</span> 
                            <span class="text-orange-300 font-medium">y recoge a partir de</span> 
                            <span class="bg-orange-500 text-white font-bold px-2 py-0.5 rounded">60 min</span>
                        </div>

                        <div>
                            <a href="{{ route('store.catalog', $store->slug) }}" class="inline-flex items-center gap-2 bg-transparent border-2 border-white text-white hover:bg-white hover:text-green-600 font-bold py-3 px-8 rounded-full transition-colors text-lg">
                                COMPRAR AHORA 
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>
                <!-- Decorative elements on the right (Optional if you have images, skipped for now to keep it clean) -->
                <div class="absolute right-0 bottom-0 top-0 w-1/3 hidden lg:block opacity-20 bg-cover bg-left" style="background-image: url('https://images.unsplash.com/photo-1556228453-efd6c1ff04f6?auto=format&fit=crop&q=80'); mask-image: linear-gradient(to right, transparent, black);">
                </div>
            </div>
            <p class="text-center text-xs text-gray-500 mt-2">*Válido para pedidos realizados hasta las 5:00 p.m. Aplica T&C.</p>
        </section>

        <!-- Featured Categories -->
        <section class="py-12 bg-white border-t border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-xl font-bold text-[#1a2f4c] mb-6">Categorías destacadas</h2>
                
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    @php
                        $featuredCats = [
                            ['title' => 'Más vendidos', 'bg' => '#fbe9d0', 'icon' => 'star', 'image' => 'https://images.unsplash.com/photo-1576020786968-07e15d7f1d46?auto=format&fit=crop&q=80&w=300'],
                            ['title' => 'Organización', 'bg' => '#d4e6f1', 'icon' => 'box', 'image' => 'https://images.unsplash.com/photo-1584286595398-a59f21d313f5?auto=format&fit=crop&q=80&w=300'],
                            ['title' => 'Cocina', 'bg' => '#e5f0c4', 'icon' => 'pot', 'image' => 'https://images.unsplash.com/photo-1556910103-1c02745a828c?auto=format&fit=crop&q=80&w=300'],
                            ['title' => 'Baño', 'bg' => '#e8dff0', 'icon' => 'bath', 'image' => 'https://images.unsplash.com/photo-1620626011761-996317b8d101?auto=format&fit=crop&q=80&w=300'],
                            ['title' => 'Decoración', 'bg' => '#fadbd8', 'icon' => 'plant', 'image' => 'https://images.unsplash.com/photo-1513161455079-7dc1de15ef3e?auto=format&fit=crop&q=80&w=300'],
                            ['title' => 'Irresistibles', 'bg' => '#d1f2eb', 'icon' => 'coin', 'image' => null, 'custom' => '<div class="absolute inset-0 flex flex-col items-center justify-center"><span class="text-red-600 font-bold text-[15px] tracking-wide mb-1 z-10">DESDE</span><span class="bg-red-600 text-white font-black text-3xl px-4 py-1.5 rounded-lg shadow-sm z-10">S/ 1.20</span></div>'],
                        ];
                    @endphp
                    @foreach($featuredCats as $cat)
                        <div class="relative overflow-hidden rounded-sm h-56 flex flex-col items-center justify-center cursor-pointer group" style="background-color: {{ $cat['bg'] }}">
                            <!-- Image -->
                            @if($cat['image'])
                                <img src="{{ $cat['image'] }}" class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-80 group-hover:scale-105 transition-transform duration-500">
                            @elseif(!empty($cat['custom']))
                                {!! $cat['custom'] !!}
                            @endif

                            <!-- Bottom Gradient Overlay -->
                            <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/50 to-transparent z-0"></div>

                            <!-- Bottom Elements (Icon Pill + Title) -->
                            <div class="absolute bottom-0 left-0 right-0 flex items-end z-10">
                                <!-- The pill shape (bottom left, top right rounded) -->
                                <div class="bg-black/30 rounded-tr-[2rem] w-14 h-12 flex items-center justify-center backdrop-blur-sm relative overflow-hidden">
                                    <div class="absolute inset-0 bg-white/10 mix-blend-overlay"></div>
                                    @if($cat['icon'] == 'star')
                                        <svg class="w-6 h-6 text-white/90 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"></path></svg>
                                    @elseif($cat['icon'] == 'box')
                                        <svg class="w-6 h-6 text-white/90 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    @elseif($cat['icon'] == 'pot')
                                        <svg class="w-6 h-6 text-white/90 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v1a5 5 0 01-5 5H8a5 5 0 01-5-5v-1a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                    @elseif($cat['icon'] == 'bath')
                                        <svg class="w-6 h-6 text-white/90 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4M9 21v-5a3 3 0 016 0v5"></path></svg>
                                    @elseif($cat['icon'] == 'plant')
                                        <svg class="w-6 h-6 text-white/90 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                                    @elseif($cat['icon'] == 'coin')
                                        <svg class="w-6 h-6 text-white/90 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="1.5"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="600" fill="currentColor">S/</text></svg>
                                    @endif
                                </div>
                                <div class="px-3 pb-3">
                                    <span class="text-white text-[11px] font-bold tracking-wide">{{ $cat['title'] }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Products Section -->
        <section class="py-12 bg-[#f9f9f9]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-8 uppercase tracking-wide border-b-2 border-accent pb-2 inline-block">Nuestros Productos</h2>
                
                @if($allProducts->isEmpty())
                <p class="text-gray-400 text-center py-12">Próximamente productos disponibles...</p>
                @else
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    @foreach($allProducts as $product)
                    <div class="bg-white rounded-xl overflow-hidden hover:shadow-lg transition-shadow border border-gray-100 group flex flex-col relative">
                        
                        <!-- Actions overlay -->
                        <div class="absolute top-2 right-2 flex flex-col gap-2 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                            <button class="w-8 h-8 bg-white rounded-full flex items-center justify-center text-gray-500 hover:text-red-500 shadow-sm border border-gray-100">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                            </button>
                        </div>

                        <a href="{{ route('store.product', [$store->slug, $product->slug]) }}" class="block relative aspect-square overflow-hidden bg-gray-50 p-4">
                            @if($product->image_path)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                     class="w-full h-full object-contain mix-blend-multiply group-hover:scale-105 transition-transform duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-4xl text-gray-300">📦</div>
                            @endif
                        </a>
                        
                        <div class="p-4 flex flex-col flex-grow">
                            <!-- Brand / Category placeholder -->
                            <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider mb-1">EN KASA</span>
                            
                            <a href="{{ route('store.product', [$store->slug, $product->slug]) }}" class="text-gray-800 font-medium text-sm leading-snug line-clamp-2 hover-text-accent mb-2 flex-grow">
                                {{ $product->name }}
                            </a>
                            
                            <div class="mt-auto">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="font-bold text-lg text-gray-900">S/ {{ number_format($product->price, 2) }}</span>
                                    @if($product->compare_price)
                                    <span class="text-gray-400 text-xs line-through">S/ {{ number_format($product->compare_price, 2) }}</span>
                                    @endif
                                </div>
                                
                                <button onclick="window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, '{{ $product->image_path ? $product->image_url : '' }}')" 
                                        class="w-full py-2 bg-gray-900 hover:bg-accent text-white rounded-lg text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    Agregar
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </section>
    </main>

    <!-- Footer Features -->
    <div class="bg-white border-y border-gray-200 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center divide-x divide-gray-100">
                <div class="px-4">
                    <svg class="w-8 h-8 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                    <h4 class="font-bold text-sm text-gray-900 mb-1">Envíos a nivel nacional</h4>
                    <p class="text-xs text-gray-500">Llegamos a todo el Perú</p>
                </div>
                <div class="px-4">
                    <svg class="w-8 h-8 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    <h4 class="font-bold text-sm text-gray-900 mb-1">Múltiples formas de pago</h4>
                    <p class="text-xs text-gray-500">Aceptamos todas las tarjetas</p>
                </div>
                <div class="px-4">
                    <svg class="w-8 h-8 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <h4 class="font-bold text-sm text-gray-900 mb-1">Pagos seguros</h4>
                    <p class="text-xs text-gray-500">Pasarelas confiables</p>
                </div>
                <div class="px-4">
                    <svg class="w-8 h-8 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    <h4 class="font-bold text-sm text-gray-900 mb-1">Asesoría personalizada</h4>
                    <p class="text-xs text-gray-500">Te ayudamos en el proceso</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer -->
    <footer class="bg-white pt-12 pb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Newsletter (Optional, keeping it clean as in design) -->
            <div class="max-w-2xl mx-auto text-center mb-12">
                <p class="text-xs text-gray-500 mb-4">Al registrarte aceptas recibir correos de marketing. Consulta nuestra Política de privacidad y nuestros Términos del Servicio para obtener más información.</p>
            </div>

            <!-- Links -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
                <div>
                    <h4 class="font-bold text-sm text-gray-900 mb-4">La marca</h4>
                    <ul class="space-y-3 text-xs text-gray-500">
                        <li><a href="#" class="hover-text-accent">Nosotros</a></li>
                        <li><a href="#" class="hover-text-accent">Contacto</a></li>
                        <li><a href="#" class="hover-text-accent">Noticias</a></li>
                        <li><a href="#" class="hover-text-accent">Seguimiento del pedido</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-sm text-gray-900 mb-4">Categorías</h4>
                    <ul class="space-y-3 text-xs text-gray-500">
                        <li><a href="#" class="hover-text-accent">Dormitorio</a></li>
                        <li><a href="#" class="hover-text-accent">Cocina</a></li>
                        <li><a href="#" class="hover-text-accent">Baño</a></li>
                        <li><a href="#" class="hover-text-accent">Lavandería</a></li>
                        <li><a href="#" class="hover-text-accent">Percheros</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-sm text-gray-900 mb-4">Ayuda al cliente</h4>
                    <ul class="space-y-3 text-xs text-gray-500">
                        <li><a href="#" class="hover-text-accent">Política de Envíos</a></li>
                        <li><a href="#" class="hover-text-accent">Política de Privacidad</a></li>
                        <li><a href="#" class="hover-text-accent">Política de Cambios</a></li>
                        <li><a href="#" class="hover-text-accent">Términos del Servicio</a></li>
                        <li><a href="#" class="hover-text-accent">Libro de reclamaciones</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold text-sm text-gray-900 mb-4">Mi cuenta</h4>
                    <ul class="space-y-3 text-xs text-gray-500">
                        <li><a href="#" class="hover-text-accent">Mis datos</a></li>
                        <li><a href="#" class="hover-text-accent">Mis pedidos</a></li>
                        <li><a href="#" class="hover-text-accent">Mis direcciones</a></li>
                        <li><a href="#" class="hover-text-accent">Mis favoritos</a></li>
                        <li><a href="#" class="hover-text-accent">Cambiar contraseña</a></li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Footer -->
            <div class="flex flex-col md:flex-row justify-between items-center pt-8 border-t border-gray-100">
                <div class="mb-4 md:mb-0 flex flex-col gap-4">
                    <a href="#" class="inline-flex items-center gap-2 bg-gray-100 text-gray-600 px-4 py-2 rounded text-xs font-semibold hover:bg-gray-200 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        Libro de Reclamaciones
                    </a>
                    <!-- Payment Methods (Placeholder badges) -->
                    <div class="flex gap-2">
                        <span class="bg-gray-100 text-gray-600 text-[9px] font-bold px-2 py-1 rounded">VISA</span>
                        <span class="bg-gray-100 text-gray-600 text-[9px] font-bold px-2 py-1 rounded">MC</span>
                        <span class="bg-gray-100 text-gray-600 text-[9px] font-bold px-2 py-1 rounded">AMEX</span>
                    </div>
                </div>
                
                <div class="flex flex-col items-end gap-4">
                    <!-- Socials -->
                    <div class="flex gap-3">
                        <a href="#" class="w-8 h-8 bg-gray-100 text-gray-600 rounded flex items-center justify-center hover:bg-accent hover:text-white transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="#" class="w-8 h-8 bg-gray-100 text-gray-600 rounded flex items-center justify-center hover:bg-accent hover:text-white transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        </a>
                    </div>
                    <p class="text-xs text-gray-400">© 2026 {{ $store->name }}. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>

    {{-- Cart Drawer (Keep the same logic we just built for checkout) --}}
    <div id="cartDrawer" x-data="{
             checkoutStep: 1,
             customer: { name: '', phone: '', address: '', notes: '', express_shipping: false },
             storeSlug: '{{ $store->slug }}',
             isExpressEnabled: {{ $store->is_express_shipping_enabled ? 'true' : 'false' }},
             expressCost: {{ $store->express_shipping_cost ?? 0 }},
             get cartItems() { return window.TribioCart ? window.TribioCart.items : []; },
             get cartTotal() { 
                 let total = this.cartItems.reduce((acc, item) => acc + (item.price * item.quantity), 0);
                 if (this.customer.express_shipping) total += this.expressCost;
                 return total;
             },
             submitOrder() {
                 if(!this.customer.name || !this.customer.phone) {
                     alert('Por favor completa los campos obligatorios (Nombre y Teléfono).');
                     return;
                 }
                 if(window.TribioCart) {
                     const btn = document.getElementById('btnSubmitOrder');
                     btn.innerText = 'Procesando...';
                     btn.disabled = true;
                     window.TribioCart.checkout(this.storeSlug, this.customer);
                 }
             }
         }"
         @cart-updated.window="$forceUpdate()"
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
                                <template x-if="!item.image">
                                    <div class="w-16 h-16 bg-white border border-gray-200 rounded-lg flex items-center justify-center text-xl shadow-sm">📦</div>
                                </template>
                                <div class="flex-1">
                                    <h4 class="text-gray-800 font-semibold text-sm leading-tight" x-text="item.name"></h4>
                                    <div class="flex justify-between items-center mt-2">
                                        <p class="text-accent font-bold text-sm" x-text="'S/ ' + (item.price * item.quantity).toFixed(2)"></p>
                                        <div class="flex items-center gap-2 text-gray-600 text-xs bg-white rounded-full border border-gray-200 p-1">
                                            <button @click="window.TribioCart.updateQuantity(item.id, item.quantity - 1); $dispatch('cart-updated')" class="w-5 h-5 rounded-full hover:bg-gray-100 flex items-center justify-center font-bold">-</button>
                                            <span x-text="item.quantity" class="w-4 text-center font-medium"></span>
                                            <button @click="window.TribioCart.updateQuantity(item.id, item.quantity + 1); $dispatch('cart-updated')" class="w-5 h-5 rounded-full hover:bg-gray-100 flex items-center justify-center font-bold">+</button>
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
                            <input type="text" x-model="customer.name" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Teléfono (WhatsApp) *</label>
                            <input type="text" x-model="customer.phone" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Dirección de Envío</label>
                            <input type="text" x-model="customer.address" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition">
                        </div>
                        
                        <template x-if="isExpressEnabled">
                            <div class="p-4 bg-green-50 border border-green-200 rounded-xl mt-4 relative overflow-hidden">
                                <div class="absolute right-0 top-0 bottom-0 opacity-10">
                                    <svg class="w-16 h-16 text-green-600 transform translate-x-2 -translate-y-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <label class="flex items-start gap-3 cursor-pointer relative z-10">
                                    <input type="checkbox" x-model="customer.express_shipping" class="mt-1 accent-green-600 w-4 h-4 rounded">
                                    <div>
                                        <p class="font-bold text-sm text-green-700 flex items-center gap-1">🚀 ¡Quiero Envío Express!</p>
                                        <p class="text-xs text-green-600/80 mt-1">Llega más rápido a tu domicilio. <span x-show="expressCost > 0" x-text="'+ S/ ' + expressCost.toFixed(2)"></span><span x-show="expressCost == 0">¡Es gratis!</span></p>
                                    </div>
                                </label>
                            </div>
                        </template>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1 mt-2">Notas adicionales</label>
                            <textarea x-model="customer.notes" rows="2" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition"></textarea>
                        </div>
                    </div>
                </template>
            </div>
            
            <template x-if="cartItems.length > 0">
                <div class="p-5 border-t border-gray-100 bg-gray-50">
                    <div class="flex justify-between items-center mb-4 text-gray-800">
                        <span class="font-bold text-sm">Total a pagar:</span>
                        <span class="font-black text-xl text-accent" x-text="'S/ ' + cartTotal.toFixed(2)"></span>
                    </div>
                    
                    <template x-if="checkoutStep === 1">
                        <button @click="checkoutStep = 2" class="w-full py-3 rounded-xl font-bold text-white transition-all shadow-md text-center flex items-center justify-center gap-2" style="background: var(--accent)">
                            Siguiente Paso →
                        </button>
                    </template>
                    
                    <template x-if="checkoutStep === 2">
                        <div class="flex gap-2">
                            <button @click="checkoutStep = 1" class="w-12 h-12 flex-shrink-0 flex items-center justify-center rounded-xl bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors">
                                ←
                            </button>
                            <button id="btnSubmitOrder" @click="submitOrder()" class="flex-1 py-3 rounded-xl font-bold text-white transition-all shadow-md text-center" style="background: var(--accent)">
                                Confirmar y Pagar
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

</body>
</html>
