@extends('templates.elegant-refurbished.layout')

@section('title', 'iStack | Equipos Apple Reacondicionados')
@section('meta_description', 'Descubre nuestra selección de iPhones, Macs y iPads reacondicionados. Calidad premium, garantía asegurada y precios inteligentes en iStack.')

@section('content')
    @if(isset($sections) && $sections->isNotEmpty())
        @foreach($sections as $section)
            @if(View::exists("components.store-sections.{$section->type}"))
                <div class="tribio-section-wrapper relative group" data-section-id="{{ $section->id }}" id="section-{{ $section->id }}">
                    <div class="tribio-section-content">
                        @include("components.store-sections.{$section->type}", ['data' => $section->data])
                    </div>
                </div>
            @endif
        @endforeach
        
        @if(request()->query('editor'))
            @include('components.store-sections.editor-scripts')
        @endif
    @else
<!-- Hero Slider Section -->
<section class="er-hero swiper" id="hero-slider">
    <div class="swiper-wrapper">
        <!-- Slide 1: iPhone 15 Pro -->
        <div class="swiper-slide" style="display: flex; align-items: center; overflow: hidden;">
            <div class="er-container er-hero-content" style="flex: 1; z-index: 10; text-align: left; padding-left: 5%;">
                <h1 class="er-title-xl" style="margin-bottom: 0;">
                    iPhone 15 Pro
                </h1>
                <h2 class="er-subtitle" style="font-size: 2rem; color: var(--text-main); margin-bottom: var(--space-md); font-weight: 500;">
                    Titanio. Tan robusto como ligero.
                </h2>
                <div style="display: flex; gap: var(--space-sm);">
                    <a href="#catalogo" class="er-btn er-btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2.5rem;">
                        Comprar
                    </a>
                </div>
            </div>
            <div style="flex: 1; display: flex; justify-content: center; align-items: center; z-index: 10;">
                <img src="{{ asset('img/hero-iphone.png') }}" alt="iPhone 15 Pro" style="max-height: 500px; max-width: 100%; object-fit: contain; filter: drop-shadow(0 20px 40px rgba(0,0,0,0.15)); transform: scale(1.1) translateX(-20px);">
            </div>
            <!-- Dynamic glow effect in background -->
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60vw; height: 60vw; background: radial-gradient(circle, rgba(0, 102, 204, 0.08) 0%, rgba(255,255,255,0) 70%); z-index: 0; pointer-events: none;"></div>
        </div>
        <!-- Slide 2: MacBook Air M2 -->
        <div class="swiper-slide" style="display: flex; align-items: center; overflow: hidden;">
            <div class="er-container er-hero-content" style="flex: 1; z-index: 10; text-align: left; padding-left: 5%;">
                <h1 class="er-title-xl" style="margin-bottom: 0; background: linear-gradient(135deg, #1D1D1F 0%, #0066CC 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    MacBook Air M2
                </h1>
                <h2 class="er-subtitle" style="font-size: 2rem; color: var(--text-main); margin-bottom: var(--space-md); font-weight: 500;">
                    Súper ligero. Súper chip.
                </h2>
                <div style="display: flex; gap: var(--space-sm);">
                    <a href="#catalogo" class="er-btn er-btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2.5rem;">
                        Descubrir
                    </a>
                </div>
            </div>
            <div style="flex: 1; display: flex; justify-content: center; align-items: center; z-index: 10;">
                <img src="{{ asset('img/hero-mac.png') }}" alt="MacBook Air" style="max-height: 450px; max-width: 100%; object-fit: contain; filter: drop-shadow(0 20px 40px rgba(0,0,0,0.15)); transform: scale(1.1) translateX(-20px);">
            </div>
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60vw; height: 60vw; background: radial-gradient(circle, rgba(0, 102, 204, 0.05) 0%, rgba(255,255,255,0) 70%); z-index: 0; pointer-events: none;"></div>
        </div>
        <!-- Slide 3: iPad Pro -->
        <div class="swiper-slide" style="display: flex; align-items: center; overflow: hidden;">
            <div class="er-container er-hero-content" style="flex: 1; z-index: 10; text-align: left; padding-left: 5%;">
                <h1 class="er-title-xl" style="margin-bottom: 0; background: linear-gradient(135deg, #1D1D1F 0%, #86868B 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    iPad Pro
                </h1>
                <h2 class="er-subtitle" style="font-size: 2rem; color: var(--text-main); margin-bottom: var(--space-md); font-weight: 500;">
                    El poder definitivo en tus manos.
                </h2>
                <div style="display: flex; gap: var(--space-sm);">
                    <a href="#catalogo" class="er-btn er-btn-primary" style="font-size: 1.1rem; padding: 0.8rem 2.5rem;">
                        Ver Ofertas
                    </a>
                </div>
            </div>
            <div style="flex: 1; display: flex; justify-content: center; align-items: center; z-index: 10;">
                <img src="{{ asset('img/hero-ipad.png') }}" alt="iPad Pro" style="max-height: 480px; max-width: 100%; object-fit: contain; filter: drop-shadow(0 20px 40px rgba(0,0,0,0.15)); transform: scale(1.1) translateX(-20px);">
            </div>
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60vw; height: 60vw; background: radial-gradient(circle, rgba(0, 0, 0, 0.03) 0%, rgba(255,255,255,0) 70%); z-index: 0; pointer-events: none;"></div>
        </div>
    </div>
    <!-- Add Pagination -->
    <div class="swiper-pagination"></div>
    <!-- Add Navigation -->
    <div class="swiper-button-next"></div>
    <div class="swiper-button-prev"></div>
