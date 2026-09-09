<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} - Tienda Online</title>
    <!-- Fonts: Playfair Display (Serif) & Plus Jakarta Sans (Sans-serif) -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --accent: {{ $store->accent_color ?? '#1A1A1A' }};
            --secondary: {{ $store->secondary_color ?? '#C8A68B' }};
            --bg: {{ $store->bg_color ?? '#FDF8EF' }};
            --text-dark: #1A1A1A;
            --text-light: #666666;
            --font-serif: 'Playfair Display', serif;
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
        .font-serif { font-family: var(--font-serif) !important; }
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

    <!-- Header -->
    <header class="bg-[#FDF8EF] sticky top-0 z-50 border-b border-gray-200/50 shadow-sm" x-data="{ mobileMenuOpen: false, searchOpen: false }">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 py-4 md:py-5">
            <!-- Top Row -->
            <div class="flex justify-between items-center">
                
                <!-- Left: Settings (Language/Currency) -->
                <div class="flex-1 flex items-center">
                    @if($store->is_multilanguage_enabled)
                    <div class="relative" x-data="{ 
                            langOpen: false, 
                            currentLang: document.cookie.includes('googtrans=/es/en') ? 'EN' : 'ES',
                            changeLanguage(lang) {
                                if(lang === 'EN') {
                                    document.cookie = 'googtrans=/es/en; path=/';
                                    document.cookie = 'googtrans=/es/en; domain=' + window.location.hostname + '; path=/';
                                } else {
                                    document.cookie = 'googtrans=/es/es; path=/';
                                    document.cookie = 'googtrans=/es/es; domain=' + window.location.hostname + '; path=/';
                                    document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                                    document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; domain=' + window.location.hostname + '; path=/;';
                                }
                                window.location.reload();
                            }
                        }">
                        <button @click="langOpen = !langOpen" @click.away="langOpen = false" class="flex items-center gap-1 text-[#1A1A1A] hover:text-[#C8A68B] text-xs font-semibold tracking-wider transition">
                            <span x-text="currentLang"></span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="langOpen" style="display: none;" class="absolute left-0 mt-2 w-24 bg-white rounded-lg shadow-xl border border-gray-100 py-1 z-50">
                            <button @click="changeLanguage('ES')" class="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-gray-50" :class="currentLang === 'ES' ? 'text-[#C8A68B]' : 'text-gray-700'">Español</button>
                            <button @click="changeLanguage('EN')" class="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-gray-50" :class="currentLang === 'EN' ? 'text-[#C8A68B]' : 'text-gray-700'">English</button>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Center: Logo -->
                <div class="flex-shrink-0 flex items-center justify-center">
                    <a href="{{ route('store.show', $store->slug) }}" class="pointer-events-auto">
                        @if($store->logo_path)
                            <img class="h-10 md:h-14 w-auto" src="{{ $store->logo_url }}" alt="{{ $store->name }}">
                        @else
                            <span class="font-serif font-semibold text-2xl md:text-3xl tracking-wide text-[#1A1A1A]">{{ $store->name }}</span>
                        @endif
                    </a>
                </div>

                <!-- Right: Icons -->
                <div class="flex-1 flex items-center justify-end space-x-4 md:space-x-5">
                    <button class="text-[#1A1A1A] hover:text-[#C8A68B] transition hidden md:block">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </button>
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

            <!-- Bottom Row: Navigation (Desktop) -->
            <nav class="hidden md:flex justify-center space-x-10 mt-6 pb-2">
                <a href="{{ route('store.show', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Home</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Shop</a>
                
                @foreach($categories->take(3) as $cat)
                    <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">
                        {{ $cat->name }}
                    </a>
                @endforeach
                
                <a href="#" class="text-[#1A1A1A] hover:text-[#C8A68B] font-medium text-sm transition">Contact</a>
            </nav>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div class="md:hidden" x-show="mobileMenuOpen" style="display: none;">
            <div class="px-4 pt-2 pb-4 space-y-1 bg-white border-t border-gray-100 shadow-inner">
                <a href="{{ route('store.show', $store->slug) }}" class="block px-3 py-2 text-sm font-medium text-gray-800">Home</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="block px-3 py-2 text-sm font-medium text-gray-800">Shop</a>
                @foreach($categories as $cat)
                    <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="block px-3 py-2 text-sm font-medium text-gray-800">{{ $cat->name }}</a>
                @endforeach
            </div>
        </div>

        <!-- Search Overlay -->
        <div x-show="searchOpen" style="display: none;" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-4"
             class="absolute top-0 inset-x-0 bg-white border-b border-gray-100 shadow-2xl z-50 p-6 md:p-10">
            <div class="max-w-4xl mx-auto relative">
                <form action="{{ route('store.catalog', $store->slug) }}" method="GET" class="flex items-center">
                    <svg class="w-6 h-6 text-gray-400 absolute left-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" name="search" placeholder="Search for products, categories..." class="w-full pl-14 pr-12 py-4 md:py-5 text-lg md:text-2xl font-serif text-[#1A1A1A] bg-gray-50 rounded-full border-none focus:ring-0 focus:outline-none placeholder-gray-300" autofocus>
                </form>
                <button @click="searchOpen = false" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
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

        <!-- Elegant Hero Banner -->
        <section class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 py-12 md:py-20">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-10">
                
                <!-- Left Image (Oval/Pill) -->
                <div class="hidden lg:block w-[300px] h-[450px] flex-shrink-0 relative" data-animate>
                    <div class="w-full h-full rounded-full overflow-hidden shadow-lg border-8 border-[#FDF8EF]">
                        <img src="{{ $store->cover_path ? $store->cover_url : 'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&q=80&w=800' }}" alt="Hero Image" class="w-full h-full object-cover">
                    </div>
                </div>

                <!-- Center Text -->
                <div class="flex-1 text-center max-w-2xl mx-auto z-10" data-animate>
                    @if($store->hero_badge)
                    <div class="inline-block px-4 py-1.5 rounded-full text-xs font-bold tracking-widest uppercase mb-6" style="background-color: var(--secondary); color: white;">
                        {{ $store->hero_badge }}
                    </div>
                    @endif

                    <h2 class="text-4xl md:text-6xl font-serif text-[#1A1A1A] mb-4 leading-tight">
                        {{ $store->hero_title ?? 'Welcome to the world of elegance' }}
                    </h2>

                    @if($store->hero_subtitle)
                    <p class="text-gray-500 text-sm md:text-base mb-10 max-w-lg mx-auto leading-relaxed">
                        {{ $store->hero_subtitle }}
                    </p>
                    @else
                    <p class="text-gray-500 text-sm md:text-base mb-10 max-w-lg mx-auto leading-relaxed">
                        Descubre nuestra colección única. Diseñada con pasión para la vida moderna, ofreciendo productos funcionales y elegantes.
                    </p>
                    @endif

                    <a href="{{ route('store.catalog', $store->slug) }}" class="inline-flex items-center justify-center w-32 h-32 rounded-full border border-gray-200 hover:border-[#C8A68B] transition-colors relative group mx-auto">
                        <div class="absolute inset-2 rounded-full flex items-center justify-center transition-transform group-hover:scale-95" style="background-color: #E2CDBC;">
                            <span class="text-[#1A1A1A] text-sm font-medium">Ver Todo</span>
                        </div>
                    </a>
                </div>

                <!-- Right Image -->
                <div class="hidden lg:block w-[350px] h-[400px] flex-shrink-0" data-animate>
                    <img src="https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&q=80&w=800" alt="Collection" class="w-full h-full object-cover rounded-tl-[100px]">
                </div>
            </div>
        </section>
        <p class="text-center text-xs text-gray-500 mt-2">*Válido para pedidos realizados hasta las 5:00 p.m. Aplica T&C.</p>

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

        <!-- Products Section (Bento Gallery) -->
        <section class="py-16 bg-[#FDF8EF]">
            <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12">
                <div class="flex items-center mb-10" data-animate>
                    <h2 class="text-3xl md:text-4xl font-serif text-[#1A1A1A] italic tracking-wide">Best Selling</h2>
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
                    <div class="{{ $isLarge ? 'md:col-span-2 md:row-span-2' : 'col-span-1 row-span-1' }} group flex flex-col relative" data-animate>
                        
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
             customer: { name: '', email: '', phone: '', address: '', country: '{{ request()->cookie('user_country') ?? 'PE' }}', state: '', city: '', zipcode: '', notes: '', express_shipping: false },
             storeSlug: '{{ $store->slug }}',
             isExpressEnabled: {{ $store->is_express_shipping_enabled ? 'true' : 'false' }},
             expressCost: {{ $store->express_shipping_cost ?? 0 }},
             currencySymbol: '{{ request()->cookie("user_country") === "US" ? "$" : "S/" }}',
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
                                <template x-if="!item.image">
                                    <div class="w-16 h-16 bg-white border border-gray-200 rounded-lg flex items-center justify-center text-xl shadow-sm">📦</div>
                                </template>
                                <div class="flex-1">
                                    <h4 class="text-gray-800 font-semibold text-sm leading-tight" x-text="item.name"></h4>
                                     <div class="flex justify-between items-center mt-2">
                                        <p class="text-accent font-bold text-sm" x-text="currencySymbol + ' ' + (item.price * item.quantity).toFixed(2)"></p>
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
                            <input type="text" x-model="customer.name" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Correo Electrónico *</label>
                                <input type="email" x-model="customer.email" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition" required>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Teléfono (WhatsApp) *</label>
                                <input type="text" x-model="customer.phone" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">País *</label>
                                <select x-model="customer.country" @change="updateShipping()" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition" required>
                                    <option value="PE">Perú</option>
                                    <option value="US">Estados Unidos</option>
                                    <option value="MX">México</option>
                                    <option value="CO">Colombia</option>
                                    <option value="ES">España</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Estado / Departamento</label>
                                <input type="text" x-model="customer.state" @change="updateShipping()" placeholder="Ej: Lima, California" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Dirección de Envío</label>
                            <input type="text" x-model="customer.address" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Ciudad</label>
                                <input type="text" x-model="customer.city" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 mb-1">Código Postal</label>
                                <input type="text" x-model="customer.zipcode" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-accent focus:ring-1 focus:ring-accent outline-none transition">
                            </div>
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
                                        <p class="text-xs text-green-600/80 mt-1">Llega más rápido a tu domicilio. <span x-show="expressCost > 0" x-text="'+ ' + currencySymbol + ' ' + expressCost.toFixed(2)"></span><span x-show="expressCost == 0">¡Es gratis!</span></p>
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
                        <span class="font-black text-xl text-accent" x-text="currencySymbol + ' ' + cartTotal.toFixed(2)"></span>
                    </div>
                    
                    <template x-if="checkoutStep === 1">
                        <button @click="checkoutStep = 2" class="w-full py-3 rounded-xl font-bold text-white transition-all shadow-md text-center flex items-center justify-center gap-2" style="background: var(--accent)">
                            Siguiente Paso →
                        </button>
                    </template>
                    
                    <template x-if="checkoutStep === 2">
                        <div class="flex gap-2">
                            <button @click="checkoutStep = 1" class="px-4 py-3 bg-white border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">
                                ←
                            </button>
                            <button id="btnSubmitOrder" @click="submitOrder" class="flex-1 py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] rounded-xl font-bold text-white transition-colors shadow-md text-center flex items-center justify-center gap-2" style="background: var(--accent)">
                                Confirmar y Pagar
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    </div>

    <!-- Modal de Selección de País (Se muestra si no hay cookie 'user_country') -->
    @if(!request()->hasCookie('user_country'))
    <div x-data="{
        showModal: true,
        selectCountry(countryCode) {
            document.cookie = 'user_country=' + countryCode + '; path=/; max-age=31536000; domain=' + window.location.hostname;
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
