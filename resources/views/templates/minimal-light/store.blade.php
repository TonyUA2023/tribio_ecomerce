<!DOCTYPE html>
<html lang="{{ \App\Helpers\TranslationHelper::currentLang() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $store->name }} - Tienda Online</title>
    @if(isset($store) && $store->logo_path)
        <link rel="icon" type="image/png" href="{{ $store->logo_url }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif
    <!-- Fonts: Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --accent: {{ $store->accent_color ?? '#1A1A1A' }};
            --secondary: {{ $store->secondary_color ?? '#C8A68B' }};
            --bg: #FDF8EF;
            --text-dark: #1A1A1A;
            --text-light: #666666;
            
            --font-sans: 'Plus Jakarta Sans', sans-serif;
        }
        body {
            background-color: var(--bg);
            font-family: var(--font-sans);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }
        
        /* Custom Utilities */
        . { font-family: var(--) !important; }
        .hover-text-accent:hover { color: var(--accent); }
        .bg-accent { background-color: var(--accent); }
        .bg-secondary { background-color: var(--secondary); }
        .text-accent { color: var(--accent); }
        .text-secondary { color: var(--secondary); }
        
        /* Hide Google Translate Widget */
        .goog-te-banner-frame.skiptranslate, .goog-te-gadget-icon { display: none !important; }
        body { top: 0px !important; }
        #goog-gt-tt, .goog-te-balloon-frame { display: none !important; }
        .goog-text-highlight { background: none !important; box-shadow: none !important; }
    </style>
