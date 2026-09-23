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

    <!-- Pasarela de pago estándar: requiere Alpine.js + Tailwind (app.css) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @yield('extra_head')
@include('components.marketing.head')
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
            <button onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer'))" aria-label="Carrito"
                    style="position:relative; background:transparent; border:none; cursor:pointer; color:inherit; padding:.5rem;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:22px;height:22px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                <span data-cart-count style="position:absolute; top:-2px; right:-2px; background:{{ $store->accent_color ?: '#0071e3' }}; color:#fff; font-size:10px; font-weight:700; min-width:16px; padding:1px 4px; border-radius:9999px; text-align:center;">0</span>
            </button>
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

    {{-- Pasarela de pago estándar (carrito + Tribio Pass) --}}
    @include('components.checkout.gateway')
</body>
</html>
