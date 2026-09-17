@php
    $currentLang = \App\Helpers\TranslationHelper::currentLang();
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $categories = $categories ?? ($store->categories ?? collect());
    $headerCategories = $categories->where('show_in_header', true)->take(6);
    if ($headerCategories->isEmpty()) {
        $headerCategories = $categories->take(6);
    }
    $headerCountries = $store->getEnabledCountriesWithDetails();
    if (empty($headerCountries)) {
        $headerCountries = [
            'PE' => \App\Helpers\CurrencyHelper::getCountryInfo('PE'),
            'US' => \App\Helpers\CurrencyHelper::getCountryInfo('US')
        ];
    }
    $currentCountry = \App\Helpers\CurrencyHelper::currentCountry();
    $currentCurrency = \App\Helpers\CurrencyHelper::currentCurrency();
    $currentCountryInfo = $headerCountries[$currentCountry] ?? \App\Helpers\CurrencyHelper::getCountryInfo($currentCountry) ?? reset($headerCountries);
@endphp

<!-- Top Announcement Bar: Maetek Pastel Sage -->
<div class="bg-[#E5EEDF] border-b border-[#CCDBC0] py-2 px-3 sm:px-6 text-center text-[11px] sm:text-xs font-semibold text-[#4A6038] tracking-wide flex items-center justify-center gap-2 font-brand">
    <span class="inline-block w-1.5 h-1.5 rounded-full bg-[#7DA268] animate-pulse"></span>
    <span>{{ $isEn ? '🌿 YOUR LIFE, MADE EASIER • We ship to multiple countries • 100% Guaranteed' : '🌿 TU VIDA, MÁS FÁCIL — Llegamos a varios países • Compra 100% protegida' }}</span>
</div>

