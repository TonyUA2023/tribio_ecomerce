{{-- Street Kicks header: franja de mensajes, menú negro (o blanco) con buscador de caja, franja de
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
    $utilityMessages = collect([0, 1, 2, 3])->map(fn ($i) => $t->text("utility.items.{$i}.text"));
    $shippingUrl = $t->link('shipping.link');
    $saleUrl = $t->link('menu.sale_link');
    $searchPlaceholder = $isEn ? 'Search products' : 'Buscar productos';
    $activeCategory = request()->routeIs('store.catalog') ? (string) request('category') : '';
@endphp

{{-- Cinta de mensajes en movimiento (arriba de todo) --}}
@if($tplPreview || $t->enabled('utility.enabled'))
<div class="sk-utility" data-tpl-show="utility.enabled" @unless($t->enabled('utility.enabled')) hidden @endunless>
    <div class="sk-wrap">
        {{-- Cinta en movimiento: los mensajes se repiten para que el recorrido sea continuo. Solo la primera
             copia lleva data-tpl-* (vista previa en vivo); el resto es decoración para lectores de pantalla. --}}
        <div class="sk-marquee" aria-label="{{ $isEn ? 'Store announcements' : 'Anuncios de la tienda' }}">
            <div class="sk-marquee-track">
                @foreach([0, 1, 2] as $copy)
                    <div class="sk-marquee-set" @if($copy > 0) aria-hidden="true" @endif>
                        @foreach($utilityMessages as $i => $message)
                            <span @if($copy === 0) data-tpl-text="utility.items.{{ $i }}.text" data-tpl-hide-empty @endif @if($message === '') hidden @endif>{{ $message }}</span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <div class="sk-utility-links">
            @if(true)
                <button type="button" @click="window.openCustomerModal ? window.openCustomerModal('orders') : $dispatch('open-customer-modal')">
                    <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="17.5" cy="17.5" r="1.5"/></svg>
                    {{ $isEn ? 'My orders' : 'Mis pedidos' }}
                </button>
                <a href="{{ route('store.contact', $store->slug) }}">
                    <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>
                    {{ $isEn ? 'Contact us' : 'Contáctanos' }}
                </a>
            @endif
            @if($store->is_multilanguage_enabled)
                <div style="position: relative; height: 100%" x-data="{ open: false }">
                    <button type="button" style="height: 100%" @click="open = !open" @click.outside="open = false" :aria-expanded="open.toString()">{{ strtoupper($currentLang) }}</button>
                    <div class="sk-drop" x-show="open" x-cloak>
                        @foreach(['es' => 'Español', 'en' => 'English'] as $code => $label)
                            <button type="button" class="{{ $currentLang === $code ? 'is-active' : '' }}" @click="skSwitchLanguage('{{ $code }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @endif
            <div style="position: relative; height: 100%" x-data="{ open: false }">
                <button type="button" style="height: 100%" @click="open = !open" @click.outside="open = false" :aria-expanded="open.toString()" title="{{ $isEn ? 'Country / currency' : 'País / moneda' }}">
                    <img src="{{ $currentCountryInfo['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($currentCountry) }}" alt=""> {{ $currentCurrency }}
                </button>
                <div class="sk-drop" x-show="open" x-cloak>
                    @foreach($headerCountries as $code => $country)
                        <button type="button" class="{{ $currentCountry === $code ? 'is-active' : '' }}" @click="skSwitchCountry('{{ $code }}', '{{ $country['currency'] }}')">
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

<header class="sk-header" data-tpl-choice="style.header" data-choice="{{ $t->choice('style.header') }}"
        x-data="{ menuOpen: false, searchOpen: false }" @keydown.escape.window="menuOpen = false; searchOpen = false">
    <div class="sk-wrap sk-header-row">
        <button type="button" class="sk-icon-btn sk-only-mobile" @click="menuOpen = true" aria-label="{{ $isEn ? 'Open menu' : 'Abrir menú' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>

        <a href="{{ route('store.show', $store->slug) }}" class="sk-logo" aria-label="{{ $store->name }} — {{ $isEn ? 'home' : 'inicio' }}">
            @if($store->logo_path)
                <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" data-tpl-choice="style.logo_mode" data-choice="{{ $t->choice('style.logo_mode') }}"
                     @if($logoIsTransparent) data-transparent @else data-opaque @endif>
            @else
                <span>{{ $store->name }}</span>
            @endif
        </a>

        <nav class="sk-nav" aria-label="{{ $isEn ? 'Main menu' : 'Menú principal' }}">
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

        <div class="sk-tools">
            <button type="button" class="sk-search-btn" @click="searchOpen = true">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                {{ $isEn ? 'Search' : 'Buscar' }}
            </button>
            <button type="button" class="sk-icon-btn sk-only-mobile" @click="searchOpen = true" aria-label="{{ $isEn ? 'Search' : 'Buscar' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
            </button>
            <button type="button" class="sk-icon-btn" onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer'))" aria-label="{{ $isEn ? 'Cart' : 'Carrito' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/></svg>
                <span class="sk-count" data-cart-count>0</span>
            </button>
            <button type="button" class="sk-icon-btn sk-hide-mobile" @click="$dispatch('open-customer-modal')" aria-label="{{ $isEn ? 'My account' : 'Mi cuenta' }}" title="{{ $isEn ? 'My account / My orders' : 'Mi cuenta / Mis pedidos' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7.5" r="3.5"/><path stroke-linejoin="round" d="M5 21v-2.5A4.5 4.5 0 0 1 9.5 14h5a4.5 4.5 0 0 1 4.5 4.5V21"/></svg>
            </button>
        </div>
    </div>

    {{-- Buscador --}}
    <template x-teleport="body">
        <div class="sk-search-panel" x-show="searchOpen" x-cloak role="dialog" aria-modal="true" aria-label="{{ $searchPlaceholder }}">
            <div class="sk-search-backdrop" @click="searchOpen = false"></div>
            <div class="sk-search-box">
                <div class="sk-narrow">
                    <form action="{{ route('store.catalog', $store->slug) }}" method="GET" role="search">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg>
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $searchPlaceholder }}…" aria-label="{{ $searchPlaceholder }}" autocomplete="off" x-effect="searchOpen && $nextTick(() => $el.focus())">
                        <button type="button" class="sk-icon-btn" @click="searchOpen = false" aria-label="{{ $isEn ? 'Close' : 'Cerrar' }}">
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </form>
                    @if($navCategories->isNotEmpty())
                        <div class="sk-search-hints">
                            <strong>{{ $isEn ? 'Popular' : 'Búsquedas populares' }}</strong>
                            @foreach($navCategories->take(8) as $cat)
                                <a class="sk-chip" href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}">{{ $cat->getTranslatedName() }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </template>

    {{-- Menú lateral (celular) --}}
    <template x-teleport="body">
        <div class="sk-drawer" x-show="menuOpen" x-cloak role="dialog" aria-modal="true" aria-label="{{ $isEn ? 'Menu' : 'Menú' }}">
            <div class="sk-drawer-backdrop" x-show="menuOpen" x-transition.opacity @click="menuOpen = false"></div>
            <div class="sk-drawer-panel" x-show="menuOpen"
                 x-transition:enter="sk-tr" x-transition:enter-start="sk-off-left" x-transition:enter-end="sk-on"
                 x-transition:leave="sk-tr" x-transition:leave-start="sk-on" x-transition:leave-end="sk-off-left">
                <div class="sk-drawer-head">
                    <strong>{{ $isEn ? 'Menu' : 'Menú' }}</strong>
                    <button type="button" class="sk-icon-btn" @click="menuOpen = false" aria-label="{{ $isEn ? 'Close menu' : 'Cerrar menú' }}">
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <div class="sk-drawer-body">
                    <nav class="sk-drawer-links" aria-label="{{ $isEn ? 'Categories' : 'Categorías' }}">
                        @foreach($headerCategories as $cat)
                            <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}">{{ $cat->getTranslatedName() }} <span aria-hidden="true">›</span></a>
                        @endforeach
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'sort' => 'newest']) }}">{{ $isEn ? 'New in' : 'Novedades' }} <span aria-hidden="true">›</span></a>
                        @if($t->enabled('menu.sale_enabled') && $saleUrl)
                            <a href="{{ $saleUrl }}" class="is-sale">{{ $t->text('menu.sale_label') }} <span aria-hidden="true">›</span></a>
                        @endif
                    </nav>
                    <nav class="sk-drawer-links is-small" style="margin-top: 12px">
                        <a href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'Full catalog' : 'Todo el catálogo' }}</a>
                        <button type="button" @click="$dispatch('open-customer-modal'); menuOpen = false">{{ $isEn ? 'My account / My orders' : 'Mi cuenta / Mis pedidos' }}</button>
                        <a href="{{ route('store.contact', $store->slug) }}">{{ $isEn ? 'Contact us' : 'Contáctanos' }}</a>
                        <a href="{{ route('store.contact', ['slug' => $store->slug, 'libro' => 1]) }}">{{ $isEn ? 'Complaints book' : 'Libro de Reclamaciones' }}</a>
                    </nav>
                    <div class="sk-drawer-foot">
                        @if($store->is_multilanguage_enabled)
                            <div>
                                <span>{{ $isEn ? 'Language' : 'Idioma' }}</span>
                                <span class="sk-seg">
                                    <button type="button" class="{{ !$isEn ? 'is-active' : '' }}" onclick="skSwitchLanguage('es')">ES</button>
                                    <button type="button" class="{{ $isEn ? 'is-active' : '' }}" onclick="skSwitchLanguage('en')">EN</button>
                                </span>
                            </div>
                        @endif
                        <div>
                            <label for="sk-country">{{ $isEn ? 'Country / currency' : 'País / moneda' }}</label>
                            <select id="sk-country" onchange="const [c, cur] = this.value.split(':'); skSwitchCountry(c, cur);">
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
    <{{ $shippingUrl ? 'a' : 'div' }} class="sk-shipbar" @if($shippingUrl) href="{{ $shippingUrl }}" @endif data-tpl-show="shipping.enabled" @unless($t->enabled('shipping.enabled')) hidden @endunless>
        <span data-tpl-text="shipping.text">{{ $t->text('shipping.text') }}</span>
    </{{ $shippingUrl ? 'a' : 'div' }}>
