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
    <!-- Fonts: Fredoka (Maetek Brand Font), Quicksand & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            /* Maetek Pastel Design System */
            --bg-cream: #FAF7F2;
            --bg-sage: #EAF1E4;
            --pastel-sage: #C8D7BA;
            --pastel-sage-light: #F0F5EC;
            --pastel-sage-dark: #4A6038;
            --pastel-peach: #C8D7BA;
            --pastel-peach-light: #F0F5EC;
            --pastel-peach-dark: #4A6038;
            --pastel-sand: #EFE3D0;
            --pastel-sand-light: #F7F3EB;
            --pastel-sand-dark: #7A6245;
            --pastel-greige: #E8E7E1;
            --pastel-greige-light: #F4F3F0;
            --pastel-caramel: #7DA268;
            --pastel-caramel-dark: #4A6038;

            --accent: {{ $store->accent_color ?? '#1E1D1B' }};
            --secondary: {{ $store->secondary_color ?? '#7DA268' }};
            --bg: #FAF7F2;
            --text-dark: #1E1D1B;
            --text-muted: #6E6A63;
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
        
        .font-brand { font-family: var(--font-brand) !important; }
        
        /* Pastel utility classes */
        .bg-pastel-sage { background-color: var(--pastel-sage); }
        .bg-pastel-sage-light { background-color: var(--pastel-sage-light); }
        .bg-pastel-peach { background-color: var(--pastel-peach); }
        .bg-pastel-peach-light { background-color: var(--pastel-peach-light); }
        .bg-pastel-sand { background-color: var(--pastel-sand); }
        .bg-pastel-sand-light { background-color: var(--pastel-sand-light); }
        .bg-pastel-greige { background-color: var(--pastel-greige); }
        .bg-pastel-greige-light { background-color: var(--pastel-greige-light); }
        .bg-pastel-cream { background-color: var(--bg-cream); }
        
        .text-pastel-sage-dark { color: var(--pastel-sage-dark); }
        .text-pastel-peach-dark { color: var(--pastel-peach-dark); }
        .text-pastel-sand-dark { color: var(--pastel-sand-dark); }
        
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
@include('components.marketing.head')
</head>
<body class="antialiased relative bg-[#FAF7F2] text-[#1E1D1B]">
    
    <!-- Google Translate Script -->
    <div id="google_translate_element" style="display:none;"></div>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({pageLanguage: 'es', includedLanguages: 'en,es', autoDisplay: false}, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    @yield('content')

    {{-- Pasarela de pago estándar (Tribio Pass) --}}
    @include('components.checkout.customer-modal')

</body>
</html>
