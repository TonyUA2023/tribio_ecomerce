<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $store->name . ' - Tienda Online')</title>
    <!-- Fonts: Playfair Display (Serif) & Plus Jakarta Sans (Sans-serif) -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --accent: {{ $store->accent_color ?? '#1A1A1A' }};
            --secondary: {{ $store->secondary_color ?? '#C8A68B' }};
            --bg: #FDF8EF;
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

    @yield('content')

</body>
</html>
