{{-- Urban Style header: barras de aviso + cupón con cuenta regresiva, menú centrado y buscador
     subrayado. Funcionalmente es el header estándar (idioma, país/moneda, Tribio Pass, carrito,
     buscador, menú móvil). $overlayHeader = true solo en la portada: flota sobre los banners. --}}
@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $currentLang = \App\Helpers\TranslationHelper::currentLang();
    $tplPreview = $templatePreview ?? false;
    $overlayHeader = $overlayHeader ?? false;
    $categories = $categories ?? ($store->categories ?? collect());
    $headerCategories = $categories->where('show_in_header', true)->take(6);
    if ($headerCategories->isEmpty()) {
        $headerCategories = $categories->whereNull('parent_id')->take(6);
    }
    $headerCountries = $store->getEnabledCountriesWithDetails();
    if (empty($headerCountries)) {
        $headerCountries = [
            'PE' => \App\Helpers\CurrencyHelper::getCountryInfo('PE'),
            'US' => \App\Helpers\CurrencyHelper::getCountryInfo('US'),
        ];
    }
    $currentCountry = \App\Helpers\CurrencyHelper::currentCountry();
    $currentCurrency = \App\Helpers\CurrencyHelper::currentCurrency();
    $currentCountryInfo = $headerCountries[$currentCountry] ?? \App\Helpers\CurrencyHelper::getCountryInfo($currentCountry) ?? reset($headerCountries);
    $promoUrl = $storefrontTheme->link('promo.link');
    $searchPlaceholder = $isEn ? 'Search products…' : 'Buscar productos…';
    $isCatalogRoot = request()->routeIs('store.catalog') && !request()->filled('category') && request('sort') !== 'newest';
@endphp

{{-- Barra negra de aviso --}}
@if($tplPreview || $storefrontTheme->enabled('announcement.enabled'))
<div class="us-ann" data-tpl-show="announcement.enabled" @unless($storefrontTheme->enabled('announcement.enabled')) hidden @endunless>
    <span data-tpl-text="announcement.text">{{ $storefrontTheme->text('announcement.text') }}</span>
</div>
@endif

{{-- Barra de cupón con cuenta regresiva --}}
@if($tplPreview || $storefrontTheme->enabled('promo.enabled'))
<{{ $promoUrl ? 'a' : 'div' }} class="us-promo" @if($promoUrl) href="{{ $promoUrl }}" @endif
     data-tpl-show="promo.enabled" @unless($storefrontTheme->enabled('promo.enabled')) hidden @endunless>
    <span class="us-promo-text" data-tpl-text="promo.text">{{ $storefrontTheme->text('promo.text') }}</span>
    <span class="us-countdown" x-data="usCountdown" data-tpl-choice="promo.ends_at" data-choice="{{ $storefrontTheme->choice('promo.ends_at') }}" x-show="visible" x-cloak
          aria-label="{{ $isEn ? 'Time left' : 'Tiempo restante' }}">
        <template x-if="days > 0"><span class="us-countdown"><b><span x-text="days"></span><small>{{ $isEn ? 'd' : 'Días' }}</small></b><i>:</i></span></template>
        <b><span x-text="pad(hours)"></span><small>Hrs</small></b><i>:</i>
        <b><span x-text="pad(minutes)"></span><small>Min</small></b><i>:</i>
        <b><span x-text="pad(seconds)"></span><small>Seg</small></b>
    </span>
</{{ $promoUrl ? 'a' : 'div' }}>
@endif

