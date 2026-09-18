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

    <!-- Dynamic Header will be rendered in the section loop -->

    @if(isset($sections) && $sections->isNotEmpty())
        @foreach($sections as $section)
            @if(View::exists("components.store-sections.{$section->type}"))
                <div class="tribio-section-wrapper relative group" data-section-id="{{ $section->id }}" id="section-{{ $section->id }}">
                    <div class="tribio-section-content">
                        @include("components.store-sections.{$section->type}", ['data' => $section->data])
                    </div>
                </div>
            @endif
        @endforeach
        
        @if(request()->query('editor'))
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
            @include('components.store-sections.editor-scripts')
        @endif
    @else
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
                         onclick="window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, '{{ $product->image_path ? $product->image_url : '' }}'); window.dispatchEvent(new CustomEvent('open-cart-drawer'));">
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
    @endif

    <!-- Cart Trigger flotante (visible cuando no hay header configurado en el constructor) -->
    <button onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer'))"
            class="fixed bottom-6 left-6 z-40 w-14 h-14 rounded-full flex items-center justify-center text-white shadow-2xl hover:scale-105 transition-all"
            style="background: {{ $store->accent_color }};" aria-label="Carrito">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        <span data-cart-count class="absolute -top-1 -right-1 bg-white text-gray-900 text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow">0</span>
    </button>

    {{-- Pasarela de pago estándar (carrito + Tribio Pass) --}}
    @include('components.checkout.gateway')

    <!-- Dynamic Footer will be rendered in the section loop -->

</body>
</html>