<!-- Header Component -->
<header class="bg-[#FAF7F2] sticky top-0 z-50 border-b border-stone-200/70 shadow-xs backdrop-blur-md" x-data="{ mobileMenuOpen: false, searchOpen: false }">
    <div class="max-w-screen-2xl mx-auto px-3 sm:px-6 lg:px-12 py-3 sm:py-4 md:py-4.5">
        <!-- Top Row -->
        <div class="flex justify-between items-center">
            
            <!-- Left: Settings (Language & Currency) -->
            <div class="flex-1 flex items-center gap-1 sm:gap-2.5 min-w-0">
                @if($store->is_multilanguage_enabled)
                <div class="relative shrink-0" x-data="{ 
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
                            class="flex items-center gap-1 px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white hover:bg-stone-50 text-[#1E1D1B] hover:text-[#C8A68B] text-[11px] sm:text-xs font-bold tracking-wider transition border border-stone-200 shadow-2xs whitespace-nowrap cursor-pointer">
                        <span class="text-xs">🌐</span>
                        <span x-text="currentLang"></span>
                        <svg class="w-2.5 h-2.5 text-stone-400 transition-transform duration-200" :class="langOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="langOpen" style="display: none;" 
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute left-0 mt-2 w-32 bg-white rounded-xl shadow-xl border border-stone-100 py-1.5 z-50 overflow-hidden">
                        <button type="button" @click="switchLanguage('ES')" 
                                class="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-stone-50 flex items-center justify-between" 
                                :class="currentLang === 'ES' ? 'text-[#C8A68B] font-bold bg-[#FAF7F2]' : 'text-[#1E1D1B]'">
                            <span>Español</span>
                            <span x-show="currentLang === 'ES'" class="text-[#C8A68B] text-xs font-bold">✓</span>
                        </button>
                        <button type="button" @click="switchLanguage('EN')" 
                                class="w-full text-left px-4 py-2 text-xs font-semibold hover:bg-stone-50 flex items-center justify-between" 
                                :class="currentLang === 'EN' ? 'text-[#C8A68B] font-bold bg-[#FAF7F2]' : 'text-[#1E1D1B]'">
                            <span>English</span>
                            <span x-show="currentLang === 'EN'" class="text-[#C8A68B] text-xs font-bold">✓</span>
                        </button>
                    </div>
                </div>
                @endif

                <!-- Country & Currency Selector -->
                <div class="relative shrink-0" x-data="{ 
                        currOpen: false, 
                        currentCountry: '{{ $currentCountry }}',
                        currentCurrency: '{{ $currentCurrency }}',
                        switchCountry(country, currency) {
                            document.cookie = 'user_country=' + country + '; path=/; max-age=31536000; SameSite=Lax';
                            document.cookie = 'store_currency=' + currency + '; path=/; max-age=31536000; SameSite=Lax';
                            
                            if (window.TribioCart && window.TribioCart.items && window.TribioCart.items.length > 0) {
                                window.TribioCart.clear();
                            }
                            
                            window.location.reload();
                        }
                    }">
                    <button @click="currOpen = !currOpen" @click.away="currOpen = false" 
                            class="flex items-center gap-1 sm:gap-1.5 px-2.5 py-1 sm:px-3 sm:py-1.5 rounded-full bg-white hover:bg-stone-50 text-[#1E1D1B] hover:text-[#C8A68B] text-[11px] sm:text-xs font-bold tracking-wider transition border border-stone-200 shadow-2xs cursor-pointer whitespace-nowrap flex-nowrap shrink-0"
                            title="{{ $isEn ? 'Select country / currency' : 'Seleccionar país / moneda' }}">
                        <img src="{{ $currentCountryInfo['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($currentCountry) }}" 
                             alt="{{ $currentCountryInfo['name'] ?? '' }}" 
                             class="w-4 h-3 object-cover rounded-xs border border-stone-300 shadow-2xs inline-block flex-shrink-0" />
                        <span class="whitespace-nowrap">{{ $currentCurrency }}<span class="hidden sm:inline"> ({{ \App\Helpers\CurrencyHelper::symbol($currentCurrency) }})</span></span>
                        <svg class="w-2.5 h-2.5 text-stone-400 transition-transform duration-200 flex-shrink-0" :class="currOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="currOpen" style="display: none;" 
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute left-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-stone-100 py-1.5 z-50 overflow-hidden">
                        @foreach($headerCountries as $hCode => $hData)
                            <button type="button" @click="switchCountry('{{ $hCode }}', '{{ $hData['currency'] }}')" 
                                    class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-stone-50 flex items-center justify-between transition cursor-pointer" 
                                    :class="currentCountry === '{{ $hCode }}' ? 'text-[#C8A68B] font-bold bg-[#FAF7F2]' : 'text-[#1E1D1B]'">
                                <div class="flex items-center gap-2">
                                    <img src="{{ $hData['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($hCode) }}" 
                                         alt="{{ $hData['name'] }}" 
                                         class="w-4 h-3 object-cover rounded-xs border border-stone-200 shadow-2xs inline-block flex-shrink-0" />
                                    <span>{{ $hData['name'] }}</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] text-stone-400 font-mono">{{ $hData['currency'] }}</span>
                                    <span x-show="currentCountry === '{{ $hCode }}'" class="text-[#C8A68B] text-xs font-bold">✓</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Center: Logo with Maetek Identity -->
            <div class="flex-shrink-0 flex items-center justify-center px-1 sm:px-4">
                <a href="{{ route('store.show', $store->slug) }}" class="pointer-events-auto flex items-center gap-2 group">
                    @if($store->logo_path)
                        <img class="h-9 sm:h-11 md:h-14 w-auto object-contain transition-transform duration-200 group-hover:scale-105" src="{{ $store->logo_url }}" alt="{{ $store->name }}">
                    @else
                        <div class="flex flex-col items-center relative py-1">
                            <span class="font-bold text-2xl sm:text-3xl md:text-4xl tracking-tight text-[#1E1D1B] group-hover:text-[#C8A68B] transition-colors font-brand leading-none">
                                {{ $store->name }}
                            </span>
                            <!-- Maetek warm caramel smile arc curve -->
                            <svg class="w-16 sm:w-20 md:w-24 h-2 mt-0.5 text-[#C8A68B]" viewBox="0 0 100 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 3C30 11 70 11 96 3" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                            </svg>
                            <span class="text-[8px] md:text-[9px] tracking-[0.25em] text-[#6E6A63] uppercase font-semibold mt-0.5 hidden sm:block font-brand">— TU VIDA, MÁS FÁCIL —</span>
                        </div>
                    @endif
                </a>
            </div>

            <!-- Right: Icons with Pastel Accents -->
            <div class="flex-1 flex items-center justify-end space-x-1.5 sm:space-x-3 md:space-x-4">
                <!-- Tribio Pass / Customer Portal (Desktop only) -->
                <button @click="$dispatch('open-customer-modal')" 
                        class="text-[#1E1D1B] hover:text-[#C8A68B] p-2 rounded-full hover:bg-stone-100/70 transition hidden md:flex items-center gap-1.5 cursor-pointer" 
                        title="{{ $isEn ? 'My Account / My Orders' : 'Mi Cuenta / Mis Pedidos' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </button>

                <!-- Search (Desktop only; on mobile it is in the toggle menu) -->
                <button @click="searchOpen = true" 
                        class="text-[#1E1D1B] hover:text-[#C8A68B] p-2 rounded-full hover:bg-stone-100/70 transition hidden md:flex items-center justify-center cursor-pointer" 
                        title="{{ $isEn ? 'Search' : 'Buscar' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>

                <!-- Cart Drawer Trigger (Pastel Caramel Badge) -->
                <button onclick="document.getElementById('cartDrawer').style.display='flex'" 
                        class="text-[#1E1D1B] hover:text-[#C8A68B] p-2 rounded-full hover:bg-stone-100/70 transition relative cursor-pointer" 
                        title="{{ $isEn ? 'Cart' : 'Carrito' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    <span data-cart-count class="absolute -top-0.5 -right-0.5 bg-[#C8A68B] text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-xs font-brand">0</span>
                </button>
                
                <!-- Mobile Hamburger Toggle -->
                <button class="md:hidden text-[#1E1D1B] p-2 rounded-lg hover:bg-stone-100/70 transition" @click="mobileMenuOpen = !mobileMenuOpen" title="Menú">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>
        </div>

        <!-- Bottom Row: Navigation (Desktop) with Fredoka Typography -->
        <nav class="hidden md:flex flex-wrap justify-center items-center gap-x-7 lg:gap-x-9 gap-y-2 mt-4 pb-1 font-brand">
            <a href="{{ route('store.show', $store->slug) }}" 
               class="text-base lg:text-[17px] font-semibold transition-colors duration-200 {{ request()->routeIs('store.show') ? 'text-[#C8A68B] font-bold border-b-2 border-[#C8A68B] pb-1' : 'text-[#363430] hover:text-[#C8A68B]' }}">
                {{ $isEn ? 'Home' : 'Inicio' }}
            </a>
            
            <a href="{{ route('store.catalog', $store->slug) }}" 
               class="text-base lg:text-[17px] font-semibold transition-colors duration-200 {{ (request()->routeIs('store.catalog') && !request()->has('category')) ? 'text-[#C8A68B] font-bold border-b-2 border-[#C8A68B] pb-1' : 'text-[#363430] hover:text-[#C8A68B]' }}">
                {{ $isEn ? 'Shop' : 'Catálogo' }}
            </a>
            
            @foreach($headerCategories as $cat)
                @php
                    $isCatActive = request()->routeIs('store.catalog') && (request('category') == $cat->slug || request('category') == $cat->id);
                @endphp
                <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" 
                   class="text-base lg:text-[17px] font-semibold transition-colors duration-200 {{ $isCatActive ? 'text-[#C8A68B] font-bold border-b-2 border-[#C8A68B] pb-1' : 'text-[#363430] hover:text-[#C8A68B]' }}">
                    {{ $cat->getTranslatedName() }}
                </a>
            @endforeach
            
            <a href="{{ route('store.contact', $store->slug) }}" 
               class="text-base lg:text-[17px] font-semibold transition-colors duration-200 {{ request()->routeIs('store.contact') ? 'text-[#C8A68B] font-bold border-b-2 border-[#C8A68B] pb-1' : 'text-[#363430] hover:text-[#C8A68B]' }}">
                {{ $isEn ? 'Contact' : 'Contacto' }}
            </a>
        </nav>
    </div>

    <!-- Mobile Menu Dropdown (Pastel Aesthetic) -->
    <div class="md:hidden" x-show="mobileMenuOpen" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0">
        <div class="px-5 pt-3 pb-5 space-y-2.5 bg-[#FAF7F2] border-t border-stone-200 shadow-xl font-brand">
            
            <!-- Mobile Search Bar (Moved into Toggle Menu per user request) -->
            <form action="{{ route('store.catalog', $store->slug) }}" method="GET" class="relative pb-1">
                <input type="text" name="search" 
                       placeholder="{{ $isEn ? 'Search products, brands...' : 'Buscar productos, marcas...' }}" 
                       class="w-full pl-10 pr-4 py-2.5 bg-white border border-stone-200 rounded-xl text-xs sm:text-sm text-stone-800 placeholder-stone-400 focus:outline-none focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] transition shadow-2xs">
                <button type="submit" class="absolute left-3 top-2.5 text-stone-400 hover:text-[#C8A68B]" title="Buscar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
            </form>

            <!-- Tribio Account Button -->
            <button @click="$dispatch('open-customer-modal'); mobileMenuOpen = false" 
                    class="w-full text-left px-3.5 py-2.5 rounded-xl bg-white text-sm font-bold text-[#1E1D1B] hover:text-[#C8A68B] flex items-center justify-between border border-stone-200 mb-2">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-[#C8A68B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    {{ $isEn ? 'My Account / Tribio Orders' : 'Mi Cuenta / Pedidos Tribio' }}
                </span>
                    <svg class="w-4 h-4 text-[#C8A68B]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    {{ $isEn ? 'My Account / Tribio Orders' : 'Mi Cuenta / Pedidos Tribio' }}
                </span>
                <span class="text-xs text-gray-400">→</span>
            </button>

            <!-- Links -->
            <a href="{{ route('store.show', $store->slug) }}" 
               class="block px-3 py-2 text-base font-semibold {{ request()->routeIs('store.show') ? 'text-[#C8A68B]' : 'text-stone-800' }}">
                {{ $isEn ? 'Home' : 'Inicio' }}
            </a>
            
            <a href="{{ route('store.catalog', $store->slug) }}" 
               class="block px-3 py-2 text-base font-semibold {{ (request()->routeIs('store.catalog') && !request()->has('category')) ? 'text-[#C8A68B]' : 'text-stone-800' }}">
                {{ $isEn ? 'Shop' : 'Catálogo' }}
            </a>

            @if($headerCategories->isNotEmpty())
                <div class="border-t border-stone-200/80 pt-2.5 pb-1 my-1">
                    <span class="px-3 text-[11px] font-bold text-stone-400 uppercase tracking-wider">
                        {{ $isEn ? 'Categories' : 'Categorías' }}
                    </span>
                    @foreach($headerCategories as $cat)
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" 
                           class="block px-3 py-2 text-[15px] font-medium text-stone-700 hover:text-[#C8A68B]">
                            {{ $cat->getTranslatedName() }}
                        </a>
                    @endforeach
                </div>
            @endif

            <a href="{{ route('store.contact', $store->slug) }}" 
               class="block px-3 py-2 text-base font-semibold {{ request()->routeIs('store.contact') ? 'text-[#C8A68B]' : 'text-stone-800' }}">
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

            <!-- Mobile Country & Currency Switcher -->
            <div class="border-t border-gray-100 pt-3 mt-2 flex items-center justify-between px-3">
                <span class="text-xs font-medium text-gray-500">{{ $isEn ? 'Country / Currency' : 'País / Moneda' }}</span>
                <select onchange="const [c, cur] = this.value.split(':'); document.cookie='user_country=' + c + '; path=/; max-age=31536000; SameSite=Lax'; document.cookie='store_currency=' + cur + '; path=/; max-age=31536000; SameSite=Lax'; if(window.TribioCart && window.TribioCart.items && window.TribioCart.items.length > 0) window.TribioCart.clear(); window.location.reload();"
                        class="bg-stone-100 border border-stone-200 text-xs font-bold rounded-lg px-2.5 py-1 text-[#1A1A1A] outline-none cursor-pointer">
                    @foreach($headerCountries as $hCode => $hData)
                        <option value="{{ $hCode }}:{{ $hData['currency'] }}" {{ $currentCountry === $hCode ? 'selected' : '' }}>
                            {{ $hData['flag'] }} {{ $hData['name'] }} ({{ $hData['currency'] }} {{ $hData['symbol'] }})
                        </option>
                    @endforeach
                </select>
            </div>
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
