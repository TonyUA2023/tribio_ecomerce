@extends('templates.textil-pro.layout')

{{-- Textil Pro · portada para talleres de estampado y confección: propuesta de valor con
     cotización por WhatsApp, servicios, cómo funciona, precios por mayor y menor, impresión
     por metro, para quién trabajan, productos, preguntas frecuentes y ubicación. Todo el
     texto sale de la configuración de la plantilla (módulo Plantillas). --}}
@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $tplPreview = $templatePreview ?? false;
    $t = $storefrontTheme;
    $show = fn (string $toggle) => $tplPreview || $t->enabled($toggle);
    $hideAttr = fn (string $toggle) => $t->enabled($toggle) ? '' : 'hidden';
    $quoteUrl = $store->whatsapp_link
        ? $store->whatsapp_link . '?text=' . rawurlencode($t->text('contact.whatsapp_message'))
        : route('store.contact', $store->slug);
    $heroImage = $t->image('hero.image');
    $secondaryLink = $t->link('hero.cta_secondary_link') ?? route('store.catalog', $store->slug);

    $products = collect($allProducts->items());
    // La cuadrícula de abajo es el catálogo general; los destacados van arriba (sección showcase).
    $catalogGrid = $products->take(8);
    $cats = $categories->where('is_featured', true);
    if ($cats->isEmpty()) {
        $cats = $categories->take(8);
    }
    $catImage = fn ($cat) => $cat->image_url ?: optional($cat->products->first())->image_url;
    $mapsUrl = $store->address
        ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim($store->address . ' ' . $store->city))
        : null;
    $socials = collect([
        'Instagram' => $store->instagram_url ?? null,
        'Facebook' => $store->facebook_url ?? null,
        'TikTok' => $store->tiktok_url ?? null,
    ])->filter();
@endphp

@section('title', $store->name . ' - ' . ($store->tagline ?: ($isEn ? 'Custom printing' : 'Estampados y confección')))

@push('preload')
    @if($heroImage)
        <link rel="preload" as="image" href="{{ $heroImage }}">
    @endif
@endpush

