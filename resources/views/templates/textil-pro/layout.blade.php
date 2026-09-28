<!DOCTYPE html>
<html lang="{{ \App\Helpers\TranslationHelper::currentLang() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $store->name . ' - Tienda Online')</title>
    @if($store->logo_path)
        <link rel="icon" type="image/png" href="{{ $store->logo_url }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif
    <!-- Barlow para textos y Barlow Condensed para títulos (si se elige otra fuente, _theme la agrega) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700;800&display=swap" rel="stylesheet">
    @stack('preload')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .goog-te-banner-frame.skiptranslate, .goog-te-gadget-icon { display: none !important; }
        body { top: 0px !important; }
        #goog-gt-tt, .goog-te-balloon-frame { display: none !important; }
        .goog-text-highlight { background: none !important; box-shadow: none !important; }
    </style>
    @include('templates.textil-pro._theme')
    @include('components.marketing.head')
</head>
<body class="tx-body" data-tpl-choice="style.corners" data-choice="{{ $storefrontTheme->choice('style.corners') }}">
    @include('templates.textil-pro._translate')

    @include('templates.textil-pro.header')

    <main class="tx-main" data-tpl-choice="style.product_image" data-choice="{{ $storefrontTheme->choice('style.product_image') }}">
        @yield('content')
    </main>

    @include('templates.textil-pro.footer')

    {{-- Pasarela de pago estándar: carrito + cuenta Tribio Pass (una sola vez por página) --}}
    @include('components.checkout.gateway')

    @include('templates.textil-pro._signup')

    {{-- Primera visita: país/moneda para mostrar precios y envíos correctos --}}
    @if(!request()->hasCookie('user_country') && !($templatePreview ?? false))
        @php
            $modalCountries = $store->getEnabledCountriesWithDetails();
            if (empty($modalCountries)) {
                $modalCountries = [
                    'PE' => \App\Helpers\CurrencyHelper::getCountryInfo('PE'),
                    'US' => \App\Helpers\CurrencyHelper::getCountryInfo('US'),
                ];
            }
        @endphp
        <div class="tx-modal" x-data="{ open: true }" x-show="open" role="dialog" aria-modal="true" aria-labelledby="tx-country-title">
            <div class="tx-modal-card" style="text-align: center">
                <h2 id="tx-country-title">{{ \App\Helpers\TranslationHelper::isEn() ? 'Where are you shopping from?' : '¿Desde dónde compras?' }}</h2>
                <p style="margin: -6px 0 20px; color: var(--tx-muted)">{{ \App\Helpers\TranslationHelper::isEn() ? 'We will show prices and shipping in your currency.' : 'Te mostraremos precios y envíos en tu moneda.' }}</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px">
                    @foreach($modalCountries as $code => $c)
                        <button type="button" class="tx-swatch" style="justify-content: center; padding: 12px" onclick="txSwitchCountry('{{ $code }}', '{{ $c['currency'] }}')">
                            <img src="{{ $c['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($code) }}" alt="" style="width: 26px; height: 18px">
                            {{ $c['name'] }} · {{ $c['currency'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @stack('scripts')
</body>
</html>
