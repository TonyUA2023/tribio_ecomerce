@php
    $currentLang = \App\Helpers\TranslationHelper::currentLang();
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $categories = $categories ?? ($store->categories ?? collect());
    $headerCategories = $categories->where('show_in_header', true)->take(6);
    if ($headerCategories->isEmpty()) {
        $headerCategories = $categories->take(6);
    }
@endphp

<!-- Header Component -->
<header class="bg-[#FDF8EF] sticky top-0 z-50 border-b border-gray-200/50 shadow-sm" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-12 py-4 md:py-5">
        <!-- Top Row -->
        <div class="flex justify-between items-center">
            
            <!-- Left: Settings (Language & Currency) -->
            <div class="flex-1 flex items-center space-x-3">
                @if($store->is_multilanguage_enabled)
                <div class="relative" x-data="{ 
                        langOpen: false, 
                        currentLang: '{{ strtoupper($currentLang) }}',
                        switchLanguage(lang) {
                            const target = lang.toLowerCase();
                            // 1. Guardar cookie principal para Laravel y backend
                            document.cookie = 'store_lang=' + target + '; path=/; max-age=31536000; SameSite=Lax';
                            
                            // 2. Sincronizar cookie de Google Translate
                            if(target === 'en') {
                                document.cookie = 'googtrans=/es/en; path=/; max-age=31536000; SameSite=Lax';
                                if (window.location.hostname.includes('.') && !/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname)) {
                                    document.cookie = 'googtrans=/es/en; domain=' + window.location.hostname + '; path=/; max-age=31536000; SameSite=Lax';
                                }
                            } else {
                                document.cookie = 'googtrans=/es/es; path=/; max-age=31536000; SameSite=Lax';
                                document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax';
                                if (window.location.hostname.includes('.') && !/^\d+\.\d+\.\d+\.\d+$/.test(window.location.hostname)) {
                                    document.cookie = 'googtrans=/es/es; domain=' + window.location.hostname + '; path=/; max-age=31536000; SameSite=Lax';
                                    document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; domain=' + window.location.hostname + '; path=/; SameSite=Lax';
                                }
                            }
                            
                            // 3. Forzar evento si Google Translate está en el DOM
                            try {
                                const combo = document.querySelector('.goog-te-combo');
                                if (combo) {
                                    combo.value = target;
                                    combo.dispatchEvent(new Event('change'));
                                }
                            } catch(e) {}

                            // 4. Recargar la página para que el backend renderice en el idioma elegido
                            window.location.reload();
                        }
                    }">
                    <button @click="langOpen = !langOpen" @click.away="langOpen = false" 
                            class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-full bg-white/70 hover:bg-white text-[#1A1A1A] hover:text-[#C8A68B] text-xs font-bold tracking-wider transition border border-stone-200/80 shadow-xs">
                        <span class="text-xs">🌐</span>
                        <span x-text="currentLang"></span>
                        <svg class="w-3 h-3 text-gray-500 transition-transform duration-200" :class="langOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="langOpen" style="display: none;" 
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute left-0 mt-2 w-32 bg-white rounded-xl shadow-xl border border-gray-100 py-1.5 z-50 overflow-hidden">
                        <button type="button" @click="switchLanguage('ES')" 
                                class="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-stone-50 flex items-center justify-between" 
                                :class="currentLang === 'ES' ? 'text-[#C8A68B] font-bold bg-[#FDF8EF]/50' : 'text-gray-700'">
                            <span>Español</span>
                            <span x-show="currentLang === 'ES'" class="text-[#C8A68B] text-xs font-bold">✓</span>
                        </button>
                        <button type="button" @click="switchLanguage('EN')" 
                                class="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-stone-50 flex items-center justify-between" 
                                :class="currentLang === 'EN' ? 'text-[#C8A68B] font-bold bg-[#FDF8EF]/50' : 'text-gray-700'">
                            <span>English</span>
                            <span x-show="currentLang === 'EN'" class="text-[#C8A68B] text-xs font-bold">✓</span>
                        </button>
                    </div>
                </div>
                @endif

                <!-- Currency -->
                <div class="text-[11px] font-bold tracking-wider text-gray-500 uppercase px-2 py-1 rounded bg-stone-100/70 border border-stone-200/50">
                    {{ request()->cookie('user_country') === 'US' ? 'USD ($)' : 'PEN (S/)' }}
                </div>
            </div>

            <!-- Center: Logo -->
            <div class="flex-shrink-0 flex items-center justify-center">
                <a href="{{ route('store.show', $store->slug) }}" class="pointer-events-auto">
                    @if($store->logo_path)
                        <img class="h-10 md:h-13 w-auto object-contain" src="{{ $store->logo_url }}" alt="{{ $store->name }}">
                    @else
                        <span class="font-semibold text-2xl md:text-3xl tracking-wide text-[#1A1A1A]">{{ $store->name }}</span>
                    @endif
                </a>
            </div>

            <!-- Right: Icons -->
            <div class="flex-1 flex items-center justify-end space-x-3 md:space-x-5">
                <!-- Tribio Pass / Customer Portal -->
                <button @click="$dispatch('open-customer-modal')" 
                        class="text-[#1A1A1A] hover:text-[#C8A68B] p-2 rounded-full hover:bg-white/60 transition hidden md:flex items-center gap-1.5" 
                        title="{{ $isEn ? 'My Account / My Orders' : 'Mi Cuenta / Mis Pedidos' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </button>

                <!-- Search -->
                <button @click="searchOpen = true" 
                        class="text-[#1A1A1A] hover:text-[#C8A68B] p-2 rounded-full hover:bg-white/60 transition"
                        title="{{ $isEn ? 'Search' : 'Buscar' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>

                <!-- Cart Drawer Trigger -->
                <button onclick="document.getElementById('cartDrawer').style.display='flex'" 
                        class="text-[#1A1A1A] hover:text-[#C8A68B] p-2 rounded-full hover:bg-white/60 transition relative"
                        title="{{ $isEn ? 'Cart' : 'Carrito' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    <span data-cart-count class="absolute -top-0.5 -right-0.5 bg-red-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-xs">0</span>
                </button>
                
                <!-- Mobile Hamburger -->
                <button class="md:hidden text-[#1A1A1A] p-2 rounded-lg hover:bg-white/60" @click="mobileMenuOpen = !mobileMenuOpen">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>
        </div>

        <!-- Bottom Row: Navigation (Desktop) -->
        <nav class="hidden md:flex justify-center items-center space-x-8 mt-5 pb-1">
            <a href="{{ route('store.show', $store->slug) }}" 
               class="font-medium text-sm transition {{ request()->routeIs('store.show') ? 'text-[#C8A68B] font-bold' : 'text-[#1A1A1A] hover:text-[#C8A68B]' }}">
                {{ $isEn ? 'Home' : 'Inicio' }}
            </a>
            
            <a href="{{ route('store.catalog', $store->slug) }}" 
               class="font-medium text-sm transition {{ (request()->routeIs('store.catalog') && !request()->has('category')) ? 'text-[#C8A68B] font-bold' : 'text-[#1A1A1A] hover:text-[#C8A68B]' }}">
                {{ $isEn ? 'Shop' : 'Catálogo' }}
            </a>
            
            @foreach($headerCategories as $cat)
                @php
                    $isCatActive = request()->routeIs('store.catalog') && (request('category') == $cat->slug || request('category') == $cat->id);
                @endphp
                <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" 
                   class="font-medium text-sm transition {{ $isCatActive ? 'text-[#C8A68B] font-bold' : 'text-[#1A1A1A] hover:text-[#C8A68B]' }}">
                    {{ $cat->getTranslatedName() }}
                </a>
            @endforeach
            
            <a href="{{ route('store.contact', $store->slug) }}" 
               class="font-medium text-sm transition {{ request()->routeIs('store.contact') ? 'text-[#C8A68B] font-bold' : 'text-[#1A1A1A] hover:text-[#C8A68B]' }}">
                {{ $isEn ? 'Contact' : 'Contacto' }}
            </a>
        </nav>
    </div>

    <!-- Mobile Menu Dropdown -->
    <div class="md:hidden" x-show="mobileMenuOpen" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0">
        <div class="px-5 pt-3 pb-5 space-y-2 bg-white border-t border-gray-100 shadow-xl">
            
            <!-- Tribio Account Button -->
            <button @click="$dispatch('open-customer-modal'); mobileMenuOpen = false" 
                    class="w-full text-left px-3.5 py-2.5 rounded-xl bg-[#FDF8EF] text-sm font-bold text-[#1A1A1A] hover:text-[#C8A68B] flex items-center justify-between border border-stone-200/60 mb-2">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-[#C8A68B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    {{ $isEn ? 'My Account / Tribio Orders' : 'Mi Cuenta / Pedidos Tribio' }}
                </span>
                <span class="text-xs text-gray-400">→</span>
            </button>

            <!-- Links -->
            <a href="{{ route('store.show', $store->slug) }}" 
               class="block px-3 py-2 text-sm font-semibold {{ request()->routeIs('store.show') ? 'text-[#C8A68B]' : 'text-gray-800' }}">
                {{ $isEn ? 'Home' : 'Inicio' }}
            </a>
            
            <a href="{{ route('store.catalog', $store->slug) }}" 
               class="block px-3 py-2 text-sm font-semibold {{ (request()->routeIs('store.catalog') && !request()->has('category')) ? 'text-[#C8A68B]' : 'text-gray-800' }}">
                {{ $isEn ? 'Shop' : 'Catálogo' }}
            </a>

            @if($headerCategories->isNotEmpty())
                <div class="border-t border-gray-100 pt-2 pb-1 my-1">
                    <span class="px-3 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                        {{ $isEn ? 'Categories' : 'Categorías' }}
                    </span>
                    @foreach($headerCategories as $cat)
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" 
                           class="block px-3 py-2 text-sm text-gray-700 hover:text-[#C8A68B]">
                            {{ $cat->getTranslatedName() }}
                        </a>
                    @endforeach
                </div>
            @endif

            <a href="{{ route('store.contact', $store->slug) }}" 
               class="block px-3 py-2 text-sm font-semibold {{ request()->routeIs('store.contact') ? 'text-[#C8A68B]' : 'text-gray-800' }}">
                {{ $isEn ? 'Contact' : 'Contacto' }}
            </a>

            <!-- Mobile Language Switcher (if enabled) -->
            @if($store->is_multilanguage_enabled)
                <div class="border-t border-gray-100 pt-3 mt-2 flex items-center justify-between px-3">
                    <span class="text-xs font-medium text-gray-500">{{ $isEn ? 'Language' : 'Idioma' }}</span>
                    <div class="inline-flex rounded-lg p-0.5 bg-stone-100 text-xs font-bold">
                        <button type="button" 
                                onclick="document.cookie='store_lang=es; path=/; max-age=31536000; SameSite=Lax'; document.cookie='googtrans=/es/es; path=/; max-age=31536000; SameSite=Lax'; window.location.reload();" 
                                class="px-3 py-1 rounded-md transition {{ !$isEn ? 'bg-white text-[#1A1A1A] shadow-xs' : 'text-gray-500' }}">
                            Español
                        </button>
                        <button type="button" 
                                onclick="document.cookie='store_lang=en; path=/; max-age=31536000; SameSite=Lax'; document.cookie='googtrans=/es/en; path=/; max-age=31536000; SameSite=Lax'; window.location.reload();" 
                                class="px-3 py-1 rounded-md transition {{ $isEn ? 'bg-white text-[#1A1A1A] shadow-xs' : 'text-gray-500' }}">
                            English
                        </button>
                    </div>
                </div>
            @endif
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
                <input type="text" name="search" 
                       placeholder="{{ $isEn ? 'Search for products, categories...' : 'Buscar productos, marcas o categorías...' }}" 
                       class="w-full pl-14 pr-12 py-4 md:py-5 text-lg md:text-2xl text-[#1A1A1A] bg-gray-50 rounded-full border-none focus:ring-0 focus:outline-none placeholder-gray-300" autofocus>
            </form>
            <button @click="searchOpen = false" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    </div>
</header>
