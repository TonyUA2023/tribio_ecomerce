{{-- Placeholder - Elegant Dark Store Template --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} - Tienda Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --accent: {{ $store->accent_color }};
            --secondary: {{ $store->secondary_color }};
            --bg: {{ $store->bg_color }};
        }
    </style>
</head>
<body style="background: {{ $store->bg_color }}; font-family: 'Outfit', sans-serif; color: white; min-height: 100vh;">

    {{-- Navbar --}}
    <nav style="background: rgba(0,0,0,0.5); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,0.08);"
         class="sticky top-0 z-50 px-6 py-4">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                @if($store->logo_path)
                    <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-10 w-auto">
                @else
                    <h1 class="text-xl font-black" style="color: {{ $store->accent_color }}">{{ $store->name }}</h1>
                @endif
            </div>
            <div class="flex items-center gap-4">
                <button class="relative" onclick="document.getElementById('cartDrawer').style.display = 'flex'">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span data-cart-count class="absolute -top-2 -right-2 w-5 h-5 rounded-full text-white text-xs font-bold flex items-center justify-center"
                          style="background: {{ $store->accent_color }}; display: none;"></span>
                </button>
                @if($store->whatsapp_phone)
                <a href="{{ $store->whatsapp_link }}" target="_blank" class="btn-whatsapp py-2 px-4 text-sm">
                    WhatsApp
                </a>
                @endif
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="relative py-24 text-center overflow-hidden"
             style="background: linear-gradient(135deg, rgba(124,58,237,0.2) 0%, transparent 60%), {{ $store->bg_color }};">
        <div class="absolute inset-0" style="background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 50px 50px;"></div>
        <div class="relative z-10 container-tribio">
            @if($store->logo_path)
            <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-24 w-auto mx-auto mb-6 object-contain">
            @else
            <div class="w-24 h-24 rounded-3xl mx-auto mb-6 flex items-center justify-center text-4xl"
                 style="background: {{ $store->accent_color }}22; border: 1px solid {{ $store->accent_color }}44;">
                🏪
            </div>
            @endif
            <h1 class="text-5xl font-black text-white mb-4">{{ $store->name }}</h1>
            @if($store->tagline)
            <p class="text-xl text-white/60">{{ $store->tagline }}</p>
            @endif
        </div>
    </section>

    {{-- Products --}}
    <section class="py-16">
        <div class="container-tribio">
            <h2 class="text-2xl font-bold text-white mb-8">Nuestros Productos</h2>
            @if($allProducts->isEmpty())
            <p class="text-white/40 text-center py-12">Próximamente productos disponibles...</p>
            @else
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                @foreach($allProducts as $product)
                <div class="glass-card overflow-hidden group hover:border-white/20 transition-all cursor-pointer"
                     onclick="window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, '{{ $product->image_path ? $product->image_url : '' }}')">
                    <div class="aspect-square overflow-hidden bg-white/5">
                        @if($product->image_path)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-5xl">📦</div>
                        @endif
                    </div>
                    <div class="p-4">
                        <p class="text-white font-semibold text-sm leading-tight">{{ $product->name }}</p>
                        @if($product->short_description)
                        <p class="text-white/40 text-xs mt-1 line-clamp-2">{{ $product->short_description }}</p>
                        @endif
                        <div class="flex items-center justify-between mt-3">
                            <div>
                                <span class="font-black text-lg" style="color: {{ $store->secondary_color }}">S/. {{ number_format($product->price, 2) }}</span>
                                @if($product->compare_price)
                                <span class="text-white/30 text-xs line-through ml-1">S/. {{ number_format($product->compare_price, 2) }}</span>
                                @endif
                            </div>
                            <button class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold transition-all hover:scale-110"
                                    style="background: {{ $store->accent_color }}">+</button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </section>

    {{-- Cart Drawer --}}
    <div id="cartDrawer" x-data="{ open: false }" style="display:none; position: fixed; inset: 0; z-index: 999; justify-content: flex-end;">
        <div style="background: rgba(0,0,0,0.5);" class="absolute inset-0" onclick="document.getElementById('cartDrawer').style.display='none'"></div>
        <div class="relative w-80 h-full flex flex-col" style="background: #1A1A2E; border-left: 1px solid rgba(255,255,255,0.1);">
            <div class="flex items-center justify-between p-5 border-b" style="border-color: rgba(255,255,255,0.1);">
                <h3 class="text-white font-bold">🛒 Mi carrito</h3>
                <button onclick="document.getElementById('cartDrawer').style.display='none'" class="text-white/50 hover:text-white">✕</button>
            </div>
            <div class="flex-1 p-5 overflow-y-auto">
                <p class="text-white/40 text-sm text-center mt-8">Agrega productos para ver tu carrito</p>
            </div>
            <div class="p-5 border-t" style="border-color: rgba(255,255,255,0.1);">
                @if($store->whatsapp_phone)
                <a href="{{ $store->whatsapp_link }}" target="_blank" class="btn-whatsapp w-full justify-center">
                    Pedir por WhatsApp
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <footer class="py-10 border-t mt-16" style="border-color: rgba(255,255,255,0.05);">
        <div class="container-tribio text-center">
            <p class="text-white/20 text-sm">{{ $store->name }} • Hecho con <span style="color: {{ $store->accent_color }}">♥</span> en Tribio</p>
            @if($store->whatsapp_phone)
            <a href="{{ $store->whatsapp_link }}" target="_blank" class="text-green-400 text-sm hover:text-green-300 mt-2 inline-block">
                📱 {{ $store->whatsapp_phone }}
            </a>
            @endif
        </div>
    </footer>

</body>
</html>
