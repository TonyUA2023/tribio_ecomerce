<!DOCTYPE html>
<html lang="{{ \App\Helpers\TranslationHelper::currentLang() }}">
<head>
    <meta charset="UTF-8">
    <script>document.documentElement.classList.add('js-anim');</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $store->name . ' - Tienda Online')</title>
    @if(isset($store) && $store->logo_path)
        <link rel="icon" type="image/png" href="{{ $store->logo_url }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif
    <!-- Fuente base: Poppins (la fuente de títulos elegida se agrega en _theme) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --text-dark: #111111;
            --text-muted: #6B6B6B;
            --font-sans: 'Poppins', sans-serif;
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
    @include('templates.urban-style._theme')
@include('components.marketing.head')
</head>
<body class="antialiased relative bg-[var(--t-bg)] us-body">
    
    @include('templates.urban-style._translate')

    @yield('content')

    {{-- Pasarela de pago estándar (Tribio Pass) --}}
    @include('components.checkout.customer-modal')

</body>
</html>
