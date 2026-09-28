{{-- Sport Pro header: franja de mensajes, menú negro (o blanco) con buscador de caja, franja de
     envío. Funcionalmente es el header estándar de Tribio: idioma, país/moneda, Tribio Pass,
     carrito, buscador y menú móvil. --}}
@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $currentLang = \App\Helpers\TranslationHelper::currentLang();
    $t = $storefrontTheme;
    $tplPreview = $templatePreview ?? false;
    $navCategories = $categories ?? $store->categories()->whereNull('parent_id')->get();
    $headerCategories = $navCategories->where('show_in_header', true)->take(7);
    if ($headerCategories->isEmpty()) {
        $headerCategories = $navCategories->whereNull('parent_id')->take(6);
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
    // País y moneda son cookies separadas: si quedaron desalineadas se muestra el país de esa
    // moneda y un script corrige la cookie (mismo arreglo que Urban Style).
    $countryFixedTo = null;
    if (($headerCountries[$currentCountry]['currency'] ?? null) !== $currentCurrency) {
        $countryFixedTo = collect($headerCountries)->search(fn ($c) => ($c['currency'] ?? null) === $currentCurrency) ?: null;
        $currentCountry = $countryFixedTo ?? $currentCountry;
    }
    $currentCountryInfo = $headerCountries[$currentCountry] ?? \App\Helpers\CurrencyHelper::getCountryInfo($currentCountry) ?? reset($headerCountries);
    // "Logo en blanco" solo funciona con fondo transparente; un JPG se volvería un rectángulo blanco.
    $logoIsTransparent = $store->logo_path
        && app(\App\Services\LogoPaletteService::class)->hasTransparentBackground(\Illuminate\Support\Facades\Storage::disk('public')->path($store->logo_path));
    $utilityMessages = collect([0, 1, 2])->map(fn ($i) => $t->text("utility.items.{$i}.text"));
    $shippingUrl = $t->link('shipping.link');
    $saleUrl = $t->link('menu.sale_link');
    $searchPlaceholder = $isEn ? 'Search products' : 'Buscar productos';
    $activeCategory = request()->routeIs('store.catalog') ? (string) request('category') : '';
@endphp

{{-- Franja blanca de mensajes --}}
@if($tplPreview || $t->enabled('utility.enabled'))
<div class="sp-utility" data-tpl-show="utility.enabled" @unless($t->enabled('utility.enabled')) hidden @endunless
     x-data="{ i: 0, n: 3, init() { setInterval(() => { this.i = (this.i + 1) % this.n; }, 4000); } }">
    <div class="sp-wrap">
        <div class="sp-utility-msgs">
            @foreach($utilityMessages as $i => $message)
                <span data-tpl-text="utility.items.{{ $i }}.text" data-tpl-hide-empty @if($message === '') hidden @endif :class="{ 'is-current': i === {{ $i }} }" @if($i === 0) class="is-current" @endif>{{ $message }}</span>
            @endforeach
        </div>
        <div class="sp-utility-links">
            @if($tplPreview || $t->enabled('utility.links'))
                <button type="button" data-tpl-show="utility.links" @unless($t->enabled('utility.links')) hidden @endunless @click="window.openCustomerModal ? window.openCustomerModal('orders') : $dispatch('open-customer-modal')">
                    <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="17.5" cy="17.5" r="1.5"/></svg>
                    {{ $isEn ? 'My orders' : 'Mis pedidos' }}
                </button>
                <a href="{{ route('store.contact', $store->slug) }}" data-tpl-show="utility.links" @unless($t->enabled('utility.links')) hidden @endunless>
                    <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>
                    {{ $isEn ? 'Contact us' : 'Contáctanos' }}
                </a>
            @endif
            @if($store->is_multilanguage_enabled)
                <div style="position: relative; height: 100%" x-data="{ open: false }">
                    <button type="button" style="height: 100%" @click="open = !open" @click.outside="open = false" :aria-expanded="open.toString()">{{ strtoupper($currentLang) }}</button>
                    <div class="sp-drop" x-show="open" x-cloak>
                        @foreach(['es' => 'Español', 'en' => 'English'] as $code => $label)
                            <button type="button" class="{{ $currentLang === $code ? 'is-active' : '' }}" @click="spSwitchLanguage('{{ $code }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div style="position: relative; height: 100%" x-data="{ open: false }">
                <button type="button" style="height: 100%" @click="open = !open" @click.outside="open = false" :aria-expanded="open.toString()" title="{{ $isEn ? 'Country / currency' : 'País / moneda' }}">
                    <img src="{{ $currentCountryInfo['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($currentCountry) }}" alt=""> {{ $currentCurrency }}
                </button>
                <div class="sp-drop" x-show="open" x-cloak>
                    @foreach($headerCountries as $code => $country)
                        <button type="button" class="{{ $currentCountry === $code ? 'is-active' : '' }}" @click="spSwitchCountry('{{ $code }}', '{{ $country['currency'] }}')">
                            <span style="display: inline-flex; align-items: center; gap: 8px"><img src="{{ $country['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($code) }}" alt=""> {{ $country['name'] }}</span>
                            <small>{{ $country['currency'] }}</small>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<header class="sp-header" data-tpl-choice="style.header" data-choice="{{ $t->choice('style.header') }}"
        x-data="{ menuOpen: false, searchOpen: false }" @keydown.escape.window="menuOpen = false; searchOpen = false">
    <div class="sp-wrap sp-header-row">
        <button type="button" class="sp-icon-btn sp-only-mobile" @click="menuOpen = true" aria-label="{{ $isEn ? 'Open menu' : 'Abrir menú' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>

        <a href="{{ route('store.show', $store->slug) }}" class="sp-logo" aria-label="{{ $store->name }} — {{ $isEn ? 'home' : 'inicio' }}">
            @if($store->logo_path)
                <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" data-tpl-choice="style.logo_mode" data-choice="{{ $t->choice('style.logo_mode') }}"
                     @if($logoIsTransparent) data-transparent @else data-opaque @endif>
            @else
                <span>{{ $store->name }}</span>
            @endif
        </a>

        <nav class="sp-nav" aria-label="{{ $isEn ? 'Main menu' : 'Menú principal' }}">
            @forelse($headerCategories as $cat)
                <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="{{ $activeCategory === $cat->slug ? 'is-active' : '' }}">{{ $cat->getTranslatedName() }}</a>
            @empty
                <a href="{{ route('store.catalog', $store->slug) }}" class="{{ request()->routeIs('store.catalog') && !request()->hasAny(['on_sale', 'sort']) ? 'is-active' : '' }}">{{ $isEn ? 'Shop' : 'Catálogo' }}</a>
                <a href="{{ route('store.catalog', ['slug' => $store->slug, 'sort' => 'newest']) }}">{{ $isEn ? 'New in' : 'Novedades' }}</a>
            @endforelse
            @if(($tplPreview || $t->enabled('menu.sale_enabled')) && $saleUrl)
                <a href="{{ $saleUrl }}" class="is-sale {{ request('on_sale') ? 'is-active' : '' }}" data-tpl-show="menu.sale_enabled" @unless($t->enabled('menu.sale_enabled')) hidden @endunless>
                    <span data-tpl-text="menu.sale_label">{{ $t->text('menu.sale_label') }}</span>
                </a>
            @endif
        </nav>

        <div class="sp-tools">
            <button type="button" class="sp-search-btn" @click="searchOpen = true">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                {{ $isEn ? 'Search' : 'Buscar' }}
            </button>
            <button type="button" class="sp-icon-btn sp-only-mobile" @click="searchOpen = true" aria-label="{{ $isEn ? 'Search' : 'Buscar' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
            </button>
            <button type="button" class="sp-icon-btn" onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer'))" aria-label="{{ $isEn ? 'Cart' : 'Carrito' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/></svg>
                <span class="sp-count" data-cart-count>0</span>
            </button>
            <button type="button" class="sp-icon-btn sp-hide-mobile" @click="$dispatch('open-customer-modal')" aria-label="{{ $isEn ? 'My account' : 'Mi cuenta' }}" title="{{ $isEn ? 'My account / My orders' : 'Mi cuenta / Mis pedidos' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7.5" r="3.5"/><path stroke-linejoin="round" d="M5 21v-2.5A4.5 4.5 0 0 1 9.5 14h5a4.5 4.5 0 0 1 4.5 4.5V21"/></svg>
            </button>
        </div>
    </div>

    {{-- Buscador --}}
    <template x-teleport="body">
        <div class="sp-search-panel" x-show="searchOpen" x-cloak role="dialog" aria-modal="true" aria-label="{{ $searchPlaceholder }}">
            <div class="sp-search-backdrop" @click="searchOpen = false"></div>
            <div class="sp-search-box">
                <div class="sp-narrow">
                    <form action="{{ route('store.catalog', $store->slug) }}" method="GET" role="search">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $searchPlaceholder }}…" aria-label="{{ $searchPlaceholder }}" autocomplete="off" x-effect="searchOpen && $nextTick(() => $el.focus())">
                        <button type="button" class="sp-icon-btn" @click="searchOpen = false" aria-label="{{ $isEn ? 'Close' : 'Cerrar' }}">
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </form>
                    @if($navCategories->isNotEmpty())
                        <div class="sp-search-hints">
                            <strong>{{ $isEn ? 'Popular' : 'Búsquedas populares' }}</strong>
                            @foreach($navCategories->take(8) as $cat)
                                <a class="sp-chip" href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}">{{ $cat->getTranslatedName() }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </template>

    {{-- Menú lateral (celular) --}}
    <template x-teleport="body">
        <div class="sp-drawer" x-show="menuOpen" x-cloak role="dialog" aria-modal="true" aria-label="{{ $isEn ? 'Menu' : 'Menú' }}">
            <div class="sp-drawer-backdrop" x-show="menuOpen" x-transition.opacity @click="menuOpen = false"></div>
            <div class="sp-drawer-panel" x-show="menuOpen"
                 x-transition:enter="sp-tr" x-transition:enter-start="sp-off-left" x-transition:enter-end="sp-on"
                 x-transition:leave="sp-tr" x-transition:leave-start="sp-on" x-transition:leave-end="sp-off-left">
                <div class="sp-drawer-head">
                    <strong>{{ $isEn ? 'Menu' : 'Menú' }}</strong>
                    <button type="button" class="sp-icon-btn" @click="menuOpen = false" aria-label="{{ $isEn ? 'Close menu' : 'Cerrar menú' }}">
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <div class="sp-drawer-body">
                    <nav class="sp-drawer-links" aria-label="{{ $isEn ? 'Categories' : 'Categorías' }}">
                        @foreach($headerCategories as $cat)
                            <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}">{{ $cat->getTranslatedName() }} <span aria-hidden="true">›</span></a>
                        @endforeach
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'sort' => 'newest']) }}">{{ $isEn ? 'New in' : 'Novedades' }} <span aria-hidden="true">›</span></a>
                        @if($t->enabled('menu.sale_enabled') && $saleUrl)
                            <a href="{{ $saleUrl }}" class="is-sale">{{ $t->text('menu.sale_label') }} <span aria-hidden="true">›</span></a>
                        @endif
                    </nav>
                    <nav class="sp-drawer-links is-small" style="margin-top: 12px">
                        <a href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'Full catalog' : 'Todo el catálogo' }}</a>
                        <button type="button" @click="$dispatch('open-customer-modal'); menuOpen = false">{{ $isEn ? 'My account / My orders' : 'Mi cuenta / Mis pedidos' }}</button>
                        <a href="{{ route('store.contact', $store->slug) }}">{{ $isEn ? 'Contact us' : 'Contáctanos' }}</a>
                        <a href="{{ route('store.contact', ['slug' => $store->slug, 'libro' => 1]) }}">{{ $isEn ? 'Complaints book' : 'Libro de Reclamaciones' }}</a>
                    </nav>
                    <div class="sp-drawer-foot">
                        @if($store->is_multilanguage_enabled)
                            <div>
                                <span>{{ $isEn ? 'Language' : 'Idioma' }}</span>
                                <span class="sp-seg">
                                    <button type="button" class="{{ !$isEn ? 'is-active' : '' }}" onclick="spSwitchLanguage('es')">ES</button>
                                    <button type="button" class="{{ $isEn ? 'is-active' : '' }}" onclick="spSwitchLanguage('en')">EN</button>
                                </span>
                            </div>
                        @endif
                        <div>
                            <label for="sp-country">{{ $isEn ? 'Country / currency' : 'País / moneda' }}</label>
                            <select id="sp-country" onchange="const [c, cur] = this.value.split(':'); spSwitchCountry(c, cur);">
                                @foreach($headerCountries as $code => $country)
                                    <option value="{{ $code }}:{{ $country['currency'] }}" @selected($currentCountry === $code)>{{ $country['name'] }} · {{ $country['currency'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</header>

@if($tplPreview || $t->enabled('shipping.enabled'))
    <{{ $shippingUrl ? 'a' : 'div' }} class="sp-shipbar" @if($shippingUrl) href="{{ $shippingUrl }}" @endif data-tpl-show="shipping.enabled" @unless($t->enabled('shipping.enabled')) hidden @endunless>
        <span data-tpl-text="shipping.text">{{ $t->text('shipping.text') }}</span>
    </{{ $shippingUrl ? 'a' : 'div' }}>
@endif

@if($countryFixedTo)
    <script>document.cookie = 'user_country={{ $countryFixedTo }}; path=/; max-age=31536000; SameSite=Lax';</script>
@endif

@once
<script>
    // Borra copias viejas de una cookie en la ruta actual/raíz y en cada dominio padre.
    function spExpireCookie(name) {
        const parts = window.location.hostname.split('.');
        const paths = ['/', window.location.pathname.replace(/\/[^/]*$/, '') || '/', window.location.pathname];
        paths.forEach((path) => {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=' + path;
            for (let i = 0; i < parts.length - 1; i++) {
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=' + path + '; domain=.' + parts.slice(i).join('.');
            }
        });
    }
    function spSwitchCountry(country, currency) {
        spExpireCookie('user_country');
        spExpireCookie('store_currency');
        document.cookie = 'user_country=' + country + '; path=/; max-age=31536000; SameSite=Lax';
        document.cookie = 'store_currency=' + currency + '; path=/; max-age=31536000; SameSite=Lax';
        if (window.TribioCart && window.TribioCart.items && window.TribioCart.items.length > 0) window.TribioCart.clear();
        window.location.reload();
    }
    function spSwitchLanguage(lang) {
        document.cookie = 'store_lang=' + lang + '; path=/; max-age=31536000; SameSite=Lax';
        if (window.spClearGoogTrans) window.spClearGoogTrans();
        if (lang === 'en') document.cookie = 'googtrans=/es/en; path=/; max-age=31536000; SameSite=Lax';
        window.location.reload();
    }
</script>
@endonce
