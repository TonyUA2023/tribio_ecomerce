<!DOCTYPE html>
<html lang="{{ \App\Helpers\TranslationHelper::currentLang() }}">
<head>
    <meta charset="UTF-8">
    <script>document.documentElement.classList.add('js-anim');</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $store->name }} - Tienda Online</title>
    @if(isset($store) && $store->logo_path)
        <link rel="icon" type="image/png" href="{{ $store->logo_url }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif
    <!-- Fonts: Fredoka & Plus Jakarta Sans (la fuente de títulos elegida se agrega en _theme) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --text-dark: #1A1A1A;
            --text-light: #666666;
            --font-sans: 'Plus Jakarta Sans', sans-serif;
        }
        body {
            background-color: var(--t-bg);
            font-family: var(--font-sans);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        .font-brand { font-family: var(--font-brand) !important; }

        /* Hide Google Translate Widget */
        .goog-te-banner-frame.skiptranslate, .goog-te-gadget-icon { display: none !important; }
        body { top: 0px !important; }
        #goog-gt-tt, .goog-te-balloon-frame { display: none !important; }
        .goog-text-highlight { background: none !important; box-shadow: none !important; }
    </style>
    @include('templates.soft-market._theme')
</head>
<body class="antialiased relative bg-[var(--t-bg)]">
    @php $tplPreview = $templatePreview ?? false; @endphp
    
    @unless($templatePreview ?? false)
    <!-- Google Translate Script -->
    <div id="google_translate_element" style="display:none;"></div>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({pageLanguage: 'es', includedLanguages: 'en,es', autoDisplay: false}, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    @endunless

    <!-- Unified Header -->
    @include('templates.soft-market.header')

    <main class="flex-grow bg-[var(--t-bg)]">
        <!-- Full-Width Professional Hero Banner -->
        @php
            // El carrusel se arma con las fotos de Galería marcadas como "Hero" (type='hero'),
            // en el orden en que se subieron/reordenaron. Si la tienda todavía no marcó ninguna,
            // cae al mismo look de siempre (Portada + 2 fotos de stock) para no romper nada.
            $heroGalleryItems = $store->galleryItems()->where('type', 'hero')->where('is_active', true)->orderBy('sort_order')->get();
            $heroImageUrls = $heroGalleryItems->isNotEmpty()
                ? $heroGalleryItems->pluck('image_url')->all()
                : [
                    $store->cover_path ? $store->cover_url : asset('images/hero_living_room_clean.jpg'),
                    'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?auto=format&fit=crop&q=85&w=1920',
                    'https://images.unsplash.com/photo-1616046229478-9901c5536a45?auto=format&fit=crop&q=85&w=1920',
                ];
        @endphp
        <section class="relative w-full h-[60vh] md:h-[80vh] min-h-[500px] overflow-hidden bg-[var(--t-bg)]" x-data="{ currentSlide: 1, totalSlides: {{ count($heroImageUrls) }} }">
            <!-- Background Images (Curated Aesthetic Carousel, powered by Galería photos tagged "Hero") -->
            <div class="absolute inset-0 w-full h-full">
                @foreach($heroImageUrls as $index => $heroImageUrl)
                    <img x-show="currentSlide === {{ $index + 1 }}" x-transition.opacity.duration.1000ms
                         src="{{ $heroImageUrl }}"
                         alt="{{ $store->name }} Slide {{ $index + 1 }}"
                         class="absolute inset-0 w-full h-full object-cover" @if($index > 0) style="display: none;" @endif>
                @endforeach

                <!-- Warm Gradient Overlay for Luminous Readability -->
                <div class="absolute inset-0 bg-gradient-to-r from-[#1E1D1B]/75 via-[#23211E]/45 to-transparent"></div>
            </div>

            <!-- Content Container -->
            <div class="relative z-10 w-full h-full max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 flex flex-col justify-center">
                <div class="max-w-2xl text-white md:ml-10" data-animate>
                    <!-- Hero badge -->
                    <div class="inline-flex items-center gap-2 mb-3.5 px-4 py-1.5 rounded-full bg-white/20 backdrop-blur-md border border-white/30 text-white text-[11px] md:text-xs font-bold tracking-[0.25em] uppercase shadow-xs font-brand">
                        <span class="w-2 h-2 rounded-full bg-[var(--t-primary)]"></span>
                        <span>— <span data-tpl-text="hero.badge">{{ $storefrontTheme->text('hero.badge') }}</span> —</span>
                    </div>

                    @if($tplPreview || $storefrontTheme->enabled('hero.show_store_name'))
                    <h1 class="text-5xl sm:text-7xl md:text-8xl font-bold tracking-tight mb-3 text-white drop-shadow-md font-brand leading-none"
                        data-tpl-show="hero.show_store_name" @unless($storefrontTheme->enabled('hero.show_store_name')) hidden @endunless>
                        {{ $store->name }}
                    </h1>
                    @endif

                    <h2 class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-semibold mb-3 drop-shadow-sm leading-snug text-white/95 max-w-xl font-brand" data-tpl-text="hero.title">{{ $storefrontTheme->text('hero.title') }}</h2>

                    @if($tplPreview || $storefrontTheme->text('hero.subtitle') !== '')
                    <p class="text-sm sm:text-base text-white/80 max-w-lg font-brand" data-tpl-text="hero.subtitle" data-tpl-hide-empty @if($storefrontTheme->text('hero.subtitle') === '') hidden @endif>{{ $storefrontTheme->text('hero.subtitle') }}</p>
                    @endif

                    <!-- Circular call-to-action -->
                    <a href="{{ route('store.catalog', $store->slug) }}" class="relative mt-7 md:mt-8 inline-flex items-center justify-center w-36 h-36 md:w-40 md:h-40 group cursor-pointer">
                        <!-- Outer Thin Ring -->
                        <span class="absolute inset-0 rounded-full border-2 border-white/60 group-hover:scale-110 group-hover:border-[var(--t-primary)] transition-all duration-500"></span>
                        <!-- Inner Solid Circle (Centered Text & Arrow) -->
                        <span class="absolute inset-2 md:inset-2.5 rounded-full bg-[var(--t-primary)] text-[var(--t-on-primary)] flex flex-col items-center justify-center text-center px-4 shadow-xl group-hover:bg-[var(--t-primary-dark)] group-hover:text-white transition-all duration-500">
                            <span class="text-sm md:text-base tracking-wider uppercase font-extrabold font-brand text-center block w-full leading-tight" data-tpl-text="hero.cta">{{ $storefrontTheme->text('hero.cta') }}</span>
                            <span class="opacity-90 text-sm sm:text-base mt-1.5 group-hover:translate-x-1 transition-transform inline-block font-bold" aria-hidden="true">→</span>
                        </span>
                    </a>
                </div>
            </div>

            <!-- Bottom Left Carousel Indicators -->
            <div x-show="totalSlides > 1" class="absolute bottom-8 left-4 sm:left-6 lg:left-12 md:ml-10 z-20 flex items-center gap-4 text-white/80 text-sm font-medium font-brand">
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
            

        </section>

        <!-- Active Promos Strip (Free Shipping / Bulk Discount) — auto-generated from the store's real settings -->
        @php
            $heroPromos = $store->activePromoMessages(\App\Helpers\TranslationHelper::isEn());
        @endphp
        @if(count($heroPromos) > 0)
        <section class="bg-[var(--t-primary-100)] border-b border-[var(--t-primary-200)]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-1.5">
                    @foreach($heroPromos as $promo)
                        <span class="inline-flex items-center gap-2 text-sm sm:text-base font-bold text-[var(--t-primary-deep)] font-brand">
                            <span>{{ $promo['icon'] }}</span>{{ $promo['text'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        <!-- Brand pillars: three owner-editable value propositions -->
        @if($tplPreview || $storefrontTheme->enabled('pillars.enabled'))
        <section class="py-6 sm:py-9 bg-[var(--t-bg)] border-b border-stone-200/70" data-tpl-show="pillars.enabled" @unless($storefrontTheme->enabled('pillars.enabled')) hidden @endunless>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    @foreach([0, 1, 2] as $pillar)
                    @php $warm = $pillar === 0; @endphp
                    <div class="flex items-center gap-4 sm:gap-5 p-4 sm:p-5 rounded-2xl sm:rounded-3xl bg-white border {{ $warm ? 'border-[var(--t-secondary-200)] hover:border-[var(--t-secondary-300)]' : 'border-[var(--t-primary-200)] hover:border-[var(--t-primary-300)]' }} shadow-2xs hover:shadow-md transition-all duration-300 group">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl {{ $warm ? 'bg-[var(--t-secondary-200)]' : 'bg-[var(--t-primary-200)] text-[var(--t-primary-dark)]' }} flex items-center justify-center shrink-0 text-2xl sm:text-3xl shadow-inner group-hover:scale-110 transition-transform duration-300" data-tpl-text="pillars.items.{{ $pillar }}.icon">{{ $storefrontTheme->text("pillars.items.{$pillar}.icon") }}</div>
                        <div class="min-w-0">
                            <h4 class="font-bold text-base sm:text-lg text-[#1E1D1B] font-brand tracking-tight mb-0.5" data-tpl-text="pillars.items.{{ $pillar }}.title">{{ $storefrontTheme->text("pillars.items.{$pillar}.title") }}</h4>
                            <p class="text-xs sm:text-sm text-[#6E6A63] leading-snug" data-tpl-text="pillars.items.{{ $pillar }}.text">{{ $storefrontTheme->text("pillars.items.{$pillar}.text") }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>
        @endif
        @if($tplPreview || $storefrontTheme->text('pillars.note') !== '')
        <p class="text-center text-xs text-stone-400 py-2.5 px-4 bg-[var(--t-bg)]" data-tpl-text="pillars.note" data-tpl-hide-empty @if($storefrontTheme->text('pillars.note') === '') hidden @endif>{{ $storefrontTheme->text('pillars.note') }}</p>
        @endif

        @if(isset($homeVideoProducts) && $homeVideoProducts->isNotEmpty())
        <!-- Featured Products with Short Videos Section (Pastel & Minimalist) -->
        <section class="py-10 md:py-14 bg-[var(--t-primary-50)] border-b border-stone-200/70 overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-6 md:mb-8 gap-3">
                    <div>
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-bold text-[#1E1D1B] font-brand tracking-tight" data-tpl-text="sections.trending_title">{{ $storefrontTheme->text('sections.trending_title') }}</h2>
                        <p class="text-xs sm:text-sm text-stone-500 mt-1 font-medium max-w-xl" data-tpl-text="sections.trending_subtitle">{{ $storefrontTheme->text('sections.trending_subtitle') }}</p>
                    </div>

                    <a href="{{ route('store.catalog', $store->slug) }}" 
                       class="inline-flex items-center gap-1 text-xs sm:text-sm font-semibold text-stone-700 hover:text-[var(--t-primary)] transition-colors font-brand group self-start sm:self-auto">
                        <span>{{ \App\Helpers\TranslationHelper::isEn() ? 'View full catalog' : 'Ver todo el catálogo' }}</span>
                        <span class="group-hover:translate-x-0.5 transition-transform">→</span>
                    </a>
                </div>

                <!-- 3 Videos in a Single Row Grid (Minimalist Cards for All Screen Sizes) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 lg:gap-8">
                    @foreach($homeVideoProducts->take(3) as $vProduct)
                    <a href="{{ route('store.product', [$store->slug, $vProduct->slug]) }}" 
                       class="group block bg-white rounded-2xl md:rounded-3xl p-2.5 sm:p-3 border border-stone-200/80 shadow-2xs hover:shadow-md hover:border-[var(--t-primary-200)] transition-all duration-300">
                        
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
                                <div class="absolute top-2.5 left-2.5 bg-[var(--t-primary-200)] text-[var(--t-primary-dark)] border border-[var(--t-primary-200)] text-[9px] sm:text-[10px] font-bold px-2 py-0.5 rounded-md uppercase tracking-wider shadow-xs font-brand">
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
                            <h3 class="font-bold text-[#1E1D1B] text-xs sm:text-sm truncate flex-1 group-hover:text-[var(--t-primary)] transition-colors font-brand" title="{{ $vProduct->name }}">
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
                                
                                <span class="inline-flex items-center text-[11px] sm:text-xs font-bold text-[var(--t-primary)] group-hover:text-[#1E1D1B] transition-colors font-brand">
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
        @if($tplPreview || $storefrontTheme->enabled('sections.quicklinks_enabled'))
        <section class="py-10 bg-[var(--t-bg)] border-b border-stone-200/70" data-tpl-show="sections.quicklinks_enabled" @unless($storefrontTheme->enabled('sections.quicklinks_enabled')) hidden @endunless>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap justify-center gap-4 md:gap-7">
                    @php
                        $isUsd = \App\Helpers\CurrencyHelper::isUsd();
                        $highlights = [
                            ['title' => \App\Helpers\TranslationHelper::isEn() ? 'New in' : 'Novedades', 'bg' => 'var(--t-primary-100)', 'text' => 'var(--t-primary-deep)', 'icon' => '✨', 'query' => 'sort=newest'],
                            $isUsd ? ['title' => '$2 o menos', 'bg' => 'var(--t-secondary-200)', 'text' => 'var(--t-secondary-dark)', 'icon' => '$ 2', 'query' => 'max_price=2'] : ['title' => 'Desde S/5', 'bg' => 'var(--t-secondary-200)', 'text' => 'var(--t-secondary-dark)', 'icon' => 'S/ 5', 'query' => 'max_price=5'],
                            $isUsd ? ['title' => '$5 o menos', 'bg' => 'var(--t-primary-200)', 'text' => 'var(--t-primary-deep)', 'icon' => '$ 5', 'query' => 'max_price=5'] : ['title' => 'Desde S/10', 'bg' => 'var(--t-primary-200)', 'text' => 'var(--t-primary-deep)', 'icon' => 'S/ 10', 'query' => 'max_price=10'],
                        ];
                    @endphp
                    @foreach($highlights as $index => $h)
                        <a href="{{ route('store.catalog', $store->slug) }}?{{ $h['query'] }}" 
                           class="flex flex-col items-center gap-2 group transition-transform duration-300 hover:-translate-y-1" 
                           data-animate style="animation-delay: {{ $index * 50 }}ms;">
                            <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl flex items-center justify-center text-base md:text-lg font-bold shadow-2xs group-hover:shadow-md transition-all border border-stone-200/80 group-hover:border-[var(--t-primary)] group-hover:scale-105" 
                                 style="background-color: {{ $h['bg'] }}; color: {{ $h['text'] }}">
                                 {{ $h['icon'] }}
                            </div>
                            <span class="text-xs md:text-sm font-semibold text-stone-700 group-hover:text-[var(--t-primary)] transition-colors font-brand">{{ $h['title'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        <!-- Featured Categories (Pastel Modern) -->
        @php
            $featuredCats = $categories->where('is_featured', true);
            if ($featuredCats->isEmpty()) {
                $featuredCats = $categories->take(6);
            }
        @endphp
        @if($featuredCats->isNotEmpty())
        <section class="py-12 bg-[var(--t-bg)] border-t border-stone-200/50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl md:text-4xl font-extrabold text-[#1E1D1B] mb-8 text-center font-brand" data-tpl-text="sections.categories_title">{{ $storefrontTheme->text('sections.categories_title') }}</h2>
                
                <div class="flex flex-wrap justify-center gap-4">
                    @foreach($featuredCats as $index => $cat)
                        @php
                            $catBg = $cat->color ?: 'var(--t-secondary-50)';
                            $imgUrl = $cat->image_url;
                        @endphp
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" 
                           class="w-[calc(50%-0.5rem)] sm:w-[calc(33.333%-0.67rem)] md:w-[calc(25%-0.75rem)] lg:w-[calc(16.666%-0.84rem)] relative overflow-hidden rounded-2xl h-56 flex flex-col justify-end cursor-pointer group shadow-2xs hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 block border border-stone-200/60" 
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

                            <!-- Bottom Capsule Pill (Pastel Frosted Glass) Centered -->
                            <div class="absolute bottom-4 left-0 right-0 flex items-center justify-center z-10 p-0">
                                <div class="bg-white/95 backdrop-blur-md rounded-2xl px-4 py-2 flex items-center justify-center gap-2.5 shadow-sm max-w-[90%] mx-auto transition-transform group-hover:scale-105">
                                    <span class="w-8 h-8 rounded-full bg-stone-100 flex items-center justify-center text-base shrink-0 shadow-2xs">
                                        {{ $cat->icon ?? '⭐' }}
                                    </span>
                                    <span class="text-[#1E1D1B] text-sm md:text-base font-extrabold tracking-tight truncate font-brand">
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
        <section class="py-16 bg-[var(--t-bg)]">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12">
                <div class="flex items-center mb-10" data-animate>
                    <h2 class="text-3xl md:text-4xl text-[#1E1D1B] font-bold tracking-tight font-brand" data-tpl-text="sections.products_title">{{ $storefrontTheme->text('sections.products_title') }}</h2>
                </div>
                
                @if($allProducts->isEmpty())
                <p class="text-stone-400 text-center py-12 font-brand">{{ \App\Helpers\TranslationHelper::isEn() ? 'Products coming soon...' : 'Próximamente productos disponibles...' }}</p>
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
                                <span class="bg-[var(--t-primary-200)] text-[var(--t-primary-dark)] border border-[var(--t-primary-200)] text-[10px] font-bold px-2.5 py-1 rounded-full shadow-2xs tracking-wider font-brand">
                                    -{{ round((($product->resolveComparePrice() - $product->resolvePrice()) / $product->resolveComparePrice()) * 100) }}% SALE
                                </span>
                            </div>
                            @endif

                            <!-- Quick add overlay on hover (Desktop) -->
                            <div class="absolute inset-x-0 bottom-0 p-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300 translate-y-4 group-hover:translate-y-0 hidden md:block">
                                <button onclick="event.preventDefault(); window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->resolvePrice() }}, '{{ $product->image_path ? $product->image_url : '' }}', null, this)"
                                        class="w-full py-3 bg-[#1E1D1B] hover:bg-[var(--t-primary)] hover:text-[var(--t-on-primary)] text-white rounded-xl text-sm font-semibold transition-colors flex items-center justify-center gap-2 shadow-md cursor-pointer font-brand">
                                    {{ \App\Helpers\TranslationHelper::isEn() ? 'Add to cart' : 'Agregar al carrito' }}
                                </button>
                            </div>
                        </a>
                        
                        <div class="flex flex-col flex-grow px-1">
                            <a href="{{ route('store.product', [$store->slug, $product->slug]) }}" class="text-[#1E1D1B] font-bold text-sm leading-snug line-clamp-2 hover:text-[var(--t-primary)] transition-colors mb-1 font-brand">
                                {{ $product->name }}
                            </a>
                            
                            <div class="mt-auto flex items-center gap-2 font-brand">
                                <span class="font-bold text-sm text-[#1E1D1B]">{{ $product->resolveCurrencySymbol() }} {{ number_format($product->resolvePrice(), 2) }}</span>
                                @if($product->resolveComparePrice() > $product->resolvePrice())
                                <span class="text-stone-400 text-[11px] line-through">{{ $product->resolveCurrencySymbol() }} {{ number_format($product->resolveComparePrice(), 2) }}</span>
                                @endif
                            </div>

                            <!-- Mobile Quick Add -->
                            <button onclick="window.TribioCart && window.TribioCart.add({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->resolvePrice() }}, '{{ $product->image_path ? $product->image_url : '' }}', null, this)"
                                    class="mt-3 md:hidden w-full py-2.5 bg-[#1E1D1B] text-white rounded-xl text-xs font-semibold hover:bg-[var(--t-primary)] hover:text-[var(--t-on-primary)] transition-colors font-brand cursor-pointer">
                                {{ \App\Helpers\TranslationHelper::isEn() ? 'Add to cart' : 'Agregar al carrito' }}
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </section>
                        
    </main>

    <!-- Benefits: three owner-editable guarantees -->
    @if($tplPreview || $storefrontTheme->enabled('benefits.enabled'))
    <div class="bg-[var(--t-bg)] border-y border-stone-200/70 py-10" data-tpl-show="benefits.enabled" @unless($storefrontTheme->enabled('benefits.enabled')) hidden @endunless>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
                @php
                    $benefitIcons = [
                        'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
                        'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                        'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                    ];
                @endphp
                @foreach([0, 1, 2] as $benefit)
                <div class="p-4 rounded-2xl bg-white border {{ $benefit === 2 ? 'border-[var(--t-secondary-200)]' : 'border-[var(--t-primary-200)]' }} shadow-2xs hover:shadow-sm transition-all" data-animate style="animation-delay: {{ ($benefit + 1) * 100 }}ms;">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full border flex items-center justify-center {{ $benefit === 2 ? 'bg-[var(--t-secondary-50)] border-[var(--t-secondary-200)] text-[var(--t-secondary-dark)]' : 'bg-[var(--t-primary-100)] border-[var(--t-primary-200)] text-[var(--t-primary-dark)]' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $benefitIcons[$benefit] }}"></path></svg>
                    </div>
                    <h4 class="font-bold text-sm text-[#1E1D1B] mb-1 font-brand" data-tpl-text="benefits.items.{{ $benefit }}.title">{{ $storefrontTheme->text("benefits.items.{$benefit}.title") }}</h4>
                    <p class="text-xs text-[#6E6A63]" data-tpl-text="benefits.items.{{ $benefit }}.text">{{ $storefrontTheme->text("benefits.items.{$benefit}.text") }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Main Footer -->
    @include('templates.soft-market.footer')

    {{-- Pasarela de pago estándar (carrito + Tribio Pass) --}}
    @include('components.checkout.gateway')

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
                            class="flex flex-col items-center justify-center gap-2.5 p-3.5 sm:p-4 rounded-xl border-2 border-stone-100 hover:border-[var(--t-primary)] hover:bg-[var(--t-bg)]/50 transition-all group cursor-pointer shadow-xs hover:shadow-md bg-white">
                        <div class="w-14 h-9 sm:w-16 sm:h-10 rounded-md overflow-hidden border border-stone-200/80 shadow-xs flex items-center justify-center bg-stone-100 group-hover:scale-105 transition-transform duration-200">
                            <img src="{{ $c['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($code) }}" 
                                 alt="{{ $c['name'] }}" 
                                 class="w-full h-full object-cover">
                        </div>
                        <div class="text-center">
                            <span class="font-bold text-xs text-gray-800 group-hover:text-[var(--t-primary)] block leading-tight">{{ $c['name'] }}</span>
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
