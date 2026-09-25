@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $tplPreview = $templatePreview ?? false;
    $t = $storefrontTheme;
    $priced = fn ($q) => $q->where(fn ($sq) => $sq->where('price', '>', 0)->orWhere('price_usd', '>', 0));

    // ── Banners de portada ─────────────────────────────────────────────
    // Sin imagen subida, el banner 1 y 2 usan las fotos "Hero" de Galería (o la portada)
    // y, si no hay ninguna, el degradado de la marca: la tienda nunca se ve vacía.
    $heroGallery = $store->galleryItems()->where('type', 'hero')->where('is_active', true)->orderBy('sort_order')->get()->pluck('image_url')->values();
    if ($heroGallery->isEmpty() && $store->cover_path) {
        $heroGallery = collect([$store->cover_url]);
    }
    $slides = [];
    foreach ([0, 1, 2] as $i) {
        $enabled = $i === 0 || $t->enabled("hero.items.{$i}.enabled");
        if (!$enabled && !$tplPreview) {
            continue;
        }
        $desktop = $t->image("hero.items.{$i}.image");
        $mobile = $t->image("hero.items.{$i}.image_mobile");
        $fallback = $i < 2 ? ($heroGallery[$i] ?? null) : null;
        $slides[] = [
            'i' => $i,
            'enabled' => $enabled,
            'desktop' => $desktop,
            'mobile' => $mobile,
            'fallback' => $fallback,
            'src' => $desktop ?: ($mobile ?: $fallback),
            'tone' => $t->choice("hero.items.{$i}.tone"),
            'align' => $t->choice("hero.items.{$i}.align"),
            'link' => $t->link("hero.items.{$i}.link"),
        ];
    }
    $firstTone = $slides[0]['tone'] ?? 'light';
    $heroText = fn (int $i, string $key) => $t->text("hero.items.{$i}.{$key}");

    // ── Categorías destacadas ──────────────────────────────────────────
    $featuredCats = $categories->where('is_featured', true);
    if ($featuredCats->isEmpty()) {
        $featuredCats = $categories->take(8);
    }
    $catImage = fn ($cat) => $cat->image_url ?: optional($cat->products->first())->image_url;
    $catImages = $featuredCats->map($catImage)->filter()->values();

    // ── Vitrina por categoría (pestañas) ───────────────────────────────
    $showcaseTabs = $categories
        ->filter(fn ($cat) => ($cat->active_products_count ?? 0) > 0)
        ->sortByDesc('is_featured')
        ->take(4)
        ->map(function ($cat) use ($store, $priced) {
            $ids = array_merge([$cat->id], $cat->children->pluck('id')->all());
            $products = $priced($store->activeProducts())
                ->where(fn ($q) => $q->whereIn('category_id', $ids)->orWhereHas('categories', fn ($sq) => $sq->whereIn('categories.id', $ids)))
                ->with(['categories', 'category'])
                ->orderByDesc('is_featured')->orderBy('sort_order')
                ->limit(10)->get();

            return ['name' => $cat->getTranslatedName(), 'slug' => $cat->slug, 'products' => $products];
        })
        ->filter(fn ($tab) => $tab['products']->isNotEmpty())
        ->values();
    if ($showcaseTabs->isEmpty()) {
        $fallbackProducts = $featuredProducts->isNotEmpty() ? $featuredProducts : collect($allProducts->items())->take(10);
        if ($fallbackProducts->isNotEmpty()) {
            $showcaseTabs = collect([['name' => $isEn ? 'Featured' : 'Destacados', 'slug' => null, 'products' => $fallbackProducts]]);
        }
    }

    // ── Novedades ──────────────────────────────────────────────────────
    $newArrivals = $priced($store->activeProducts())->with(['categories', 'category'])->orderByDesc('id')->limit(8)->get();

    $popupEnabled = $t->enabled('popup.enabled');
    $popupImage = $t->image('popup.image');
    $popupLink = $t->link('popup.link');