</section>
<!-- Info Bar -->
<div class="er-container">
    <div class="er-info-bar">
        <div class="er-info-grid">
            <div class="er-info-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                </svg>
                1 año de garantía
            </div>
            <div class="er-info-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                </svg>
                Mayores importadores del Perú
            </div>
            <div class="er-info-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
                Los mejores precios
            </div>
            <div class="er-info-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                </svg>
                Envíos gratis
            </div>
        </div>
    </div>
</div>

<!-- Initialize Swiper in script later -->

<!-- Trust / Features Section -->
<section class="er-section" style="background-color: var(--bg-secondary);">
    <div class="er-container er-grid er-grid-3 animate-on-scroll" style="text-align: center;">
        <div style="padding: var(--space-sm);">
            <div style="color: var(--er-accent); margin-bottom: var(--space-xs); display: flex; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 3rem; height: 3rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 10.5h.375c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125H21M4.5 10.5h6.75V15H4.5v-4.5zM3 8.25h15c.828 0 1.5.672 1.5 1.5v4.5c0 .828-.672 1.5-1.5 1.5H3c-.828 0-1.5-.672-1.5-1.5v-4.5c0-.828.672-1.5 1.5-1.5z" />
                </svg>
            </div>
            <h3 class="er-title-md" style="color: var(--text-main);">Batería Óptima</h3>
            <p class="er-subtitle" style="font-size: 1rem; margin-top: 0.5rem;">Garantizamos una capacidad de batería superior al 85% para que tu dispositivo rinda al máximo.</p>
        </div>
        <div style="padding: var(--space-sm);">
            <div style="color: var(--er-accent); margin-bottom: var(--space-xs); display: flex; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 3rem; height: 3rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
            <h3 class="er-title-md" style="color: var(--text-main);">Garantía Total</h3>
            <p class="er-subtitle" style="font-size: 1rem; margin-top: 0.5rem;">Cuentas con 12 meses de garantía directa con iStack ante cualquier fallo de fábrica.</p>
        </div>
        <div style="padding: var(--space-sm);">
            <div style="color: var(--er-accent); margin-bottom: var(--space-xs); display: flex; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 3rem; height: 3rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 019 9v.375M10.125 2.25A3.375 3.375 0 0113.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 013.375 3.375M9 15l2.25 2.25L15 12" />
                </svg>
            </div>
            <h3 class="er-title-md" style="color: var(--text-main);">Revisión de 40 Puntos</h3>
            <p class="er-subtitle" style="font-size: 1rem; margin-top: 0.5rem;">Nuestros técnicos certificados evalúan cada detalle antes de ofrecerlo en nuestra tienda.</p>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section id="catalogo" class="er-section">
    <div class="er-container">
        <h2 class="er-title-lg animate-on-scroll" style="text-align: center; margin-bottom: var(--space-lg); color: var(--text-main);">⬇️ Conoce nuestra selección HOT SALE 🔥 del momento ⬇️</h2>
        
        <div class="er-grid er-grid-3">
            @forelse($featured_products ?? [] as $index => $product)
                <a href="{{ route('store.product', ['slug' => $store->slug, 'product' => $product->slug]) }}" class="er-product-card animate-on-scroll" style="animation-delay: {{ $index * 0.1 }}s;">
                    <img src="{{ $product->image_url ?? asset('img/dummy-iphone.png') }}" alt="{{ $product->name }}" class="er-product-img">
                    <h3 class="er-title-md" style="margin-bottom: 0.5rem; color: var(--text-main); font-size: 1.2rem;">{{ $product->name }}</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Desde</p>
                    <div class="er-price-new">S/ {{ number_format($product->price, 2) }}</div>
                    <div class="er-price-old">S/ {{ number_format($product->price * 1.2, 2) }}</div>
                    <span class="er-btn er-btn-primary" style="width: 100%; margin-top: 1rem;">Lo Quiero</span>
                </a>
            @empty
                <!-- Fallback Products if DB query is empty -->
                <a href="#" class="er-product-card animate-on-scroll">
                    <img src="https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-15-pro-finish-select-202309-6-1inch-bluetitanium?wid=5120&hei=2880&fmt=p-jpg&qlt=80&.v=1692846360609" alt="iPhone 15 Pro" class="er-product-img">
                    <h3 class="er-title-md" style="margin-bottom: 0.5rem; color: var(--text-main); font-size: 1.2rem;">iPhone 15 Pro</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Desde</p>
                    <div class="er-price-new">S/ 3,999.00</div>
                    <div class="er-price-old">S/ 4,599.00</div>
                    <span class="er-btn er-btn-primary" style="width: 100%; margin-top: 1rem;">Lo Quiero</span>
                </a>
                
                <a href="#" class="er-product-card animate-on-scroll" style="transition-delay: 0.1s;">
                    <img src="https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/macbook-air-midnight-select-20220606?wid=904&hei=840&fmt=jpeg&qlt=90&.v=1653084303665" alt="MacBook Air M2" class="er-product-img">
                    <h3 class="er-title-md" style="margin-bottom: 0.5rem; color: var(--text-main); font-size: 1.2rem;">MacBook Air M2</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Desde</p>
                    <div class="er-price-new">S/ 4,299.00</div>
                    <div class="er-price-old">S/ 4,999.00</div>
                    <span class="er-btn er-btn-primary" style="width: 100%; margin-top: 1rem;">Lo Quiero</span>
                </a>
                
                <a href="#" class="er-product-card animate-on-scroll" style="transition-delay: 0.2s;">
                    <img src="https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/ipad-air-storage-select-202207-blue-wifi_FMT_WHH?wid=1280&hei=720&fmt=jpeg&qlt=90&.v=1670856064287" alt="iPad Air" class="er-product-img">
                    <h3 class="er-title-md" style="margin-bottom: 0.5rem; color: var(--text-main); font-size: 1.2rem;">iPad Air</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Desde</p>
                    <div class="er-price-new">S/ 2,199.00</div>
                    <div class="er-price-old">S/ 2,599.00</div>
                    <span class="er-btn er-btn-primary" style="width: 100%; margin-top: 1rem;">Lo Quiero</span>
                </a>
            @endforelse
        </div>
    </div>
