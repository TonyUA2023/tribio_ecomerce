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
    <!-- Fonts: Fredoka (Maetek Brand Font), Quicksand & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
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
            --font-brand: 'Fredoka', 'Quicksand', sans-serif;
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
        .font-brand { font-family: var(--font-brand) !important; }
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

    <main class="flex-grow bg-[#FAF7F2]">
        <!-- Full-Width Professional Hero Banner -->
        <section class="relative w-full h-[60vh] md:h-[80vh] min-h-[500px] overflow-hidden bg-[#FAF7F2]" x-data="{ currentSlide: 1, totalSlides: 3 }">
            <!-- Background Images (Curated Aesthetic Carousel) -->
            <div class="absolute inset-0 w-full h-full">
                <!-- Slide 1 (Bright, clean Scandinavian living room without sofa, natural wood & modern art) -->
                <img x-show="currentSlide === 1" x-transition.opacity.duration.1000ms 
                     src="{{ $store->cover_path ? $store->cover_url : asset('images/hero_living_room_clean.jpg') }}" 
                     alt="{{ $store->name }} Slide 1" 
                     class="absolute inset-0 w-full h-full object-cover">
                <!-- Slide 2 (Bright lifestyle kitchen & home organization with warm neutral tones) -->
                <img x-show="currentSlide === 2" x-transition.opacity.duration.1000ms 
                     src="https://images.unsplash.com/photo-1556911220-e15b29be8c8f?auto=format&fit=crop&q=85&w=1920" 
                     alt="{{ $store->name }} Slide 2" 
                     class="absolute inset-0 w-full h-full object-cover" style="display: none;">
                <!-- Slide 3 (Cozy minimalist aesthetic home with sunlight & warm beige accents) -->
                <img x-show="currentSlide === 3" x-transition.opacity.duration.1000ms 
                     src="https://images.unsplash.com/photo-1616046229478-9901c5536a45?auto=format&fit=crop&q=85&w=1920" 
                     alt="{{ $store->name }} Slide 3" 
                     class="absolute inset-0 w-full h-full object-cover" style="display: none;">
                
                <!-- Warm Gradient Overlay for Luminous Readability -->
                <div class="absolute inset-0 bg-gradient-to-r from-[#1E1D1B]/75 via-[#23211E]/45 to-transparent"></div>
            </div>

            <!-- Content Container -->
            <div class="relative z-10 w-full h-full max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 flex flex-col justify-center">
                <div class="max-w-2xl text-white md:ml-10" data-animate>
                    <!-- Brand Slogan Tag from Logo -->
                    <div class="inline-flex items-center gap-2 mb-3.5 px-4 py-1.5 rounded-full bg-white/20 backdrop-blur-md border border-white/30 text-white text-[11px] md:text-xs font-bold tracking-[0.25em] uppercase shadow-xs font-brand">
                        <span class="w-2 h-2 rounded-full bg-[#7DA268]"></span>
                        <span>— TU VIDA, MÁS FÁCIL —</span>
                    </div>

                    <!-- Main Brand Name with Maetek Typography -->
                    <h1 class="text-5xl sm:text-7xl md:text-8xl font-bold tracking-tight mb-3 text-white drop-shadow-md font-brand leading-none">
                        {{ $store->name }}
                    </h1>

                    <!-- Hero Title with Maetek Typography -->
                    <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-semibold mb-10 drop-shadow-sm leading-snug text-white/95 max-w-xl font-brand">
                        {{ $store->hero_title ?: 'Innovación que inspira tu estilo de vida' }}
                    </h2>

                    <!-- Circular "Comprar Ahora" Button with Pastel Green Accent -->
                    <div class="relative inline-flex items-center justify-center w-32 h-32 md:w-36 md:h-36 group cursor-pointer" onclick="window.location='{{ route('store.catalog', $store->slug) }}'">
                        <!-- Outer Thin Ring -->
                        <div class="absolute inset-0 rounded-full border-2 border-white/60 group-hover:scale-110 group-hover:border-[#CCDBC0] transition-all duration-500"></div>
                        <!-- Inner Solid Circle (Centered Text & Arrow) -->
                        <div class="absolute inset-2 md:inset-2.5 rounded-full bg-[#7DA268] flex flex-col items-center justify-center text-center px-3 shadow-xl group-hover:bg-[#6D9159] transition-all duration-500">
                            <span class="text-white text-xs md:text-sm tracking-wider uppercase font-bold font-brand text-center block w-full leading-tight">
                                {{ \App\Helpers\TranslationHelper::trans('shop_now', 'Comprar Ahora') }}
                            </span>
                            <span class="text-white/90 text-xs sm:text-sm mt-1.5 group-hover:translate-x-1 transition-transform inline-block">→</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Left Carousel Indicators -->
            <div class="absolute bottom-8 left-4 sm:left-6 lg:left-12 md:ml-10 z-20 flex items-center gap-4 text-white/80 text-sm font-medium font-brand">
                <span x-text="String(currentSlide).padStart(2, '0')">01</span>
                <div class="flex items-center gap-2">
                    <button @click="currentSlide = currentSlide > 1 ? currentSlide - 1 : totalSlides" class="hover:text-white transition-colors focus:outline-none cursor-pointer">
                        <svg class="w-8 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M7 16l-4-4m0 0l4-4m-4 4h18"></path></svg>
                    </button>
                    <div class="w-8 h-[1px] bg-white/30"></div>
                    <button @click="currentSlide = currentSlide < totalSlides ? currentSlide + 1 : 1" class="hover:text-white transition-colors focus:outline-none cursor-pointer">
                        <svg class="w-8 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </button>
                </div>
                <span x-text="String(totalSlides).padStart(2, '0')">03</span>
            </div>
            
            <!-- Bottom Right Tagline -->
            @if($store->hero_subtitle)
            <div class="absolute bottom-8 right-4 sm:right-6 lg:right-12 hidden md:block z-20 text-white/90 text-sm font-medium max-w-xs text-right font-brand">
                {{ $store->hero_subtitle }}
            </div>
            @else
            <div class="absolute bottom-8 right-4 sm:right-6 lg:right-12 hidden md:block z-20 text-white/90 text-sm font-medium max-w-xs text-right font-brand">
                ✨ Tu vida, más fácil
            </div>
            @endif
        </section>

        <!-- Maetek 3 Pastel Brand Pillars (Inspired Directly from Official Logo: House, Leaf, Lightbulb) -->
        <section class="py-6 sm:py-9 bg-[#FAF7F2] border-b border-stone-200/70">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    <!-- Pillar 1: Hogar (Pastel Sand) -->
                    <div class="flex items-center gap-4 sm:gap-5 p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-white border border-[#EFE3D0] shadow-2xs hover:shadow-md hover:border-[#DECBB5] transition-all duration-300 group">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-[#EFE3D0] flex items-center justify-center shrink-0 text-2xl sm:text-3xl shadow-inner group-hover:scale-110 transition-transform duration-300">
                            🏠
                        </div>
                        <div>
                            <h4 class="font-bold text-base sm:text-lg text-[#1E1D1B] font-brand tracking-tight mb-0.5">
                                {{ \App\Helpers\TranslationHelper::isEn() ? 'Your Home, Better' : 'Tu Hogar, Mejor' }}
                            </h4>
                            <p class="text-xs sm:text-sm text-[#6E6A63] leading-snug">
                                {{ \App\Helpers\TranslationHelper::isEn() ? 'Essential items for your everyday life' : 'Artículos esenciales para tu día a día' }}
                            </p>
                        </div>
                    </div>

                    <!-- Pillar 2: Natural & Calidad (Pastel Sage Green) -->
                    <div class="flex items-center gap-4 sm:gap-5 p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-white border border-[#CCDBC0] shadow-2xs hover:shadow-md hover:border-[#A2C493] transition-all duration-300 group">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-[#C8D7BA] flex items-center justify-center shrink-0 text-[#3F542F] text-2xl sm:text-3xl shadow-inner group-hover:scale-110 transition-transform duration-300">
                            🍃
                        </div>
                        <div>
                            <h4 class="font-bold text-base sm:text-lg text-[#1E1D1B] font-brand tracking-tight mb-0.5">
                                {{ \App\Helpers\TranslationHelper::isEn() ? 'Essential Quality' : 'Calidad Esencial' }}
                            </h4>
                            <p class="text-xs sm:text-sm text-[#6E6A63] leading-snug">
                                {{ \App\Helpers\TranslationHelper::isEn() ? 'Curated with care and durability' : 'Seleccionados con cariño y durabilidad' }}
                            </p>
                        </div>
                    </div>

                    <!-- Pillar 3: Ideas Prácticas (Pastel Peach) -->
                    <div class="flex items-center gap-4 sm:gap-5 p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-white border border-[#F9D6CE] shadow-2xs hover:shadow-md hover:border-[#E8A598] transition-all duration-300 group">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-[#F9D6CE] flex items-center justify-center shrink-0 text-[#8C3425] text-2xl sm:text-3xl shadow-inner group-hover:scale-110 transition-transform duration-300">
                            💡
                        </div>
                        <div>
                            <h4 class="font-bold text-base sm:text-lg text-[#1E1D1B] font-brand tracking-tight mb-0.5">
                                {{ \App\Helpers\TranslationHelper::isEn() ? 'Smart Ideas' : 'Ideas Prácticas' }}
                            </h4>
                            <p class="text-xs sm:text-sm text-[#6E6A63] leading-snug">
                                {{ \App\Helpers\TranslationHelper::isEn() ? 'Simple solutions that make life easier' : 'Soluciones simples que facilitan tu vida' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <p class="text-center text-xs text-stone-400 py-2.5 bg-[#FAF7F2]">
            {{ \App\Helpers\TranslationHelper::isEn() ? '*Valid for orders placed before 5:00 PM. T&C apply.' : '*Válido para pedidos realizados hasta las 5:00 p.m. Aplica T&C.' }}
        </p>

        @if(isset($homeVideoProducts) && $homeVideoProducts->isNotEmpty())
        <!-- Featured Products with Short Videos Section (Pastel & Minimalist) -->
        <section class="py-10 md:py-14 bg-[#F5F8F2] border-b border-stone-200/70 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-6 md:mb-8 gap-3">
                    <div>
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-[#1E1D1B] font-brand tracking-tight">
                            {{ \App\Helpers\TranslationHelper::isEn() ? 'Trending Products' : 'Productos en Tendencia' }}
                        </h2>
                        <p class="text-xs sm:text-sm text-stone-500 mt-1 font-medium max-w-xl">
                            Detalles y acabados reales de nuestros productos destacados.
                        </p>
                    </div>

                    <a href="{{ route('store.catalog', $store->slug) }}" 
                       class="inline-flex items-center gap-1 text-xs sm:text-sm font-semibold text-stone-700 hover:text-[#C8A68B] transition-colors font-brand group self-start sm:self-auto">
                        <span>Ver todo el catálogo</span>
                        <span class="group-hover:translate-x-0.5 transition-transform">→</span>
                    </a>
                </div>

                <!-- 3 Videos in a Single Row Grid (Minimalist Cards for All Screen Sizes) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
                    @foreach($homeVideoProducts->take(3) as $vProduct)
                    <a href="{{ route('store.product', [$store->slug, $vProduct->slug]) }}" 
                       class="group block bg-white rounded-2xl md:rounded-3xl p-2.5 sm:p-3 border border-stone-200/80 shadow-2xs hover:shadow-md hover:border-[#CCDBC0] transition-all duration-300">
                        
                        <!-- Video Container (Compact aspect-[4/5] format) -->
                        <div class="relative aspect-[4/5] w-full rounded-xl md:rounded-2xl overflow-hidden bg-stone-950"
                             x-data="{
                                 isMuted: true,
                                 playVideo() {
                                     const vid = this.$refs.videoPlayer;
                                     if (vid) {
                                         vid.muted = true;
                                         vid.defaultMuted = true;
                                         const p = vid.play();
                                         if (p !== undefined) {
                                             p.catch(() => {});
                                         }
                                     }
                                 },
                                 toggleMute(e) {
                                     e.preventDefault();
                                     e.stopPropagation();
                                     this.isMuted = !this.isMuted;
                                     if (this.$refs.videoPlayer) {
                                         this.$refs.videoPlayer.muted = this.isMuted;
                                     }
                                 }
                             }"
                             x-init="
                                 $nextTick(() => { playVideo(); });
                                 if ('IntersectionObserver' in window) {
                                     const observer = new IntersectionObserver((entries) => {
                                         entries.forEach(entry => {
                                             if (entry.isIntersecting) {
                                                 playVideo();
                                             }
                                         });
                                     }, { threshold: 0.15 });
                                     observer.observe($el);
                                 }
                             ">
                            
                            <video x-ref="videoPlayer"
                                   src="{{ $vProduct->video_url }}"
                                   class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                   autoplay
                                   loop
                                   muted
                                   playsinline
                                   webkit-playsinline
                                   preload="auto">
                            </video>

                            @if($vProduct->resolveComparePrice() > $vProduct->resolvePrice())
                                <div class="absolute top-2.5 left-2.5 bg-[#F9D6CE] text-[#8C3425] border border-[#F2BCB0] text-[9px] sm:text-[10px] font-bold px-2 py-0.5 rounded-md uppercase tracking-wider shadow-xs font-brand">
                                    Oferta
                                </div>
                            @endif

                            <!-- Subtle Sound Toggle (Does not navigate) -->
                            <button type="button" 
                                    @click="toggleMute($event)"
                                    class="absolute bottom-2.5 right-2.5 z-20 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-black/60 hover:bg-black text-white flex items-center justify-center text-[11px] sm:text-xs transition shadow-xs cursor-pointer backdrop-blur-xs"
                                    :title="isMuted ? 'Activar sonido' : 'Silenciar'">
                                <span x-show="isMuted">🔇</span>
                                <span x-show="!isMuted" style="display: none;">🔊</span>
                            </button>
                        </div>

                        <!-- Minimalist Single Row Info: Name + Price + Action -->
                        <div class="pt-2.5 pb-1 px-1 flex items-center justify-between gap-2.5">
                            <!-- Product Name -->
                            <h3 class="font-bold text-[#1E1D1B] text-xs sm:text-sm truncate flex-1 group-hover:text-[#C8A68B] transition-colors font-brand" title="{{ $vProduct->name }}">
                                {{ $vProduct->name }}
                            </h3>

                            <!-- Price and Action in the same single row -->
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <div class="text-right flex items-baseline gap-1.5">
                                    <span class="text-xs sm:text-sm font-bold text-[#1E1D1B] font-brand">
                                        {{ \App\Helpers\CurrencyHelper::format($vProduct->resolvePrice()) }}
                                    </span>
                                    @if($vProduct->resolveComparePrice() > $vProduct->resolvePrice())
                                        <span class="text-[10px] text-stone-400 line-through hidden xs:inline font-brand">
                                            {{ \App\Helpers\CurrencyHelper::format($vProduct->resolveComparePrice()) }}
                                        </span>
                                    @endif
                                </div>
                                
                                <span class="inline-flex items-center text-[11px] sm:text-xs font-bold text-[#C8A68B] group-hover:text-[#1E1D1B] transition-colors font-brand">
                                    <span>Ver</span>
                                    <span class="ml-0.5 group-hover:translate-x-0.5 transition-transform">→</span>
                                </span>
                            </div>
                        </div>

                    </a>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        <!-- Highlights/Categories Row (Pastel Color Palette Inspired by Logo) -->
        <section class="py-10 bg-[#FAF7F2] border-b border-stone-200/70">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap justify-center gap-4 md:gap-7">
                    @php
                        $isUsd = \App\Helpers\CurrencyHelper::isUsd();
                        $highlights = [
                            ['title' => \App\Helpers\TranslationHelper::trans('new', 'Nuevo'), 'bg' => '#E8D5C4', 'text' => '#4A3525', 'icon' => '✨', 'query' => 'sort=newest'],
                            ['title' => \App\Helpers\TranslationHelper::trans('outlet', 'Outlet'), 'bg' => '#FBDDD6', 'text' => '#5A2C22', 'icon' => '🏷️', 'query' => 'sale=1'],
                            ['title' => \App\Helpers\TranslationHelper::trans('essentials', 'Esenciales'), 'bg' => '#C8D7BA', 'text' => '#2A3A1E', 'icon' => '🍃', 'query' => 'q=esenciales'],
                            ['title' => \App\Helpers\TranslationHelper::trans('back_in_stock', 'De regreso'), 'bg' => '#FAF0E4', 'text' => '#4A4335', 'icon' => '🔄', 'query' => 'q=destacado'],
                            ['title' => \App\Helpers\TranslationHelper::trans('deco', 'Deco'), 'bg' => '#E8E7E1', 'text' => '#363532', 'icon' => '🪴', 'query' => 'category=deco'],
                            ['title' => \App\Helpers\TranslationHelper::trans('wholesale', 'Por mayor'), 'bg' => '#D8E5D0', 'text' => '#23391C', 'icon' => '📦', 'query' => 'q=mayor'],
                            $isUsd ? ['title' => '$2 o menos', 'bg' => '#EFE3D0', 'text' => '#4A3525', 'icon' => '$ 2', 'query' => 'max_price=2'] : ['title' => 'De S/5', 'bg' => '#EFE3D0', 'text' => '#4A3525', 'icon' => 'S/ 5', 'query' => 'max_price=5'],
                            $isUsd ? ['title' => '$5 o menos', 'bg' => '#D6E8E2', 'text' => '#1E3A33', 'icon' => '$ 5', 'query' => 'max_price=5'] : ['title' => 'De S/10', 'bg' => '#D6E8E2', 'text' => '#1E3A33', 'icon' => 'S/ 10', 'query' => 'max_price=10'],
                        ];
                    @endphp
                    @foreach($highlights as $index => $h)
                        <a href="{{ route('store.catalog', $store->slug) }}?{{ $h['query'] }}" 
                           class="flex flex-col items-center gap-2 group transition-transform duration-300 hover:-translate-y-1" 
                           data-animate style="animation-delay: {{ $index * 50 }}ms;">
                            <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl flex items-center justify-center text-base md:text-lg font-bold shadow-2xs group-hover:shadow-md transition-all border border-stone-200/80 group-hover:border-[#C8A68B] group-hover:scale-105" 
                                 style="background-color: {{ $h['bg'] }}; color: {{ $h['text'] }}">
                                 {{ $h['icon'] }}
                            </div>
                            <span class="text-xs md:text-sm font-semibold text-stone-700 group-hover:text-[#C8A68B] transition-colors font-brand">{{ $h['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Featured Categories (Pastel Modern) -->
        @php
            $featuredCats = $categories->where('is_featured', true);
            if ($featuredCats->isEmpty()) {
                $featuredCats = $categories->take(6);
            }
        @endphp
        @if($featuredCats->isNotEmpty())
        <section class="py-12 bg-[#FAF7F2] border-t border-stone-200/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-xl md:text-2xl font-bold text-[#1E1D1B] mb-6 font-brand">{{ \App\Helpers\TranslationHelper::trans('featured_categories', 'Categorías destacadas') }}</h2>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    @foreach($featuredCats as $index => $cat)
                        @php
                            $catBg = $cat->color ?: '#F5EDE2';
                            $imgUrl = $cat->image_url;
                        @endphp
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" 
                           class="relative overflow-hidden rounded-2xl h-56 flex flex-col justify-end cursor-pointer group shadow-2xs hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 block border border-stone-200/60" 
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
                            <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black/60 via-black/20 to-transparent z-0 pointer-events-none"></div>

                            <!-- Bottom Capsule Pill (Pastel Frosted Glass) -->
                            <div class="absolute bottom-0 left-0 right-0 flex items-end z-10 p-0">
                                <div class="bg-white/90 backdrop-blur-md rounded-tr-2xl pr-3.5 pl-2 py-1.5 flex items-center gap-2 border-t border-r border-stone-200/70 shadow-xs max-w-[92%]">
                                    <span class="w-7 h-7 rounded-full bg-stone-100 flex items-center justify-center text-sm shrink-0 shadow-2xs">
                                        {{ $cat->icon ?? '⭐' }}
                                    </span>
                                    <span class="text-[#1E1D1B] text-xs font-bold tracking-tight truncate font-brand">
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

        <!-- Products Section (Bento Gallery Pastel Minimalist) -->
        <section class="py-16 bg-[#FAF7F2]">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12">
                <div class="flex items-center mb-10" data-animate>
                    <h2 class="text-3xl md:text-4xl text-[#1E1D1B] font-bold tracking-tight font-brand">{{ \App\Helpers\TranslationHelper::trans('best_selling', 'Best Selling') }}</h2>
                </div>
                
                @if($allProducts->isEmpty())
                <p class="text-stone-400 text-center py-12 font-brand">Próximamente productos disponibles...</p>
                @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 auto-rows-[auto]">
                    @foreach($allProducts as $product)
                    @php
                        // First item is large (bento style)
                        $isLarge = $loop->first || $loop->iteration == 6;
                    @endphp
                    <div class="{{ $isLarge ? 'md:col-span-2 md:row-span-2' : 'col-span-1 row-span-1' }} group flex flex-col relative" data-animate style="animation-delay: {{ $loop->index * 100 }}ms;">
                        
                        <a href="{{ route('store.product', [$store->slug, $product->slug]) }}" class="block relative w-full {{ $isLarge ? 'aspect-[4/3] md:aspect-square' : 'aspect-square' }} overflow-hidden rounded-2xl bg-white border border-stone-200/80 mb-4 shadow-2xs group-hover:shadow-md transition-shadow">
                            @if($product->image_path)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-in-out">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-4xl text-stone-300">📦</div>
                            @endif

                            @if($product->resolveComparePrice() > $product->resolvePrice())
                            <div class="absolute top-3 left-3">
                                <span class="bg-[#F9D6CE] text-[#8C3425] border border-[#F2BCB0] text-[10px] font-bold px-2.5 py-1 rounded-full shadow-2xs tracking-wider font-brand">
                                    -{{ round((($product->resolveComparePrice() - $product->resolvePrice()) / $product->resolveComparePrice()) * 100) }}% SALE
                                </span>
                            </div>
                            @endif

                            <!-- Quick add overlay on hover (Desktop) -->
                            <div class="absolute inset-x-0 bottom-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300 translate-y-4 group-hover:translate-y-0 hidden md:block">
                                <button onclick="event.preventDefault(); window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->resolvePrice() }}, '{{ $product->image_path ? $product->image_url : '' }}')" 
                                        class="w-full py-3 bg-[#1E1D1B] hover:bg-[#C8A68B] text-white rounded-xl text-sm font-semibold transition-colors flex items-center justify-center gap-2 shadow-md cursor-pointer font-brand">
                                    Add to cart
                                </button>
                            </div>
                        </a>
                        
                        <div class="flex flex-col flex-grow px-1">
                            <a href="{{ route('store.product', [$store->slug, $product->slug]) }}" class="text-[#1E1D1B] font-bold text-sm leading-snug line-clamp-2 hover:text-[#C8A68B] transition-colors mb-1 font-brand">
                                {{ $product->name }}
                            </a>
                            
                            <div class="mt-auto flex items-center gap-2 font-brand">
                                <span class="font-bold text-sm text-[#1E1D1B]">{{ $product->resolveCurrencySymbol() }} {{ number_format($product->resolvePrice(), 2) }}</span>
                                @if($product->resolveComparePrice() > $product->resolvePrice())
                                <span class="text-stone-400 text-[11px] line-through">{{ $product->resolveCurrencySymbol() }} {{ number_format($product->resolveComparePrice(), 2) }}</span>
                                @endif
                            </div>

                            <!-- Mobile Quick Add -->
                            <button onclick="window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->resolvePrice() }}, '{{ $product->image_path ? $product->image_url : '' }}')" 
                                    class="mt-3 md:hidden w-full py-2.5 bg-[#1E1D1B] text-white rounded-xl text-xs font-semibold hover:bg-[#C8A68B] transition-colors font-brand cursor-pointer">
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

    <!-- Footer Features (Maetek Pastel Palette) -->
    <div class="bg-[#FAF7F2] border-y border-stone-200/70 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-center">
                <!-- Feature 1: Pastel Sage Green -->
                <div class="p-4 rounded-2xl bg-white border border-[#CCDBC0] shadow-2xs hover:shadow-sm transition-all" data-animate style="animation-delay: 100ms;">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#EAF1E4] border border-[#CCDBC0] flex items-center justify-center text-[#4A6038]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                    </div>
                    <h4 class="font-bold text-sm text-[#1E1D1B] mb-1 font-brand">Envíos a nivel nacional</h4>
                    <p class="text-xs text-[#6E6A63]">Entregas rápidas y seguras a todo el país</p>
                </div>

                <!-- Feature 2: Pastel Peach -->
                <div class="p-4 rounded-2xl bg-white border border-[#F9D6CE] shadow-2xs hover:shadow-sm transition-all" data-animate style="animation-delay: 200ms;">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#FDF1ED] border border-[#F9D6CE] flex items-center justify-center text-[#964736]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    </div>
                    <h4 class="font-bold text-sm text-[#1E1D1B] mb-1 font-brand">Múltiples formas de pago</h4>
                    <p class="text-xs text-[#6E6A63]">Tarjetas, transferencias y pagos locales</p>
                </div>

                <!-- Feature 3: Pastel Sand -->
                <div class="p-4 rounded-2xl bg-white border border-[#EFE3D0] shadow-2xs hover:shadow-sm transition-all" data-animate style="animation-delay: 300ms;">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#F7F3EB] border border-[#EFE3D0] flex items-center justify-center text-[#7A6245]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h4 class="font-bold text-sm text-[#1E1D1B] mb-1 font-brand">Pagos 100% seguros</h4>
                    <p class="text-xs text-[#6E6A63]">Tus datos protegidos y encriptados</p>
                </div>

                <!-- Feature 4: Pastel Greige -->
                <div class="p-4 rounded-2xl bg-white border border-[#E8E7E1] shadow-2xs hover:shadow-sm transition-all" data-animate style="animation-delay: 400ms;">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-[#F2F2ED] border border-[#E8E7E1] flex items-center justify-center text-[#555450]">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    </div>
                    <h4 class="font-bold text-sm text-[#1E1D1B] mb-1 font-brand">Asesoría personalizada</h4>
                    <p class="text-xs text-[#6E6A63]">Te acompañamos en tu compra por WhatsApp</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Footer -->
    @include('templates.minimal-light.footer')

    {{-- Cart Drawer --}}
    @include('templates.minimal-light.cart-drawer')

    {{-- Customer Account & Dashboard Modal --}}
    @include('templates.minimal-light.customer-modal')

    </div>

    <!-- Modal de Selección de País (Se muestra si no hay cookie 'user_country') -->
    @if(!request()->hasCookie('user_country'))
    @php
        $modalCountries = $store->getEnabledCountriesWithDetails();
        if (empty($modalCountries)) {
            $modalCountries = [
                'PE' => \App\Helpers\CurrencyHelper::getCountryInfo('PE'),
                'US' => \App\Helpers\CurrencyHelper::getCountryInfo('US')
            ];
        }
    @endphp
    <div x-data="{
        showModal: true,
        selectCountry(countryCode, currencyCode) {
            document.cookie = 'user_country=' + countryCode + '; path=/; max-age=31536000; SameSite=Lax';
            document.cookie = 'store_currency=' + currencyCode + '; path=/; max-age=31536000; SameSite=Lax';
            if (window.TribioCart && window.TribioCart.items && window.TribioCart.items.length > 0) {
                window.TribioCart.clear();
            }
            this.showModal = false;
            window.location.reload();
        }
    }" x-show="showModal" style="display:none;" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div @click.away="showModal = true" class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 sm:p-8 text-center animate-fade-in-up">
            <h2 class="text-2xl font-black text-gray-900 mb-2 font-brand">¡Hola! 👋</h2>
            <p class="text-gray-500 mb-6 text-xs sm:text-sm">Selecciona desde qué país nos estás visitando para mostrarte los precios y costos de envío en tu moneda local.</p>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[60vh] overflow-y-auto p-1">
                @foreach($modalCountries as $code => $c)
                    <button type="button" @click="selectCountry('{{ $code }}', '{{ $c['currency'] }}')" 
                            class="flex flex-col items-center justify-center gap-2.5 p-3.5 sm:p-4 rounded-xl border-2 border-stone-100 hover:border-[#C8A68B] hover:bg-[#FDF8EF]/50 transition-all group cursor-pointer shadow-xs hover:shadow-md bg-white">
                        <div class="w-14 h-9 sm:w-16 sm:h-10 rounded-md overflow-hidden border border-stone-200/80 shadow-xs flex items-center justify-center bg-stone-100 group-hover:scale-105 transition-transform duration-200">
                            <img src="{{ $c['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($code) }}" 
                                 alt="{{ $c['name'] }}" 
                                 class="w-full h-full object-cover">
                        </div>
                        <div class="text-center">
                            <span class="font-bold text-xs text-gray-800 group-hover:text-[#C8A68B] block leading-tight">{{ $c['name'] }}</span>
                            <span class="text-[10px] text-gray-500 font-mono mt-0.5 block">{{ $c['currency'] }} ({{ $c['symbol'] }})</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
    @endif

</body>
</html>