@section('content')
    {{-- ═════ Portada ═════ --}}
    <section class="tx-hero" data-tpl-choice="hero.style" data-choice="{{ $t->choice('hero.style') }}">
        <div class="tx-narrow tx-hero-grid">
            <div class="tx-hero-copy">
                <p class="tx-eyebrow" data-tpl-text="hero.eyebrow" data-tpl-hide-empty @if($t->text('hero.eyebrow') === '') hidden @endif>{{ $t->text('hero.eyebrow') }}</p>
                <h1 class="tx-hero-title">
                    <span data-tpl-text="hero.title">{{ $t->text('hero.title') }}</span>
                    <mark data-tpl-text="hero.title_highlight" data-tpl-hide-empty @if($t->text('hero.title_highlight') === '') hidden @endif>{{ $t->text('hero.title_highlight') }}</mark>
                </h1>
                <p class="tx-hero-text" data-tpl-text="hero.text" data-tpl-hide-empty @if($t->text('hero.text') === '') hidden @endif>{{ $t->text('hero.text') }}</p>
                <div class="tx-hero-actions">
                    <a class="tx-btn is-accent is-lg" href="{{ $quoteUrl }}" @if($store->whatsapp_link) target="_blank" rel="noopener" @endif>
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7a11.4 11.4 0 0 1-4.4-3.9 5 5 0 0 1-1-2.7 2.9 2.9 0 0 1 .9-2.2 1 1 0 0 1 .7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.2-.3.3-.1.6a8.5 8.5 0 0 0 1.6 2 7.7 7.7 0 0 0 2.3 1.4c.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3a2.3 2.3 0 0 1-.2 1.1Z"/></svg>
                        <span data-tpl-text="hero.cta_primary">{{ $t->text('hero.cta_primary') }}</span>
                    </a>
                    <a class="tx-btn is-ghost is-lg" href="{{ $secondaryLink }}" data-tpl-text="hero.cta_secondary" data-tpl-hide-empty @if($t->text('hero.cta_secondary') === '') hidden @endif>{{ $t->text('hero.cta_secondary') }}</a>
                </div>
                <ul class="tx-hero-badges">
                    @foreach([0, 1, 2] as $i)
                        <li data-tpl-text="hero.badges.items.{{ $i }}.text" data-tpl-hide-empty @if($t->text("hero.badges.items.{$i}.text") === '') hidden @endif>{{ $t->text("hero.badges.items.{$i}.text") }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="tx-hero-visual" data-tpl-img-host @if($heroImage) data-has-image @endif>
                <img class="tx-hero-photo" src="{{ $heroImage ?? '' }}" alt="" data-tpl-img="hero.image" fetchpriority="high" @unless($heroImage) hidden @endunless>
                {{-- Sin foto: una prenda ilustrada con los colores de la tienda y su marca estampada. --}}
                <div class="tx-shirt" aria-hidden="true">
                    <svg viewBox="0 0 400 420" class="tx-shirt-svg">
                        <path class="tx-shirt-body" d="M130 30c14 22 40 34 70 34s56-12 70-34l96 42-34 88-44-18v248H112V142l-44 18-34-88Z"/>
                        <path class="tx-shirt-collar" d="M130 30c14 22 40 34 70 34s56-12 70-34l-16-6c-12 16-32 24-54 24s-42-8-54-24Z"/>
                    </svg>
                    <div class="tx-shirt-print">
                        @if($store->logo_path)
                            <img src="{{ $store->logo_url }}" alt="">
                        @else
                            <strong>{{ $store->name }}</strong>
                        @endif
                    </div>
                </div>
                <span class="tx-float is-a" data-tpl-text="hero.badges.items.0.text" data-tpl-hide-empty @if($t->text('hero.badges.items.0.text') === '') hidden @endif>{{ $t->text('hero.badges.items.0.text') }}</span>
                <span class="tx-float is-b" data-tpl-text="hero.badges.items.1.text" data-tpl-hide-empty @if($t->text('hero.badges.items.1.text') === '') hidden @endif>{{ $t->text('hero.badges.items.1.text') }}</span>
            </div>
        </div>
    </section>

    {{-- ═════ Productos destacados (arriba) ═════
         Lo primero bajo la portada: productos (destacados, nuevos u ofertas) o, si todavía no
         hay productos, las fotos de Galería como trabajos realizados. Vacío = no se muestra. --}}
    @php
        $pricedQuery = fn () => $store->activeProducts()
            ->where(fn ($q) => $q->where('price', '>', 0)->orWhere('price_usd', '>', 0))
            ->with(['categories', 'category']);
        $showcaseSources = [
            // El controlador solo trae 3 destacados; aquí se piden hasta 12 (o los más nuevos si no hay).
            'featured' => fn () => $featuredProducts->isNotEmpty()
                ? $pricedQuery()->where('is_featured', true)->orderBy('sort_order')->orderByDesc('id')->limit(12)->get()
                : $pricedQuery()->orderByDesc('id')->limit(12)->get(),
            'newest' => fn () => $pricedQuery()->orderByDesc('id')->limit(12)->get(),
            'sale' => fn () => $pricedQuery()->whereNotNull('compare_price')->whereColumn('compare_price', '>', 'price')->orderBy('sort_order')->limit(12)->get(),
        ];
        $showcaseSource = $t->choice('showcase.source');
        // En la vista previa se arman las tres listas para que el selector cambie al instante.
        $showcaseRails = collect($tplPreview ? array_keys($showcaseSources) : [$showcaseSource])
            ->mapWithKeys(fn ($key) => [$key => $showcaseSources[$key]()]);
        $hasShowcaseProducts = $showcaseRails->contains(fn ($items) => $items->isNotEmpty());
        $works = !$hasShowcaseProducts && ($tplPreview || $t->enabled('showcase.use_gallery'))
            ? $store->galleryItems()->where('is_active', true)->where('type', '!=', 'hero')->orderBy('sort_order')->limit(12)->get()
            : collect();
    @endphp
    @if(($tplPreview || $t->enabled('showcase.enabled')) && ($hasShowcaseProducts || $works->isNotEmpty() || $tplPreview))
    <section class="tx-section tx-showcase" id="destacados" data-tpl-show="showcase.enabled" {{ $hideAttr('showcase.enabled') }}>
        <div class="tx-narrow">
            <div class="tx-head">
                <div>
                    <p class="tx-eyebrow" data-tpl-text="showcase.eyebrow" data-tpl-hide-empty @if($t->text('showcase.eyebrow') === '') hidden @endif>{{ $t->text('showcase.eyebrow') }}</p>
                    <h2 class="tx-h2 is-left" data-tpl-text="showcase.title">{{ $t->text('showcase.title') }}</h2>
                </div>
                <a class="tx-link" href="{{ $hasShowcaseProducts ? route('store.catalog', $store->slug) : route('store.gallery', $store->slug) }}"
                   data-tpl-text="showcase.cta" data-tpl-hide-empty @if($t->text('showcase.cta') === '') hidden @endif>{{ $t->text('showcase.cta') }}</a>
            </div>
            @if($hasShowcaseProducts)
                <div class="tx-rails" data-tpl-choice="showcase.source" data-choice="{{ $showcaseSource }}">
                    @foreach($showcaseRails as $source => $railProducts)
                        <div class="tx-rail-src" data-src="{{ $source }}">
                            @if($railProducts->isEmpty())
                                <p class="tx-muted-note">{{ $isEn ? 'No products here yet.' : 'Aún no hay productos para mostrar aquí.' }}</p>
                            @else
                                @include('templates.textil-pro._rail', ['products' => $railProducts])
                            @endif
                        </div>
                    @endforeach
                </div>
            @elseif($works->isNotEmpty())
                <div class="tx-works">
                    @foreach($works as $work)
                        <a class="tx-work" href="{{ $work->link_url ?: route('store.gallery', $store->slug) }}">
                            <img src="{{ $work->image_url }}" alt="{{ $work->title ?: $store->name }}" loading="lazy">
                            @if($work->title)<span>{{ $work->title }}</span>@endif
                        </a>
                    @endforeach
                </div>
            @else
                <p class="tx-custom-cta">{{ $isEn ? 'Your featured products will appear here. Add products, or photos of your work in Gallery.' : 'Aquí aparecerán tus productos destacados. Agrega productos, o fotos de tus trabajos en Galería.' }}</p>
            @endif
        </div>
    </section>
    @endif

    {{-- Promociones automáticas de la tienda (envío gratis, descuento por volumen) --}}
    @php $storePromos = $store->activePromoMessages($isEn); @endphp
    @if(count($storePromos) > 0)
        <div class="tx-promos"><div class="tx-wrap">@foreach($storePromos as $promo)<span>{{ $promo['icon'] }} {{ $promo['text'] }}</span>@endforeach</div></div>
    @endif

    {{-- ═════ Cifras ═════ --}}
    @if($show('stats.enabled'))
    <section class="tx-stats" data-tpl-show="stats.enabled" {{ $hideAttr('stats.enabled') }}>
        <div class="tx-narrow tx-stats-grid">
            @foreach([0, 1, 2, 3] as $i)
                <div class="tx-stat" @if($t->text("stats.items.{$i}.value") === '' && !$tplPreview) hidden @endif>
                    <strong data-tpl-text="stats.items.{{ $i }}.value">{{ $t->text("stats.items.{$i}.value") }}</strong>
                    <span data-tpl-text="stats.items.{{ $i }}.label">{{ $t->text("stats.items.{$i}.label") }}</span>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ═════ Servicios ═════ --}}
    @if($show('services.enabled'))
    <section class="tx-section" id="servicios" data-tpl-show="services.enabled" {{ $hideAttr('services.enabled') }}>
        <div class="tx-narrow">
            <div class="tx-heading">
                <p class="tx-eyebrow" data-tpl-text="services.eyebrow" data-tpl-hide-empty @if($t->text('services.eyebrow') === '') hidden @endif>{{ $t->text('services.eyebrow') }}</p>
                <h2 class="tx-h2" data-tpl-text="services.title">{{ $t->text('services.title') }}</h2>
            </div>
            <div class="tx-services">
                @foreach([0, 1, 2, 3, 4, 5] as $i)
                    @php $serviceLink = $t->link("services.items.{$i}.link"); @endphp
                    <a class="tx-service" @if($serviceLink) href="{{ $serviceLink }}" @endif
                       @if($t->text("services.items.{$i}.title") === '' && !$tplPreview) hidden @endif>
                        <span class="tx-service-icon" data-tpl-text="services.items.{{ $i }}.icon">{{ $t->text("services.items.{$i}.icon") }}</span>
                        <h3 data-tpl-text="services.items.{{ $i }}.title">{{ $t->text("services.items.{$i}.title") }}</h3>
                        <p data-tpl-text="services.items.{{ $i }}.text">{{ $t->text("services.items.{$i}.text") }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═════ Cómo funciona ═════ --}}
    @if($show('process.enabled'))
    <section class="tx-section tx-process-wrap" data-tpl-show="process.enabled" {{ $hideAttr('process.enabled') }}>
        <div class="tx-narrow">
            <div class="tx-heading">
                <h2 class="tx-h2" data-tpl-text="process.title">{{ $t->text('process.title') }}</h2>
            </div>
            <ol class="tx-steps">
                @foreach([0, 1, 2, 3] as $i)
                    <li class="tx-step">
                        <span class="tx-step-n" aria-hidden="true">{{ $i + 1 }}</span>
                        <h3 data-tpl-text="process.items.{{ $i }}.title">{{ $t->text("process.items.{$i}.title") }}</h3>
                        <p data-tpl-text="process.items.{{ $i }}.text">{{ $t->text("process.items.{$i}.text") }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
    @endif

    {{-- ═════ Por mayor y menor ═════ --}}
    @if($show('wholesale.enabled'))
    <section class="tx-section" id="por-mayor" data-tpl-show="wholesale.enabled" {{ $hideAttr('wholesale.enabled') }}>
        <div class="tx-narrow tx-wholesale">
            <div class="tx-wholesale-copy">
                <p class="tx-eyebrow" data-tpl-text="wholesale.eyebrow" data-tpl-hide-empty @if($t->text('wholesale.eyebrow') === '') hidden @endif>{{ $t->text('wholesale.eyebrow') }}</p>
                <h2 class="tx-h2 is-left" data-tpl-text="wholesale.title">{{ $t->text('wholesale.title') }}</h2>
                <p data-tpl-text="wholesale.text">{{ $t->text('wholesale.text') }}</p>
                <a class="tx-btn is-accent" href="{{ $quoteUrl }}" @if($store->whatsapp_link) target="_blank" rel="noopener" @endif data-tpl-text="wholesale.cta">{{ $t->text('wholesale.cta') }}</a>
            </div>
            <div class="tx-tiers" role="table" aria-label="{{ $t->text('wholesale.title') }}">
                @foreach([0, 1, 2, 3] as $i)
                    <div class="tx-tier" role="row" @if($t->text("wholesale.items.{$i}.qty") === '' && !$tplPreview) hidden @endif>
                        <strong role="cell" data-tpl-text="wholesale.items.{{ $i }}.qty">{{ $t->text("wholesale.items.{$i}.qty") }}</strong>
                        <span role="cell" data-tpl-text="wholesale.items.{{ $i }}.benefit">{{ $t->text("wholesale.items.{$i}.benefit") }}</span>
                    </div>
                @endforeach
                <p class="tx-tiers-note" data-tpl-text="wholesale.note" data-tpl-hide-empty @if($t->text('wholesale.note') === '') hidden @endif>{{ $t->text('wholesale.note') }}</p>
            </div>
        </div>
    </section>
    @endif

    {{-- ═════ Impresión por metro / promoción ═════ --}}
    @if($show('promo.enabled'))
    @php $promoImage = $t->image('promo.image'); $promoLink = $t->link('promo.link'); @endphp
    <section class="tx-promo-band" data-tpl-show="promo.enabled" {{ $hideAttr('promo.enabled') }}>
        <div class="tx-narrow tx-promo-grid">
            <div>
                <p class="tx-eyebrow" data-tpl-text="promo.eyebrow" data-tpl-hide-empty @if($t->text('promo.eyebrow') === '') hidden @endif>{{ $t->text('promo.eyebrow') }}</p>
                <h2 class="tx-promo-title" data-tpl-text="promo.title">{{ $t->text('promo.title') }}</h2>
                <p class="tx-promo-text" data-tpl-text="promo.text" data-tpl-hide-empty @if($t->text('promo.text') === '') hidden @endif>{{ $t->text('promo.text') }}</p>
                <a class="tx-btn" href="{{ $promoLink ?? $quoteUrl }}" @if(!$promoLink && $store->whatsapp_link) target="_blank" rel="noopener" @endif data-tpl-text="promo.cta">{{ $t->text('promo.cta') }}</a>
            </div>
            <div class="tx-promo-side" data-tpl-img-host>
                <div class="tx-price-tag" @if($t->text('promo.price') === '' && !$tplPreview) hidden @endif>
                    <small data-tpl-text="promo.price_prefix">{{ $t->text('promo.price_prefix') }}</small>
                    <strong data-tpl-text="promo.price">{{ $t->text('promo.price') }}</strong>
                    <span data-tpl-text="promo.price_note">{{ $t->text('promo.price_note') }}</span>
                </div>
                <img src="{{ $promoImage ?? '' }}" alt="" loading="lazy" data-tpl-img="promo.image" @unless($promoImage) hidden @endunless>
            </div>
        </div>
    </section>
    @endif

    {{-- ═════ Para quién trabajamos ═════ --}}
    @if($show('segments.enabled'))
    <section class="tx-section" data-tpl-show="segments.enabled" {{ $hideAttr('segments.enabled') }}>
        <div class="tx-narrow">
            <div class="tx-heading">
                <h2 class="tx-h2" data-tpl-text="segments.title">{{ $t->text('segments.title') }}</h2>
            </div>
            <div class="tx-segments">
                @foreach([0, 1, 2, 3] as $i)
                    @php $segImage = $t->image("segments.items.{$i}.image"); @endphp
                    <a class="tx-segment" href="{{ $quoteUrl }}" @if($store->whatsapp_link) target="_blank" rel="noopener" @endif data-tpl-img-host
                       @if($t->text("segments.items.{$i}.title") === '' && !$tplPreview) hidden @endif>
                        <img src="{{ $segImage ?? '' }}" alt="" loading="lazy" data-tpl-img="segments.items.{{ $i }}.image" @unless($segImage) hidden @endunless>
                        <span class="tx-segment-icon" aria-hidden="true" data-tpl-text="segments.items.{{ $i }}.icon">{{ $t->text("segments.items.{$i}.icon") }}</span>
                        <span class="tx-segment-copy">
                            <h3 data-tpl-text="segments.items.{{ $i }}.title">{{ $t->text("segments.items.{$i}.title") }}</h3>
                            <p data-tpl-text="segments.items.{{ $i }}.text">{{ $t->text("segments.items.{$i}.text") }}</p>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═════ Categorías ═════ --}}
    @if($cats->isNotEmpty() && ($tplPreview || $t->enabled('categories.enabled')))
    <section class="tx-section" data-tpl-show="categories.enabled" {{ $hideAttr('categories.enabled') }}>
        <div class="tx-narrow">
            <h2 class="tx-h2" data-tpl-text="categories.title" data-tpl-hide-empty @if($t->text('categories.title') === '') hidden @endif>{{ $t->text('categories.title') }}</h2>
            <div class="tx-cats">
                @foreach($cats as $cat)
                    @php $image = $catImage($cat); @endphp
                    <a class="tx-cat" href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}">
                        <span class="tx-cat-media">
                            @if($image)<img src="{{ $image }}" alt="" loading="lazy">@else<span class="tx-tile-fallback" aria-hidden="true"></span>@endif
                        </span>
                        <strong>{{ $cat->getTranslatedName() }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═════ Productos ═════ --}}
    <section class="tx-section" id="productos">
        <div class="tx-narrow">
            <div class="tx-head">
                <h2 class="tx-h2 is-left" data-tpl-text="sections.products_title">{{ $t->text('sections.products_title') }}</h2>
                @if($catalogGrid->isNotEmpty())
                    <a class="tx-link" href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'View all' : 'Ver todo' }}</a>
                @endif
            </div>
            @if($catalogGrid->isEmpty())
                {{-- Catálogo aún vacío: la portada invita a cotizar en vez de mostrar un hueco. --}}
                <div class="tx-custom-cta">
                    <div>
                        <h3 data-tpl-text="sections.empty_title">{{ $t->text('sections.empty_title') }}</h3>
                        <p data-tpl-text="sections.empty_text">{{ $t->text('sections.empty_text') }}</p>
                    </div>
                    <a class="tx-btn is-accent is-lg" href="{{ $quoteUrl }}" @if($store->whatsapp_link) target="_blank" rel="noopener" @endif>{{ $t->text('hero.cta_primary') }}</a>
                </div>
            @else
                <div class="tx-grid">
                    @foreach($catalogGrid as $product)
                        @include('templates.textil-pro._product-card', ['product' => $product])
                    @endforeach
                </div>
                <div style="display: flex; justify-content: center; margin-top: 40px">
                    <a class="tx-btn is-outline" href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'Shop all products' : 'Ver todos los productos' }}</a>
                </div>
            @endif
        </div>
    </section>

    {{-- ═════ Preguntas frecuentes ═════ --}}
    @if($show('faq.enabled'))
    <section class="tx-section tx-faq-wrap" data-tpl-show="faq.enabled" {{ $hideAttr('faq.enabled') }}>
        <div class="tx-narrow tx-faq">
            <h2 class="tx-h2 is-left" data-tpl-text="faq.title">{{ $t->text('faq.title') }}</h2>
            <div class="tx-faq-list">
                @foreach([0, 1, 2, 3, 4] as $i)
                    <details class="tx-faq-item" @if($t->text("faq.items.{$i}.question") === '' && !$tplPreview) hidden @endif @if($i === 0) open @endif>
                        <summary data-tpl-text="faq.items.{{ $i }}.question">{{ $t->text("faq.items.{$i}.question") }}</summary>
                        <p data-tpl-text="faq.items.{{ $i }}.answer">{{ $t->text("faq.items.{$i}.answer") }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═════ Visítanos / contacto ═════ --}}
    @if($show('contact.enabled'))
    <section class="tx-visit" data-tpl-show="contact.enabled" {{ $hideAttr('contact.enabled') }}>
        <div class="tx-narrow tx-visit-grid">
            <div>
                <h2 class="tx-h2 is-left" data-tpl-text="contact.title">{{ $t->text('contact.title') }}</h2>
                <p class="tx-visit-text" data-tpl-text="contact.text" data-tpl-hide-empty @if($t->text('contact.text') === '') hidden @endif>{{ $t->text('contact.text') }}</p>
                <div class="tx-hero-actions">
                    <a class="tx-btn is-accent" href="{{ $quoteUrl }}" @if($store->whatsapp_link) target="_blank" rel="noopener" @endif>{{ $t->text('hero.cta_primary') }}</a>
                    @if($mapsUrl)
                        <a class="tx-btn is-ghost" href="{{ $mapsUrl }}" target="_blank" rel="noopener">{{ $isEn ? 'Get directions' : 'Cómo llegar' }}</a>
                    @endif
                </div>
            </div>
            <ul class="tx-visit-list">
                @if($store->address)
                    <li><span aria-hidden="true">📍</span><div><strong>{{ $isEn ? 'Address' : 'Dirección' }}</strong>{{ $store->address }}@if($store->city), {{ $store->city }}@endif</div></li>
                @endif
                <li @if($t->text('contact.hours') === '' && !$tplPreview) hidden @endif><span aria-hidden="true">🕘</span><div><strong>{{ $isEn ? 'Hours' : 'Horario' }}</strong><span data-tpl-text="contact.hours">{{ $t->text('contact.hours') }}</span></div></li>
                @if($store->whatsapp_phone)
                    <li><span aria-hidden="true">💬</span><div><strong>WhatsApp</strong><a href="{{ $quoteUrl }}" target="_blank" rel="noopener">{{ $store->whatsapp_phone }}</a></div></li>
                @endif
                @if($store->email)
                    <li><span aria-hidden="true">✉️</span><div><strong>Email</strong><a href="mailto:{{ $store->email }}">{{ $store->email }}</a></div></li>
                @endif
                @if($socials->isNotEmpty())
                    <li><span aria-hidden="true">📲</span><div><strong>{{ $isEn ? 'Follow us' : 'Síguenos' }}</strong>
                        <span class="tx-visit-socials">@foreach($socials as $network => $url)<a href="{{ $url }}" target="_blank" rel="noopener">{{ $network }}</a>@endforeach</span></div></li>
                @endif
            </ul>
        </div>
    </section>
    @endif
@endsection