</section>

<!-- Promo Banner Section -->
<section class="er-section" style="padding-top: 0;">
    <div class="er-container animate-on-scroll">
        <div class="er-promo-banner">
            <div class="er-promo-content">
                <h2 class="er-title-lg" style="margin-bottom: 1rem;">¿Un iPhone a tan buen precio?</h2>
                <p style="font-size: 1.2rem; margin-bottom: 2rem; color: #f5f5f7;">
                    ¿Muy bueno para ser verdad? Todos nuestros <strong style="color: var(--er-accent);">iPhones usados</strong> siguen un camino regulado y certificado hasta llegar a ti.
                </p>
                <div>
                    <a href="#catalogo" class="er-btn er-btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">Conoce Más</a>
                </div>
            </div>
            <div class="er-promo-image" style="background-image: url('{{ asset('img/hero-iphone.png') }}'); background-size: contain; background-repeat: no-repeat; transform: scale(1.2);"></div>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="er-section" style="background-color: var(--bg-secondary);">
    <div class="er-container">
        <h2 class="er-title-lg animate-on-scroll" style="text-align: center; margin-bottom: var(--space-lg); color: var(--text-main);">Nuestros clientes opinan 💖</h2>
        <div class="er-grid er-grid-3">
            <!-- Testimonial 1 -->
            <div class="er-testimonial-card animate-on-scroll">
                <div class="er-testimonial-avatar">
                    N
                    <div class="er-testimonial-google">
                        <svg viewBox="0 0 48 48" width="14" height="14"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    </div>
                </div>
                <h4 style="font-weight: 600; color: var(--text-main); margin-bottom: 0;">Nataly V</h4>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">22 Enero 2025</p>
                <div class="er-stars">★★★★★</div>
                <p class="er-testimonial-text">"Muy buen servicio y la atención al detalle fue excelente. Mi iPhone llegó impecable."</p>
            </div>
            <!-- Testimonial 2 -->
            <div class="er-testimonial-card animate-on-scroll" style="animation-delay: 0.1s;">
                <div class="er-testimonial-avatar">
                    D
                    <div class="er-testimonial-google">
                        <svg viewBox="0 0 48 48" width="14" height="14"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    </div>
                </div>
                <h4 style="font-weight: 600; color: var(--text-main); margin-bottom: 0;">Diego T</h4>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">15 Enero 2025</p>
                <div class="er-stars">★★★★★</div>
                <p class="er-testimonial-text">"Celular en excelente estado y hacen el envío muy rápido a provincia."</p>
            </div>
            <!-- Testimonial 3 -->
            <div class="er-testimonial-card animate-on-scroll" style="animation-delay: 0.2s;">
                <div class="er-testimonial-avatar">
                    C
                    <div class="er-testimonial-google">
                        <svg viewBox="0 0 48 48" width="14" height="14"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    </div>
                </div>
                <h4 style="font-weight: 600; color: var(--text-main); margin-bottom: 0;">Crisita Q</h4>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">10 Enero 2025</p>
                <div class="er-stars">★★★★★</div>
                <p class="er-testimonial-text">"Todo bien excelente vendedor, muy recomendado y seguro."</p>
            </div>
        </div>
    </div>
</section>
    @endif
@endsection

@section('extra_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Swiper !== 'undefined') {
            new Swiper('#hero-slider', {
                loop: true,
                effect: 'fade',
                fadeEffect: {
                    crossFade: true
                },
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
            });
        }
    });
</script>
@endsection