</head>
<body class="antialiased relative bg-[#FDF8EF]">
    
    <!-- Google Translate Script -->
    <div id="google_translate_element" style="display:none;"></div>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({pageLanguage: 'es', includedLanguages: 'en,es', autoDisplay: false}, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    <!-- Unified Header -->
    @include('templates.minimal-light.header')

    <main class="flex-grow bg-[#FDF8EF]">
        <!-- Full-Width Professional Hero Banner -->
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap');
            .font-script { font-family: 'Great Vibes', cursive; }
        </style>
        
        <section class="relative w-full h-[60vh] md:h-[80vh] min-h-[500px] overflow-hidden bg-[#FDF8EF]" x-data="{ currentSlide: 1, totalSlides: 3 }">
            <!-- Background Images (Carousel) -->
            <div class="absolute inset-0 w-full h-full">
                <!-- Slide 1 -->
                <img x-show="currentSlide === 1" x-transition.opacity.duration.1000ms src="{{ $store->cover_path ? $store->cover_url : 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&q=80&w=1920' }}" alt="Hero Image 1" class="absolute inset-0 w-full h-full object-cover">
                <!-- Slide 2 -->
                <img x-show="currentSlide === 2" x-transition.opacity.duration.1000ms src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&q=80&w=1920" alt="Hero Image 2" class="absolute inset-0 w-full h-full object-cover" style="display: none;">
                <!-- Slide 3 -->
                <img x-show="currentSlide === 3" x-transition.opacity.duration.1000ms src="https://images.unsplash.com/photo-1583847268964-b28e50b58b44?auto=format&fit=crop&q=80&w=1920" alt="Hero Image 3" class="absolute inset-0 w-full h-full object-cover" style="display: none;">
                
                <!-- Subtle Dark Overlay for Text Readability -->
                <div class="absolute inset-0 bg-black/30 md:bg-black/20"></div>
            </div>

            <!-- Content Container -->
            <div class="relative z-10 w-full h-full max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 flex flex-col justify-center">
                <div class="max-w-2xl text-white md:ml-10" data-animate>
                    <h1 class="text-5xl md:text-7xl font-script tracking-wide mb-2 text-white/90 drop-shadow-md">
                        {{ $store->name }}
                    </h1>
                    <h2 class="text-3xl md:text-5xl font-medium mb-12 drop-shadow-md leading-tight">
                        {{ $store->hero_title ?? 'Your Perfect Home Awaits' }}
                    </h2>

                    <!-- Circular "Shop Now" Button -->
                    <div class="relative inline-flex items-center justify-center w-32 h-32 md:w-40 md:h-40 group cursor-pointer" onclick="window.location='{{ route('store.catalog', $store->slug) }}'">
                        <!-- Outer Thin Ring -->
                        <div class="absolute inset-0 rounded-full border border-white/50 group-hover:scale-110 transition-transform duration-500"></div>
                        <!-- Inner Solid Circle -->
                        <div class="absolute inset-2 md:inset-3 rounded-full bg-[#1A1A1A] flex items-center justify-center shadow-2xl group-hover:bg-[#C8A68B] transition-colors duration-500">
                            <span class="text-white text-xs md:text-sm tracking-widest uppercase font-semibold">{{ \App\Helpers\TranslationHelper::trans('shop_now', 'Shop Now') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Left Carousel Indicators -->
            <div class="absolute bottom-8 left-4 sm:left-6 lg:left-12 md:ml-10 z-20 flex items-center gap-4 text-white/80 text-sm font-medium">
                <span x-text="String(currentSlide).padStart(2, '0')">01</span>
                <div class="flex items-center gap-2">
                    <button @click="currentSlide = currentSlide > 1 ? currentSlide - 1 : totalSlides" class="hover:text-white transition-colors focus:outline-none">
                        <svg class="w-8 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 16l-4-4m0 0l4-4m-4 4h18"></path></svg>
                    </button>
                    <div class="w-8 h-[1px] bg-white/30"></div>
                    <button @click="currentSlide = currentSlide < totalSlides ? currentSlide + 1 : 1" class="hover:text-white transition-colors focus:outline-none">
                        <svg class="w-8 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </button>
                </div>
                <span x-text="String(totalSlides).padStart(2, '0')">03</span>
            </div>
            
            <!-- Bottom Right Tagline (Optional) -->
            @if($store->hero_subtitle)
            <div class="absolute bottom-8 right-4 sm:right-6 lg:right-12 hidden md:block z-20 text-white/80 text-sm font-medium max-w-xs text-right">
                {{ $store->hero_subtitle }}
            </div>
            @endif
        </section>
        <p class="text-center text-xs text-gray-400 py-2">*Válido para pedidos realizados hasta las 5:00 p.m. Aplica T&C.</p>

        <!-- Highlights/Categories Row (Harmonized with brand palette) -->
        <section class="py-10 bg-[#FDF8EF] border-b border-stone-200/60">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap justify-center gap-4 md:gap-8">
                    @php
                        $highlights = [
                            ['title' => \App\Helpers\TranslationHelper::trans('new', 'Nuevo'), 'bg' => '#C8A68B', 'text' => 'white', 'icon' => 'New', 'query' => 'sort=newest'],
                            ['title' => \App\Helpers\TranslationHelper::trans('outlet', 'Outlet'), 'bg' => '#EADBC8', 'text' => '#1A1A1A', 'icon' => '🏷️', 'query' => 'sale=1'],
                            ['title' => \App\Helpers\TranslationHelper::trans('essentials', 'Esenciales'), 'bg' => '#F4EFE6', 'text' => '#1A1A1A', 'icon' => '✨', 'query' => 'q=esenciales'],
                            ['title' => \App\Helpers\TranslationHelper::trans('back_in_stock', 'De regreso'), 'bg' => '#E5DFD5', 'text' => '#1A1A1A', 'icon' => '🔄', 'query' => 'q=destacado'],
                            ['title' => \App\Helpers\TranslationHelper::trans('deco', 'Deco'), 'bg' => '#DFD3C3', 'text' => '#1A1A1A', 'icon' => '🪴', 'query' => 'category=deco'],
                            ['title' => \App\Helpers\TranslationHelper::trans('wholesale', 'Por mayor'), 'bg' => '#D0C4B4', 'text' => '#1A1A1A', 'icon' => '📦', 'query' => 'q=mayor'],
                            ['title' => 'De S/5', 'bg' => '#2C2B2A', 'text' => 'white', 'icon' => 'S/ 5', 'query' => 'max_price=5'],
                            ['title' => 'De S/10', 'bg' => '#1A1A1A', 'text' => 'white', 'icon' => 'S/ 10', 'query' => 'max_price=10'],
                        ];
                    @endphp
                    @foreach($highlights as $index => $h)
                        <a href="{{ route('store.catalog', $store->slug) }}?{{ $h['query'] }}" 
                           class="flex flex-col items-center gap-2 group transition-transform duration-300 hover:-translate-y-1" 
                           data-animate style="animation-delay: {{ $index * 50 }}ms;">
                            <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl flex items-center justify-center text-base md:text-lg font-bold shadow-sm group-hover:shadow-md transition-all border border-stone-200/50 group-hover:border-[#C8A68B]" 
                                 style="background-color: {{ $h['bg'] }}; color: {{ $h['text'] === 'white' ? '#fff' : '#1A1A1A' }}">
                                 {{ $h['icon'] }}
                            </div>
                            <span class="text-xs md:text-sm font-medium text-gray-700 group-hover:text-[#C8A68B] transition-colors">{{ $h['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Featured Categories -->
        @php
            $featuredCats = $categories->where('is_featured', true);
            if ($featuredCats->isEmpty()) {
                $featuredCats = $categories->take(6);
            }
        @endphp
        @if($featuredCats->isNotEmpty())
        <section class="py-12 bg-[#FDF8EF] border-t border-stone-200/40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-xl font-bold text-[#1A1A1A] mb-6">{{ \App\Helpers\TranslationHelper::trans('featured_categories', 'Categorías destacadas') }}</h2>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    @foreach($featuredCats as $index => $cat)
                        @php
                            $catBg = $cat->color ?: '#F5EDE2';
                            $imgUrl = $cat->image_url;
                        @endphp
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" 
                           class="relative overflow-hidden rounded-2xl h-56 flex flex-col justify-end cursor-pointer group shadow-sm hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 block border border-black/5" 
                           style="background-color: {{ $catBg }}; animation-delay: {{ $index * 100 }}ms;" 
                           data-animate>
                            
                            <!-- Image -->
                            @if($imgUrl)
                                <img src="{{ $imgUrl }}" alt="{{ $cat->getTranslatedName() }}" 
                                     class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-out">
                            @else
                                <!-- Pastel Gradient & Ambient Icon fallback -->
                                <div class="absolute inset-0 bg-gradient-to-br from-white/30 via-transparent to-black/10"></div>
                                <div class="absolute inset-0 flex items-center justify-center opacity-15">
                                    <span class="text-7xl select-none">{{ $cat->icon ?? '⭐' }}</span>
                                </div>
                            @endif

                            <!-- Bottom Gradient Overlay for readability -->
                            <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black/75 via-black/30 to-transparent z-0 pointer-events-none"></div>

                            <!-- Bottom Capsule Pill (Exact matching layout from screenshot) -->
                            <div class="absolute bottom-0 left-0 right-0 flex items-end z-10 p-0">
                                <div class="bg-black/50 backdrop-blur-md rounded-tr-2xl pr-3.5 pl-2 py-1.5 flex items-center gap-2 border-t border-r border-white/20 shadow-md max-w-[92%]">
                                    <span class="w-7 h-7 rounded-full bg-white/20 backdrop-blur-xs flex items-center justify-center text-sm shrink-0 shadow-inner">
                                        {{ $cat->icon ?? '⭐' }}
                                    </span>
                                    <span class="text-white text-xs font-bold tracking-tight truncate drop-shadow-sm">
                                        {{ $cat->getTranslatedName() }}
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        <!-- Products Section (Bento Gallery) -->
        <section class="py-16 bg-[#FDF8EF]">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12">
                <div class="flex items-center mb-10" data-animate>
                    <h2 class="text-3xl md:text-4xl text-[#1A1A1A] italic tracking-wide">{{ \App\Helpers\TranslationHelper::trans('best_selling', 'Best Selling') }}</h2>
                </div>
                
                @if($allProducts->isEmpty())
                <p class="text-gray-400 text-center py-12">Próximamente productos disponibles...</p>
                @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 auto-rows-[auto]">
                    @foreach($allProducts as $product)
                    @php
                        // First item is large (bento style)
                        $isLarge = $loop->first || $loop->iteration == 6;
                    @endphp
                    <div class="{{ $isLarge ? 'md:col-span-2 md:row-span-2' : 'col-span-1 row-span-1' }} group flex flex-col relative" data-animate style="animation-delay: {{ $loop->index * 100 }}ms;">
                        
                        <a href="{{ route('store.product', [$store->slug, $product->slug]) }}" class="block relative w-full {{ $isLarge ? 'aspect-[4/3] md:aspect-square' : 'aspect-square' }} overflow-hidden rounded-md bg-[#F4F2EE] mb-4">
                            @if($product->image_path)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                     class="w-full h-full object-cover mix-blend-multiply group-hover:scale-105 transition-transform duration-700 ease-in-out">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-4xl text-gray-300">📦</div>
                            @endif

                            @if($product->resolveComparePrice())
                            <div class="absolute top-3 left-3">
                                <span class="bg-red-600 text-white text-[10px] font-bold px-2 py-1 rounded-full shadow-sm tracking-widest">
                                    -{{ round((($product->resolveComparePrice() - $product->resolvePrice()) / $product->resolveComparePrice()) * 100) }}% SALE
                                </span>
                            </div>
                            @endif

                            <!-- Quick add overlay on hover (Desktop) -->
                            <div class="absolute inset-x-0 bottom-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300 translate-y-4 group-hover:translate-y-0 hidden md:block">
                                <button onclick="event.preventDefault(); window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->resolvePrice() }}, '{{ $product->image_path ? $product->image_url : '' }}')" 
                                        class="w-full py-3 bg-[#1A1A1A]/90 backdrop-blur-sm text-white rounded-md text-sm font-semibold hover:bg-[#C8A68B] transition-colors flex items-center justify-center gap-2 shadow-lg">
                                    Add to cart
                                </button>
                            </div>
                        </a>
                        
                        <div class="flex flex-col flex-grow px-1">
                            <a href="{{ route('store.product', [$store->slug, $product->slug]) }}" class="text-[#1A1A1A] font-medium text-sm leading-snug line-clamp-2 hover:text-[#C8A68B] transition-colors mb-1">
                                {{ $product->name }}
                            </a>
                            
                            <div class="mt-auto flex items-center gap-2">
                                <span class="font-bold text-sm text-red-600">{{ $product->resolveCurrencySymbol() }} {{ number_format($product->resolvePrice(), 2) }}</span>
                                @if($product->resolveComparePrice())
                                <span class="text-gray-400 text-[11px] line-through">{{ $product->resolveCurrencySymbol() }} {{ number_format($product->resolveComparePrice(), 2) }}</span>
                                @endif
                            </div>

                            <!-- Mobile Quick Add -->
                            <button onclick="window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->resolvePrice() }}, '{{ $product->image_path ? $product->image_url : '' }}')" 
                                    class="mt-3 md:hidden w-full py-2.5 bg-[#1A1A1A] text-white rounded-md text-xs font-semibold hover:bg-[#C8A68B] transition-colors">
                                Add to cart
                            </button>
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
                <div class="px-4" data-animate style="animation-delay: 100ms;">
                    <svg class="w-8 h-8 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                    <h4 class="font-bold text-sm text-gray-900 mb-1">Envíos a nivel nacional</h4>
                    <p class="text-xs text-gray-500">A todo el Perú vía Shalom, Olva o agencia</p>
                </div>
                <div class="px-4" data-animate style="animation-delay: 200ms;">
                    <svg class="w-8 h-8 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    <h4 class="font-bold text-sm text-gray-900 mb-1">Múltiples formas de pago</h4>
                    <p class="text-xs text-gray-500">Tarjetas, transferencias y Yape</p>
                </div>
                <div class="px-4" data-animate style="animation-delay: 300ms;">
                    <svg class="w-8 h-8 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <h4 class="font-bold text-sm text-gray-900 mb-1">Pagos seguros</h4>
                    <p class="text-xs text-gray-500">Transacciones 100% encriptadas</p>
                </div>
                <div class="px-4" data-animate style="animation-delay: 400ms;">
                    <svg class="w-8 h-8 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    <h4 class="font-bold text-sm text-gray-900 mb-1">Asesoría personalizada</h4>
                    <p class="text-xs text-gray-500">Te ayudamos a elegir por WhatsApp</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer -->
    <footer class="bg-white border-t border-stone-200/60 py-8 text-center mt-12">
        <p class="text-xs font-semibold text-gray-500 tracking-wider">
            {{ \App\Helpers\TranslationHelper::isEn() ? 'Powered by' : 'Impulsado por' }} <span class="text-[#1A1A1A] font-bold">Tribio</span>
        </p>
    </footer>

    {{-- Cart Drawer --}}
    @include('templates.minimal-light.cart-drawer')

    {{-- Customer Account & Dashboard Modal --}}
    @include('templates.minimal-light.customer-modal')

    </div>

    <!-- Modal de Selección de País (Se muestra si no hay cookie 'user_country') -->
    @if(!request()->hasCookie('user_country'))
    <div x-data="{
        showModal: true,
        selectCountry(countryCode) {
            document.cookie = 'user_country=' + countryCode + '; path=/; max-age=31536000';
            this.showModal = false;
            window.location.reload();
        }
    }" x-show="showModal" style="display:none;" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.away="showModal = true" class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 text-center animate-fade-in-up">
            <h2 class="text-2xl font-black text-gray-900 mb-2">¡Hola! 👋</h2>
            <p class="text-gray-500 mb-6 text-sm">Antes de continuar, cuéntanos desde qué país nos estás visitando para mostrarte los precios y opciones correctas.</p>
            
            <div class="grid grid-cols-2 gap-4">
                <button @click="selectCountry('PE')" class="flex flex-col items-center gap-3 p-4 rounded-xl border-2 border-gray-100 hover:border-green-500 hover:bg-green-50 transition-all group">
                    <span class="text-4xl group-hover:scale-110 transition-transform">🇵🇪</span>
                    <span class="font-bold text-gray-700 group-hover:text-green-600">Perú</span>
                </button>
                <button @click="selectCountry('US')" class="flex flex-col items-center gap-3 p-4 rounded-xl border-2 border-gray-100 hover:border-green-500 hover:bg-green-50 transition-all group">
                    <span class="text-4xl group-hover:scale-110 transition-transform">🇺🇸</span>
                    <span class="font-bold text-gray-700 group-hover:text-green-600">Estados Unidos</span>
                </button>
            </div>
        </div>
    </div>
    @endif

</body>
</html>