@endphp
<!DOCTYPE html>
<html lang="{{ \App\Helpers\TranslationHelper::currentLang() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $store->name }} - Tienda Online</title>
    @if($store->logo_path)
        <link rel="icon" type="image/png" href="{{ $store->logo_url }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @if(!empty($slides[0]['src']))
        <link rel="preload" as="image" href="{{ $slides[0]['src'] }}">
    @endif
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background-color: var(--t-bg); min-height: 100vh; overflow-x: hidden; }
        .font-brand { font-family: var(--font-brand) !important; }
        .goog-te-banner-frame.skiptranslate, .goog-te-gadget-icon { display: none !important; }
        body { top: 0px !important; }
        #goog-gt-tt, .goog-te-balloon-frame { display: none !important; }
        .goog-text-highlight { background: none !important; box-shadow: none !important; }
    </style>
    @include('templates.urban-style._theme')
    @include('components.marketing.head')
</head>
<body class="antialiased us-body">
    @include('templates.urban-style._translate')

    @include('templates.urban-style.header', ['overlayHeader' => true])

    <main>
        <h1 class="us-sr">{{ $store->name }}</h1>

        {{-- ═════ Banners de portada ═════ --}}
        <div class="us-hero-shell" data-tpl-choice="header.style" data-choice="{{ $t->choice('header.style') }}">
            <section class="us-hero" x-data="usHero({ autoplay: {{ !$tplPreview && $t->enabled('hero.autoplay') ? 'true' : 'false' }} })"
                     data-tpl-choice="hero.height" data-choice="{{ $t->choice('hero.height') }}" data-tone="{{ $firstTone }}"
                     aria-roledescription="carrusel" aria-label="{{ $isEn ? 'Featured promotions' : 'Promociones destacadas' }}"
                     @mouseenter="pause()" @mouseleave="resume()" @focusin="pause()" @focusout="resume()">
                <div class="us-hero-track" x-ref="track" @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)">
                    @foreach($slides as $slide)
                        @php $i = $slide['i']; @endphp
                        <div class="us-slide {{ $loop->first ? 'is-active' : '' }}" data-slide data-tpl-img-host
                             data-tpl-choice="hero.items.{{ $i }}.tone" data-choice="{{ $slide['tone'] }}"
                             @if($slide['src']) data-has-image @endif
                             @if($i > 0) data-tpl-show="hero.items.{{ $i }}.enabled" @unless($slide['enabled']) hidden @endunless @endif
                             role="group" aria-roledescription="banner">
                            <div class="us-slide-bg">
                                <picture>
                                    <source media="{{ $slide['mobile'] ? '(max-width: 767px)' : 'not all' }}" data-media="(max-width: 767px)"
                                            srcset="{{ $slide['mobile'] ?? '' }}" data-tpl-img="hero.items.{{ $i }}.image_mobile">
                                    <img src="{{ $slide['src'] ?? '' }}" alt="" data-tpl-img="hero.items.{{ $i }}.image" data-tpl-img-fallback="{{ $slide['fallback'] ?? '' }}"
                                         @if(!$loop->first) loading="lazy" @else fetchpriority="high" @endif @unless($slide['src']) hidden @endunless>
                                </picture>
                            </div>
                            <div class="us-slide-scrim" data-tpl-choice="hero.items.{{ $i }}.align" data-choice="{{ $slide['align'] }}"></div>
                            @if($slide['link'])
                                <a class="us-slide-link" href="{{ $slide['link'] }}" aria-label="{{ $heroText($i, 'title') ?: $store->name }}"></a>
                            @endif
                            <div class="us-slide-copy" data-tpl-choice="hero.items.{{ $i }}.align" data-choice="{{ $slide['align'] }}">
                                <div class="us-wrap">
                                    <div class="us-copy">
                                        @foreach([
                                            ['title', 'h2', 'us-title'],
                                            ['eyebrow', 'p', 'us-eyebrow'],
                                        ] as [$key, $tag, $class])
                                            <{{ $tag }} class="{{ $class }}" data-tpl-text="hero.items.{{ $i }}.{{ $key }}" data-tpl-hide-empty @if($heroText($i, $key) === '') hidden @endif>{{ $heroText($i, $key) }}</{{ $tag }}>
                                        @endforeach
                                        <div class="us-figure">
                                            <span class="us-figure-prefix" data-tpl-text="hero.items.{{ $i }}.highlight_prefix" data-tpl-hide-empty @if($heroText($i, 'highlight_prefix') === '') hidden @endif>{{ $heroText($i, 'highlight_prefix') }}</span>
                                            <span class="us-figure-value" data-tpl-text="hero.items.{{ $i }}.highlight" data-tpl-hide-empty @if($heroText($i, 'highlight') === '') hidden @endif>{{ $heroText($i, 'highlight') }}</span>
                                            <span class="us-figure-note" data-tpl-text="hero.items.{{ $i }}.highlight_note" data-tpl-hide-empty @if($heroText($i, 'highlight_note') === '') hidden @endif>{{ $heroText($i, 'highlight_note') }}</span>
                                        </div>
                                        <span class="us-badge" data-tpl-text="hero.items.{{ $i }}.subtitle" data-tpl-hide-empty @if($heroText($i, 'subtitle') === '') hidden @endif>{{ $heroText($i, 'subtitle') }}</span>
                                        <p class="us-legal" data-tpl-text="hero.items.{{ $i }}.legal" data-tpl-hide-empty @if($heroText($i, 'legal') === '') hidden @endif>{{ $heroText($i, 'legal') }}</p>
                                        <a class="us-cta" @if($slide['link']) href="{{ $slide['link'] }}" @endif data-tpl-text="hero.items.{{ $i }}.cta" data-tpl-hide-empty @if($heroText($i, 'cta') === '') hidden @endif>{{ $heroText($i, 'cta') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="button" class="us-hero-arrow is-prev" x-show="count > 1" @click="prev()" aria-label="{{ $isEn ? 'Previous banner' : 'Banner anterior' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4m6-6-6 6 6 6"/></svg>
                </button>
                <button type="button" class="us-hero-arrow is-next" x-show="count > 1" @click="next()" aria-label="{{ $isEn ? 'Next banner' : 'Banner siguiente' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16m-6-6 6 6-6 6"/></svg>
                </button>
                <div class="us-hero-dots" x-show="count > 1">
                    <template x-for="n in count" :key="n">
                        <button type="button" :class="{ 'is-active': n - 1 === index }" @click="go(n - 1)" :aria-label="'{{ $isEn ? 'Go to banner' : 'Ir al banner' }} ' + n" :aria-current="(n - 1 === index).toString()"></button>
                    </template>
                </div>
                <button type="button" class="us-hero-down" @click="document.getElementById('us-after-hero').scrollIntoView({ behavior: 'smooth' })" aria-label="{{ $isEn ? 'Scroll down' : 'Bajar' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m-6-6 6 6 6-6"/></svg>
                </button>
            </section>
        </div>

        <div id="us-after-hero" style="scroll-margin-top: var(--us-header-h)"></div>

        {{-- Promos automáticas de la tienda (envío gratis / descuento por volumen) --}}
        @php $heroPromos = $store->activePromoMessages($isEn); @endphp
        @if(count($heroPromos) > 0)
            <div class="us-promos" style="margin-top: 22px">
                <div class="us-wrap">
                    @foreach($heroPromos as $promo)
                        <span>{{ $promo['icon'] }} {{ $promo['text'] }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ═════ Compra por categoría ═════ --}}
        @if($featuredCats->isNotEmpty() && ($tplPreview || $t->enabled('categories.enabled')))
        <section class="us-section" style="padding-top: 64px" data-tpl-show="categories.enabled" @unless($t->enabled('categories.enabled')) hidden @endunless>
            <div class="us-wrap">
                <div class="us-section-head">
                    <h2 class="us-h2 is-center" data-tpl-text="categories.title" data-tpl-hide-empty @if($t->text('categories.title') === '') hidden @endif>{{ $t->text('categories.title') }}</h2>
                </div>
                <div class="us-cats" data-tpl-choice="categories.style" data-choice="{{ $t->choice('categories.style') }}">
                    @foreach($featuredCats as $cat)
                        @php $image = $catImage($cat); @endphp
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" class="us-cat">
                            <span class="us-cat-media" @if(!$image && $cat->color) style="background: {{ $cat->color }}" @endif>
                                @if($image)
                                    <img src="{{ $image }}" alt="" loading="lazy">
                                @else
                                    <span aria-hidden="true">{{ $cat->icon ?: '👕' }}</span>
                                @endif
                            </span>
                            <strong>{{ $cat->getTranslatedName() }}</strong>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- ═════ Banners de colección ═════ --}}
        @if($tplPreview || $t->enabled('editorial.enabled'))
        <section class="us-section" data-tpl-show="editorial.enabled" @unless($t->enabled('editorial.enabled')) hidden @endunless>
            <div class="us-wrap us-editorial">
                @foreach([0, 1] as $e)
                    @php
                        $editImage = $t->image("editorial.items.{$e}.image");
                        $editFallback = $catImages[$e] ?? ($heroGallery[$e + 1] ?? null);
                        $editSrc = $editImage ?: $editFallback;
                        $editLink = $t->link("editorial.items.{$e}.link");
                    @endphp
                    <a class="us-edit" @if($editLink) href="{{ $editLink }}" @endif data-tpl-img-host @if($editSrc) data-has-image @endif>
                        <span class="us-edit-fallback" aria-hidden="true"></span>
                        <img src="{{ $editSrc ?? '' }}" alt="" loading="lazy" data-tpl-img="editorial.items.{{ $e }}.image" data-tpl-img-fallback="{{ $editFallback ?? '' }}" @unless($editSrc) hidden @endunless>
                        <span class="us-edit-copy">
                            <h3 data-tpl-text="editorial.items.{{ $e }}.title">{{ $t->text("editorial.items.{$e}.title") }}</h3>
                            <span data-tpl-text="editorial.items.{{ $e }}.cta">{{ $t->text("editorial.items.{$e}.cta") }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═════ Vitrina por categoría ═════ --}}
        @if($showcaseTabs->isNotEmpty() && ($tplPreview || $t->enabled('showcase.enabled')))
        <section class="us-section" x-data="{ tab: 0 }" data-tpl-show="showcase.enabled" @unless($t->enabled('showcase.enabled')) hidden @endunless>
            <div class="us-wrap">
                <div class="us-section-head">
                    <h2 class="us-h2" data-tpl-text="showcase.title" data-tpl-hide-empty @if($t->text('showcase.title') === '') hidden @endif>{{ $t->text('showcase.title') }}</h2>
                </div>
                <div class="us-tabs" role="tablist">
                    <span class="us-tabs-label" data-tpl-text="showcase.label" data-tpl-hide-empty @if($t->text('showcase.label') === '') hidden @endif>{{ $t->text('showcase.label') }}</span>
                    @foreach($showcaseTabs as $tabIndex => $tab)
                        <button type="button" role="tab" :class="{ 'is-active': tab === {{ $tabIndex }} }" :aria-selected="(tab === {{ $tabIndex }}).toString()" @click="tab = {{ $tabIndex }}">{{ $tab['name'] }}</button>
                    @endforeach
                </div>
                @foreach($showcaseTabs as $tabIndex => $tab)
                    <div class="us-rail" role="tabpanel" x-data="usRail" x-show="tab === {{ $tabIndex }}" @if($tabIndex > 0) x-cloak @endif
                         x-effect="tab === {{ $tabIndex }} && $nextTick(() => update())">
                        <button type="button" class="us-rail-btn is-prev" @click="scroll(-1)" :disabled="atStart" aria-label="{{ $isEn ? 'Previous' : 'Anterior' }}">
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4m6-6-6 6 6 6"/></svg>
                        </button>
                        <div class="us-rail-track" x-ref="track" @scroll.passive.debounce.60ms="update()">
                            @foreach($tab['products'] as $product)
                                @include('templates.urban-style._product-card', ['product' => $product])
                            @endforeach
                        </div>
                        <button type="button" class="us-rail-btn is-next" @click="scroll(1)" :disabled="atEnd" aria-label="{{ $isEn ? 'Next' : 'Siguiente' }}">
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16m-6-6 6 6-6 6"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ═════ Novedades ═════ --}}
        <section class="us-section">
            <div class="us-wrap">
                <div class="us-section-head">
                    <h2 class="us-h2" data-tpl-text="sections.products_title">{{ $t->text('sections.products_title') }}</h2>
                    @if($newArrivals->isNotEmpty())
                        <a href="{{ route('store.catalog', ['slug' => $store->slug, 'sort' => 'newest']) }}" class="us-link">{{ $isEn ? 'View all' : 'Ver todo' }}</a>
                    @endif
                </div>
                @if($newArrivals->isEmpty())
                    <p style="text-align: center; color: var(--us-muted); padding: 48px 0">{{ $isEn ? 'Products coming soon…' : 'Muy pronto nuevos productos…' }}</p>
                @else
                    <div class="us-grid">
                        @foreach($newArrivals as $product)
                            @include('templates.urban-style._product-card', ['product' => $product])
                        @endforeach
                    </div>
                    <div style="display: flex; justify-content: center; margin-top: 40px">
                        <a href="{{ route('store.catalog', $store->slug) }}" class="us-btn">{{ $isEn ? 'Shop the full catalog' : 'Ver todo el catálogo' }}</a>
                    </div>
                @endif
            </div>
        </section>
    </main>

    {{-- ═════ Beneficios ═════ --}}
    @if($tplPreview || $t->enabled('benefits.enabled'))
    <section class="us-benefits" data-tpl-show="benefits.enabled" @unless($t->enabled('benefits.enabled')) hidden @endunless>
        <div class="us-wrap">
            @php
                $benefitIcons = [
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7M7.5 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm10 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/>',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M4 12a8 8 0 0 1 14-5.3M20 4v4h-4M20 12a8 8 0 0 1-14 5.3M4 20v-4h4"/>',
                    '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4"/>',
                ];
            @endphp
            @foreach([0, 1, 2] as $benefit)
                <div class="us-benefit">
                    <i><svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">{!! $benefitIcons[$benefit] !!}</svg></i>
                    <div>
                        <h4 data-tpl-text="benefits.items.{{ $benefit }}.title">{{ $t->text("benefits.items.{$benefit}.title") }}</h4>
                        <p data-tpl-text="benefits.items.{{ $benefit }}.text">{{ $t->text("benefits.items.{$benefit}.text") }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    @include('templates.urban-style.footer')

    {{-- Pasarela de pago estándar (carrito + Tribio Pass) --}}
    @include('components.checkout.gateway')

    {{-- ═════ Pop-up promocional (una vez por visita) ═════ --}}
    @if($tplPreview || $popupEnabled)
    <div x-data="usPopup({ auto: {{ !$tplPreview && $popupEnabled ? 'true' : 'false' }}, key: 'us-popup-{{ $store->id }}' })"
         data-tpl-show="popup.enabled" @unless($popupEnabled) hidden @endunless>
        @if($tplPreview)
            <button type="button" class="us-popup-preview-pill" @click="show()">Ver pop-up promocional</button>
        @endif
        <div class="us-popup" x-show="open" x-cloak @click.self="close()" @keydown.escape.window="close()" role="dialog" aria-modal="true" aria-label="{{ $isEn ? 'Promotion' : 'Promoción' }}">
            <div class="us-popup-card" data-tpl-img-host @if($popupImage) data-has-image @endif>
                <button type="button" class="us-popup-close" @click="close()" aria-label="{{ $isEn ? 'Close' : 'Cerrar' }}">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
                <a class="us-popup-image" @if($popupLink) href="{{ $popupLink }}" @endif>
                    <img src="{{ $popupImage ?? '' }}" alt="{{ $isEn ? 'Promotion' : 'Promoción' }}" data-tpl-img="popup.image" @unless($popupImage) hidden @endunless>
                </a>
                <div class="us-popup-copy">
                    <p class="us-popup-eyebrow" data-tpl-text="popup.eyebrow" data-tpl-hide-empty @if($t->text('popup.eyebrow') === '') hidden @endif>{{ $t->text('popup.eyebrow') }}</p>
                    <div class="us-popup-ticket">
                        <span class="us-popup-amount" data-tpl-text="popup.amount">{{ $t->text('popup.amount') }}</span>
                        <span class="us-popup-note" data-tpl-text="popup.amount_note" data-tpl-hide-empty @if($t->text('popup.amount_note') === '') hidden @endif>{{ $t->text('popup.amount_note') }}</span>
                    </div>
                    <p class="us-popup-label" data-tpl-text="popup.code_label" data-tpl-hide-empty @if($t->text('popup.code_label') === '') hidden @endif>{{ $t->text('popup.code_label') }}</p>
                    <button type="button" class="us-popup-code" @click="copy($event)" @if($t->text('popup.code') === '') hidden @endif>
                        <span data-tpl-text="popup.code">{{ $t->text('popup.code') }}</span>
                        <small x-text="copied ? '{{ $isEn ? 'Copied!' : '¡Copiado!' }}' : '{{ $isEn ? 'Copy' : 'Copiar' }}'">{{ $isEn ? 'Copy' : 'Copiar' }}</small>
                    </button>
                    <p class="us-popup-footer" data-tpl-text="popup.footer" data-tpl-hide-empty @if($t->text('popup.footer') === '') hidden @endif>{{ $t->text('popup.footer') }}</p>
                    @if($popupLink)
                        <a href="{{ $popupLink }}" class="us-btn" style="background: #fff; color: var(--us-ink); margin-top: 6px" @click="close()">{{ $isEn ? 'Shop now' : 'Comprar ahora' }}</a>
                    @endif
                    <p class="us-popup-legal" data-tpl-text="popup.legal" data-tpl-hide-empty @if($t->text('popup.legal') === '') hidden @endif>{{ $t->text('popup.legal') }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de selección de país (si el visitante aún no eligió) --}}
    @if(!request()->hasCookie('user_country'))
    @php
        $modalCountries = $store->getEnabledCountriesWithDetails();
        if (empty($modalCountries)) {
            $modalCountries = [
                'PE' => \App\Helpers\CurrencyHelper::getCountryInfo('PE'),
                'US' => \App\Helpers\CurrencyHelper::getCountryInfo('US'),
            ];
        }
    @endphp
    <div x-data="{ showModal: true }" x-show="showModal" style="display:none;" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6 sm:p-8 text-center animate-fade-in-up">
            <h2 class="text-2xl font-black text-gray-900 mb-2 font-brand">¡Hola! 👋</h2>
            <p class="text-gray-500 mb-6 text-xs sm:text-sm">Selecciona desde qué país nos estás visitando para mostrarte los precios y costos de envío en tu moneda local.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[60vh] overflow-y-auto p-1">
                @foreach($modalCountries as $code => $c)
                    <button type="button" onclick="usSwitchCountry('{{ $code }}', '{{ $c['currency'] }}')"
                            class="flex flex-col items-center justify-center gap-2.5 p-3.5 sm:p-4 rounded-xl border-2 border-stone-100 hover:border-[var(--t-primary)] transition-all group cursor-pointer shadow-xs hover:shadow-md bg-white">
                        <div class="w-14 h-9 sm:w-16 sm:h-10 rounded-md overflow-hidden border border-stone-200/80 shadow-xs flex items-center justify-center bg-stone-100">
                            <img src="{{ $c['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($code) }}" alt="{{ $c['name'] }}" class="w-full h-full object-cover">
                        </div>
                        <div class="text-center">
                            <span class="font-bold text-xs text-gray-800 group-hover:text-[var(--t-primary)] block leading-tight">{{ $c['name'] }}</span>
                            <span class="text-[10px] text-gray-500 font-mono mt-0.5 block">{{ $c['currency'] }} ({{ $c['symbol'] }})</span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <script>
        document.addEventListener('alpine:init', () => {
            // Carrusel de banners. Cuenta solo los banners visibles, así la vista previa del
            // dashboard puede prender/apagar banners en vivo sin recargar.
            Alpine.data('usHero', ({ autoplay }) => ({
                index: 0, count: 0, timer: null, paused: false, touchX: null,
                init() {
                    this.refresh();
                    document.addEventListener('tribio:template-preview-applied', () => this.refresh());
                    if (autoplay && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        this.timer = setInterval(() => { if (!this.paused && !document.hidden) this.next(); }, 6500);
                    }
                },
                destroy() { clearInterval(this.timer); },
                slides() { return [...this.$refs.track.querySelectorAll('[data-slide]:not([hidden])')]; },
                refresh() {
                    this.count = this.slides().length;
                    if (this.index >= this.count) this.index = 0;
                    this.render();
                },
                render() {
                    const slides = this.slides();
                    this.$refs.track.querySelectorAll('[data-slide]').forEach((el) => el.classList.remove('is-active'));
                    const active = slides[this.index];
                    if (!active) return;
                    active.classList.add('is-active');
                    slides.forEach((el) => el.setAttribute('aria-hidden', el === active ? 'false' : 'true'));
                    this.$el.dataset.tone = active.dataset.choice === 'dark' ? 'dark' : 'light';
                },
                go(i) { if (!this.count) return; this.index = (i + this.count) % this.count; this.render(); },
                next() { this.go(this.index + 1); },
                prev() { this.go(this.index - 1); },
                pause() { this.paused = true; },
                resume() { this.paused = false; },
                touchStart(e) { this.touchX = e.changedTouches[0].clientX; },
                touchEnd(e) {
                    if (this.touchX === null) return;
                    const dx = e.changedTouches[0].clientX - this.touchX;
                    if (Math.abs(dx) > 40) dx < 0 ? this.next() : this.prev();
                    this.touchX = null;
                },
            }));

            // Carrusel horizontal de productos con flechas que se ocultan en los extremos.
            Alpine.data('usRail', () => ({
                atStart: true, atEnd: false,
                init() { this.$nextTick(() => this.update()); window.addEventListener('resize', () => this.update(), { passive: true }); },
                update() {
                    const track = this.$refs.track;
                    if (!track) return;
                    this.atStart = track.scrollLeft <= 4;
                    this.atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
                },
                scroll(direction) {
                    const track = this.$refs.track;
                    track.scrollBy({ left: direction * track.clientWidth * 0.9, behavior: 'smooth' });
                },
            }));

            // Pop-up promocional: aparece una vez por sesión, a los pocos segundos.
            Alpine.data('usPopup', ({ auto, key }) => ({
                open: false, copied: false,
                init() {
                    if (!auto) return;
                    let seen = false;
                    try { seen = sessionStorage.getItem(key) === '1'; } catch (e) {}
                    if (!seen) setTimeout(() => this.show(), 2500);
                },
                show() { this.open = true; },
                close() {
                    this.open = false;
                    try { sessionStorage.setItem(key, '1'); } catch (e) {}
                },
                copy(event) {
                    const code = event.currentTarget.querySelector('[data-tpl-text]').textContent.trim();
                    if (navigator.clipboard) navigator.clipboard.writeText(code).catch(() => {});
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 1800);
                },
            }));
        });
    </script>
</body>
</html>