@endif

@if($countryFixedTo)
    <script>document.cookie = 'user_country={{ $countryFixedTo }}; path=/; max-age=31536000; SameSite=Lax';</script>
@endif

@once
<script>
    // Borra copias viejas de una cookie en la ruta actual/raíz y en cada dominio padre.
    function skExpireCookie(name) {
        const parts = window.location.hostname.split('.');
        const paths = ['/', window.location.pathname.replace(/\/[^/]*$/, '') || '/', window.location.pathname];
        paths.forEach((path) => {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=' + path;
            for (let i = 0; i < parts.length - 1; i++) {
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=' + path + '; domain=.' + parts.slice(i).join('.');
            }
        });
    }
    function skSwitchCountry(country, currency) {
        skExpireCookie('user_country');
        skExpireCookie('store_currency');
        document.cookie = 'user_country=' + country + '; path=/; max-age=31536000; SameSite=Lax';
        document.cookie = 'store_currency=' + currency + '; path=/; max-age=31536000; SameSite=Lax';
        if (window.TribioCart && window.TribioCart.items && window.TribioCart.items.length > 0) window.TribioCart.clear();
        window.location.reload();
    }
    function skSwitchLanguage(lang) {
        document.cookie = 'store_lang=' + lang + '; path=/; max-age=31536000; SameSite=Lax';
        if (window.skClearGoogTrans) window.skClearGoogTrans();
        if (lang === 'en') document.cookie = 'googtrans=/es/en; path=/; max-age=31536000; SameSite=Lax';
        window.location.reload();
    }
</script>
@endonce