<header class="us-header" x-data="{ solid: {{ $overlayHeader ? 'false' : 'true' }}, mobileMenuOpen: false, searchOpen: false, currOpen: false, langOpen: false }"
        @if($overlayHeader)
            data-tpl-choice="header.style" data-choice="{{ $storefrontTheme->choice('header.style') }}"
            x-init="solid = window.scrollY > 24" @scroll.window.passive="solid = window.scrollY > 24"
        @endif
        :class="{ 'is-solid': solid }">
    <div class="us-wrap us-header-row">
        {{-- Logo --}}
        <a href="{{ route('store.show', $store->slug) }}" class="us-logo" aria-label="{{ $store->name }}">
            @if($store->logo_path)
                <img src="{{ $store->logo_url }}" alt="{{ $store->name }}"
                     @if($overlayHeader) data-tpl-choice="header.logo_on_hero" data-choice="{{ $storefrontTheme->choice('header.logo_on_hero') }}" @endif>
            @else
                <span>{{ $store->name }}</span>
            @endif
        </a>

        {{-- Menú (escritorio) --}}
        <nav class="us-nav" aria-label="{{ $isEn ? 'Main menu' : 'Menú principal' }}">
            @forelse($headerCategories as $cat)
                @php $isCatActive = request()->routeIs('store.catalog') && (request('category') == $cat->slug || request('category') == $cat->id); @endphp
                <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="{{ $isCatActive ? 'is-active' : '' }}">{{ $cat->getTranslatedName() }}</a>
            @empty
                <a href="{{ route('store.catalog', $store->slug) }}" class="{{ $isCatalogRoot ? 'is-active' : '' }}">{{ $isEn ? 'Shop' : 'Catálogo' }}</a>
            @endforelse
            <a href="{{ route('store.catalog', ['slug' => $store->slug, 'sort' => 'newest']) }}" class="{{ request('sort') === 'newest' ? 'is-active' : '' }}">{{ $isEn ? 'New in' : 'Novedades' }}</a>
            <a href="{{ route('store.contact', $store->slug) }}" class="{{ request()->routeIs('store.contact') ? 'is-active' : '' }}">{{ $isEn ? 'Help' : 'Ayuda' }}</a>
        </nav>

        {{-- Herramientas --}}
        <div class="us-tools">
            <form action="{{ route('store.catalog', $store->slug) }}" method="GET" class="us-search" role="search">
                <label for="us-search-input" class="us-sr">{{ $searchPlaceholder }}</label>
                <input id="us-search-input" type="search" name="q" value="{{ request('q') }}" placeholder="{{ $searchPlaceholder }}" autocomplete="off">
                <button type="submit" aria-label="{{ $isEn ? 'Search' : 'Buscar' }}">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                </button>
            </form>

            @if($store->is_multilanguage_enabled)
            <div class="us-hide-mobile" style="position: relative">
                <button type="button" class="us-pill-btn" @click="langOpen = !langOpen" @click.outside="langOpen = false" :aria-expanded="langOpen.toString()">{{ strtoupper($currentLang) }}</button>
                <div class="us-dropdown" x-show="langOpen" x-transition.opacity x-cloak style="min-width: 140px">
                    @foreach(['es' => 'Español', 'en' => 'English'] as $langCode => $langName)
                        <button type="button" class="{{ $currentLang === $langCode ? 'is-active' : '' }}" @click="usSwitchLanguage('{{ $langCode }}')">{{ $langName }} @if($currentLang === $langCode)<span aria-hidden="true">✓</span>@endif</button>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="us-hide-mobile" style="position: relative">
                <button type="button" class="us-pill-btn" @click="currOpen = !currOpen" @click.outside="currOpen = false" :aria-expanded="currOpen.toString()" title="{{ $isEn ? 'Country / currency' : 'País / moneda' }}">
                    <img src="{{ $currentCountryInfo['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($currentCountry) }}" alt="">
                    {{ $currentCurrency }}
                </button>
                <div class="us-dropdown" x-show="currOpen" x-transition.opacity x-cloak>
                    @foreach($headerCountries as $hCode => $hData)
                        <button type="button" class="{{ $currentCountry === $hCode ? 'is-active' : '' }}" @click="usSwitchCountry('{{ $hCode }}', '{{ $hData['currency'] }}')">
                            <span style="display: inline-flex; align-items: center; gap: 8px"><img src="{{ $hData['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($hCode) }}" alt=""> {{ $hData['name'] }}</span>
                            <small style="opacity: .6">{{ $hData['currency'] }}</small>
                        </button>
                    @endforeach
                </div>
            </div>

            <button type="button" class="us-icon-btn us-only-mobile" @click="searchOpen = true" aria-label="{{ $isEn ? 'Search' : 'Buscar' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
            </button>
            <button type="button" class="us-icon-btn us-hide-mobile" @click="$dispatch('open-customer-modal')" title="{{ $isEn ? 'My account / My orders' : 'Mi cuenta / Mis pedidos' }}" aria-label="{{ $isEn ? 'My account' : 'Mi cuenta' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 21a8 8 0 0 1 16 0"/></svg>
            </button>
            <button type="button" class="us-icon-btn" onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer'))" aria-label="{{ $isEn ? 'Cart' : 'Carrito' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M5 8h14l-1.2 12.1a1 1 0 0 1-1 .9H7.2a1 1 0 0 1-1-.9L5 8Z"/><path stroke-linecap="round" d="M9 10V6a3 3 0 0 1 6 0v4"/></svg>
                <span class="us-cart-count" data-cart-count>0</span>
            </button>
            <button type="button" class="us-icon-btn us-only-mobile" @click="mobileMenuOpen = true" :aria-expanded="mobileMenuOpen.toString()" aria-label="{{ $isEn ? 'Menu' : 'Menú' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>

    {{-- Búsqueda (celular) --}}
    <template x-teleport="body">
        <div x-show="searchOpen" x-cloak x-transition.opacity class="us-search-overlay" @keydown.escape.window="searchOpen = false">
            <form action="{{ route('store.catalog', $store->slug) }}" method="GET" role="search">
                <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                <input type="search" name="q" placeholder="{{ $searchPlaceholder }}" aria-label="{{ $searchPlaceholder }}" x-effect="searchOpen && $nextTick(() => $el.focus())">
                <button type="button" class="us-icon-btn" @click="searchOpen = false" aria-label="{{ $isEn ? 'Close' : 'Cerrar' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </form>
        </div>
    </template>

    {{-- Menú lateral (celular) --}}
    <template x-teleport="body">
        <div x-show="mobileMenuOpen" x-cloak class="us-drawer" @keydown.escape.window="mobileMenuOpen = false">
            <div class="us-drawer-backdrop" x-show="mobileMenuOpen" x-transition.opacity @click="mobileMenuOpen = false"></div>
            <div class="us-drawer-panel" x-show="mobileMenuOpen"
                 x-transition:enter="us-slide-tr" x-transition:enter-start="us-off-left" x-transition:enter-end="us-on"
                 x-transition:leave="us-slide-tr" x-transition:leave-start="us-on" x-transition:leave-end="us-off-left"
                 role="dialog" aria-modal="true" aria-label="{{ $isEn ? 'Menu' : 'Menú' }}">
                <div class="us-drawer-head">
                    <strong style="font-size: 15px; text-transform: uppercase; letter-spacing: .08em">{{ $isEn ? 'Menu' : 'Menú' }}</strong>
                    <button type="button" class="us-icon-btn" @click="mobileMenuOpen = false" aria-label="{{ $isEn ? 'Close menu' : 'Cerrar menú' }}">
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <div class="us-drawer-body">
                    <form action="{{ route('store.catalog', $store->slug) }}" method="GET" class="us-drawer-search" role="search">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                        <input type="search" name="q" placeholder="{{ $searchPlaceholder }}" aria-label="{{ $searchPlaceholder }}">
                    </form>

                    <nav class="us-drawer-links" aria-label="{{ $isEn ? 'Main menu' : 'Menú principal' }}">
                        <a href="{{ route('store.show', $store->slug) }}" class="{{ request()->routeIs('store.show') ? 'is-active' : '' }}">{{ $isEn ? 'Home' : 'Inicio' }}</a>
                        @foreach($headerCategories as $cat)
                            @php $isCatActive = request()->routeIs('store.catalog') && (request('category') == $cat->slug || request('category') == $cat->id); @endphp
                            <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="{{ $isCatActive ? 'is-active' : '' }}">{{ $cat->getTranslatedName() }} <span aria-hidden="true">›</span></a>
                        @endforeach
                        <a href="{{ route('store.catalog', $store->slug) }}" class="{{ $isCatalogRoot ? 'is-active' : '' }}">{{ $isEn ? 'Full catalog' : 'Todo el catálogo' }} <span aria-hidden="true">›</span></a>
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'sort' => 'newest']) }}">{{ $isEn ? 'New in' : 'Novedades' }} <span aria-hidden="true">›</span></a>
                        <a href="{{ route('store.contact', $store->slug) }}" class="{{ request()->routeIs('store.contact') ? 'is-active' : '' }}">{{ $isEn ? 'Help & contact' : 'Ayuda y contacto' }} <span aria-hidden="true">›</span></a>
                        <button type="button" @click="$dispatch('open-customer-modal'); mobileMenuOpen = false">{{ $isEn ? 'My account / My orders' : 'Mi cuenta / Mis pedidos' }} <span aria-hidden="true">›</span></button>
                    </nav>

                    <div class="us-drawer-prefs">
                        @if($store->is_multilanguage_enabled)
                            <div>
                                <span>{{ $isEn ? 'Language' : 'Idioma' }}</span>
                                <span class="us-seg">
                                    <button type="button" class="{{ !$isEn ? 'is-active' : '' }}" onclick="usSwitchLanguage('es')">ES</button>
                                    <button type="button" class="{{ $isEn ? 'is-active' : '' }}" onclick="usSwitchLanguage('en')">EN</button>
                                </span>
                            </div>
                        @endif
                        <div>
                            <label for="us-country-select">{{ $isEn ? 'Country / Currency' : 'País / Moneda' }}</label>
                            <select id="us-country-select" onchange="const [c, cur] = this.value.split(':'); usSwitchCountry(c, cur);">
                                @foreach($headerCountries as $hCode => $hData)
                                    <option value="{{ $hCode }}:{{ $hData['currency'] }}" @selected($currentCountry === $hCode)>{{ $hData['flag'] ?? '' }} {{ $hData['name'] }} · {{ $hData['currency'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</header>

@once
<script>
    // Mismo comportamiento que el header estándar (Maetek / Soft Market): cookies + recarga.
    function usSwitchCountry(country, currency) {
        document.cookie = 'user_country=' + country + '; path=/; max-age=31536000; SameSite=Lax';
        document.cookie = 'store_currency=' + currency + '; path=/; max-age=31536000; SameSite=Lax';
        if (window.TribioCart && window.TribioCart.items && window.TribioCart.items.length > 0) window.TribioCart.clear();
        window.location.reload();
    }
    function usSwitchLanguage(lang) {
        const host = window.location.hostname;
        const withDomain = host.includes('.') && !/^\d+\.\d+\.\d+\.\d+$/.test(host);
        document.cookie = 'store_lang=' + lang + '; path=/; max-age=31536000; SameSite=Lax';
        if (lang === 'en') {
            document.cookie = 'googtrans=/es/en; path=/; max-age=31536000; SameSite=Lax';
            if (withDomain) document.cookie = 'googtrans=/es/en; domain=' + host + '; path=/; max-age=31536000; SameSite=Lax';
        } else {
            document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax';
            if (withDomain) document.cookie = 'googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; domain=' + host + '; path=/; SameSite=Lax';
        }
        try {
            const combo = document.querySelector('.goog-te-combo');
            if (combo) { combo.value = lang; combo.dispatchEvent(new Event('change')); }
        } catch (e) {}
        window.location.reload();
    }
    document.addEventListener('alpine:init', () => {
        // Cuenta regresiva hasta el final del día elegido (hora del visitante). Relee
        // data-choice en cada tic para que la vista previa del dashboard la cambie en vivo.
        Alpine.data('usCountdown', () => ({
            days: 0, hours: 0, minutes: 0, seconds: 0, visible: false,
            init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
            destroy() { clearInterval(this.timer); },
            tick() {
                const value = this.$el.dataset.choice || '';
                const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
                const end = match ? new Date(+match[1], +match[2] - 1, +match[3], 23, 59, 59) : null;
                const left = end ? Math.floor((end - new Date()) / 1000) : -1;
                this.visible = left > 0;
                if (!this.visible) return;
                this.days = Math.floor(left / 86400);
                this.hours = Math.floor(left % 86400 / 3600);
                this.minutes = Math.floor(left % 3600 / 60);
                this.seconds = left % 60;
            },
            pad(n) { return String(n).padStart(2, '0'); },
        }));
    });
</script>
@endonce
