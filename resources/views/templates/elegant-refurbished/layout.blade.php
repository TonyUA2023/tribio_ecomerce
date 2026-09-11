<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- SEO Meta Tags -->
    <title>@yield('title', 'iPhones Reacondicionados Premium') | {{ $store->name ?? 'Tienda' }}</title>
    <meta name="description" content="@yield('meta_description', 'Encuentra los mejores iPhones reacondicionados. Como nuevos, precio inteligente. Compra con garantía y confianza.')">
    
    <!-- Dynamic Keywords passed from the view or controller -->
    <meta name="keywords" content="@yield('meta_keywords', 'iphone 13 segunda mano precio, iphone 13 usado precio perú, iphone 15 reacondicionado perú, iphone reacondicionado saga falabella, donde comprar iphone barato en perú')">
    
    <link rel="canonical" href="{{ url()->current() }}">
    
    <!-- Open Graph for Social Media -->
    <meta property="og:title" content="@yield('title', 'iPhones Reacondicionados Premium')">
    <meta property="og:description" content="@yield('meta_description', 'Encuentra los mejores iPhones reacondicionados. Como nuevos, precio inteligente.')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('meta_image')
    <meta property="og:image" content="@yield('meta_image')">
    @endif

    <!-- Schema.org for Local Business / Store -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@type": "Store",
      "name": "{{ $store->name ?? 'Tienda de Reacondicionados' }}",
      "image": "{{ asset('img/logo.png') }}",
      "description": "Venta de iPhones reacondicionados de alta calidad en Perú.",
      "telephone": "",
      "address": {
        "@type": "PostalAddress",
        "addressCountry": "PE"
      }
    }
    </script>

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    
    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('css/templates/elegant-refurbished/style.css') }}">
    
    @yield('extra_head')
</head>
<body class="er-body-dark">
    <!-- Header -->
    <header class="er-header">
        <div class="er-container er-nav">
            <a href="{{ route('store.show', ['slug' => $store->slug]) }}" class="er-logo">
                <span>i</span>Stack
            </a>
            <ul class="er-nav-links">
                <li><a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => 'iphone']) }}">iPhones</a></li>
                <li><a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => 'mac']) }}">Macs</a></li>
                <li><a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => 'ipad']) }}">iPads</a></li>
                <li><a href="{{ route('store.catalog', ['slug' => $store->slug]) }}">Catálogo Completo</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="er-footer" style="padding: 2rem 0; text-align: center; border-top: 1px solid var(--border-color);">
        <div class="er-container">
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                Impulsado por <strong style="color: var(--text-color);">Tribio</strong>
            </p>
        </div>
    </footer>

    <!-- Swiper JS -->
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.animate-on-scroll').forEach(element => {
                observer.observe(element);
            });
        });
    </script>
    @yield('extra_scripts')
</body>
</html>
