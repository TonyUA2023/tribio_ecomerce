@extends('templates.sport-pro.layout')

@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $tplPreview = $templatePreview ?? false;
    $t = $storefrontTheme;
    $priced = fn ($q) => $q->where(fn ($sq) => $sq->where('price', '>', 0)->orWhere('price_usd', '>', 0));

    // ── Banners ────────────────────────────────────────────────────────
    // Sin imagen subida, los banners 1 y 2 usan las fotos "Hero" de Galería (o la portada);
    // si no hay ninguna queda un fondo oscuro con el texto: la portada nunca se ve vacía.
    $heroGallery = $store->galleryItems()->where('type', 'hero')->where('is_active', true)->orderBy('sort_order')->get()->pluck('image_url')->values();
    if ($heroGallery->isEmpty() && $store->cover_path) {
        $heroGallery = collect([$store->cover_url]);
    }
    $slides = [];
    foreach ([0, 1, 2, 3] as $i) {
        $enabled = $i === 0 || $t->enabled("hero.items.{$i}.enabled");
        if (!$enabled && !$tplPreview) {
            continue;
        }
        $desktop = $t->image("hero.items.{$i}.image");
        $mobile = $t->image("hero.items.{$i}.image_mobile");
        $fallback = $i < 2 ? ($heroGallery[$i] ?? null) : null;
        $slides[] = [
            'i' => $i, 'enabled' => $enabled, 'mobile' => $mobile, 'fallback' => $fallback,
            'src' => $desktop ?: ($mobile ?: $fallback),
            'tone' => $t->choice("hero.items.{$i}.tone"),
            'align' => $t->choice("hero.items.{$i}.align"),
            'link' => $t->link("hero.items.{$i}.link"),
        ];
    }
    $heroText = fn (int $i, string $key) => $t->text("hero.items.{$i}.{$key}");

    // ── Carrusel (destacados / nuevos / en oferta) ─────────────────────
    $railQuery = fn () => $priced($store->activeProducts())->with(['categories', 'category']);
    $railSources = [
        'featured' => fn () => $featuredProducts->isNotEmpty() ? $featuredProducts : collect($allProducts->items())->take(12),
        'newest' => fn () => $railQuery()->orderByDesc('id')->limit(12)->get(),
        'sale' => fn () => $railQuery()->whereNotNull('compare_price')->whereColumn('compare_price', '>', 'price')->orderBy('sort_order')->limit(12)->get(),
    ];
    $railSource = $t->choice('rail.source');
    // En la vista previa se arman los tres para que el selector cambie al instante.
    $rails = collect($tplPreview ? array_keys($railSources) : [$railSource])
        ->mapWithKeys(fn ($key) => [$key => $railSources[$key]()]);

    // ── Imágenes de respaldo para tarjetas y campañas ──────────────────
    $cats = $categories->where('is_featured', true);
    if ($cats->isEmpty()) {
        $cats = $categories->take(8);
    }
    $catImage = fn ($cat) => $cat->image_url ?: optional($cat->products->first())->image_url;
    $fallbackImages = $cats->map($catImage)->filter()->values()
        ->concat(collect($allProducts->items())->map(fn ($p) => $p->image_path ? $p->image_url : null)->filter())
        ->values();

    $popularProducts = collect($allProducts->items());
@endphp

@section('title', $store->name . ' - Tienda Online')

@push('preload')
    @if(!empty($slides[0]['src']))
        <link rel="preload" as="image" href="{{ $slides[0]['src'] }}">
    @endif
@endpush

@section('content')
    <h1 class="sp-sr">{{ $store->name }}</h1>

    {{-- ═════ Banners ═════ --}}
    <section class="sp-hero" x-data="spHero({ autoplay: {{ !$tplPreview && $t->enabled('hero.autoplay') ? 'true' : 'false' }} })"
             data-tpl-choice="hero.height" data-choice="{{ $t->choice('hero.height') }}" data-tone="{{ $slides[0]['tone'] ?? 'light' }}"
             aria-roledescription="carrusel" aria-label="{{ $isEn ? 'Featured campaigns' : 'Campañas destacadas' }}"
             @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false">
        <div class="sp-hero-track" x-ref="track" @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)">
            @foreach($slides as $slide)
                @php $i = $slide['i']; @endphp
                <div class="sp-slide {{ $loop->first ? 'is-active' : '' }}" data-slide data-tpl-img-host
                     data-tpl-choice="hero.items.{{ $i }}.tone" data-choice="{{ $slide['tone'] }}"
                     @if($slide['src']) data-has-image @endif
                     @if($i > 0) data-tpl-show="hero.items.{{ $i }}.enabled" @unless($slide['enabled']) hidden @endunless @endif
                     role="group" aria-roledescription="banner">
                    <div class="sp-slide-bg">
                        <picture>
                            <source media="{{ $slide['mobile'] ? '(max-width: 767px)' : 'not all' }}" data-media="(max-width: 767px)"
                                    srcset="{{ $slide['mobile'] ?? '' }}" data-tpl-img="hero.items.{{ $i }}.image_mobile">
                            <img src="{{ $slide['src'] ?? '' }}" alt="" data-tpl-img="hero.items.{{ $i }}.image" data-tpl-img-fallback="{{ $slide['fallback'] ?? '' }}"
                                 @if($loop->first) fetchpriority="high" @else loading="lazy" @endif @unless($slide['src']) hidden @endunless>
                        </picture>
                    </div>
                    <div class="sp-slide-scrim" data-tpl-choice="hero.items.{{ $i }}.align" data-choice="{{ $slide['align'] }}"></div>
                    @if($slide['link'])
                        <a class="sp-slide-link" href="{{ $slide['link'] }}" aria-label="{{ $heroText($i, 'title') ?: $store->name }}"></a>
                    @endif
                    <div class="sp-slide-copy sp-wrap" data-tpl-choice="hero.items.{{ $i }}.align" data-choice="{{ $slide['align'] }}">
                        <div class="sp-copy">
                            <h2 class="sp-hero-title" data-tpl-text="hero.items.{{ $i }}.title" data-tpl-hide-empty @if($heroText($i, 'title') === '') hidden @endif>{{ $heroText($i, 'title') }}</h2>
                            <p class="sp-hero-sub" data-tpl-text="hero.items.{{ $i }}.subtitle" data-tpl-hide-empty @if($heroText($i, 'subtitle') === '') hidden @endif>{{ $heroText($i, 'subtitle') }}</p>
                            <a class="sp-btn is-white sp-hero-cta" @if($slide['link']) href="{{ $slide['link'] }}" @endif data-tpl-text="hero.items.{{ $i }}.cta" data-tpl-hide-empty @if($heroText($i, 'cta') === '') hidden @endif>{{ $heroText($i, 'cta') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <button type="button" class="sp-hero-arrow is-prev" x-show="count > 1" @click="go(index - 1)" aria-label="{{ $isEn ? 'Previous banner' : 'Banner anterior' }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 6-6 6 6 6"/></svg>
        </button>
        <button type="button" class="sp-hero-arrow is-next" x-show="count > 1" @click="go(index + 1)" aria-label="{{ $isEn ? 'Next banner' : 'Banner siguiente' }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
        </button>
        <div class="sp-hero-dots" x-show="count > 1">
            <template x-for="n in count" :key="n">
                <button type="button" :class="{ 'is-active': n - 1 === index }" @click="go(n - 1)" :aria-label="'{{ $isEn ? 'Go to banner' : 'Ir al banner' }} ' + n"></button>
            </template>
        </div>
    </section>

    {{-- Promociones automáticas de la tienda (envío gratis, descuento por volumen) --}}
    @php $storePromos = $store->activePromoMessages($isEn); @endphp
    @if(count($storePromos) > 0)
        <div class="sp-promos"><div class="sp-wrap">@foreach($storePromos as $promo)<span>{{ $promo['icon'] }} {{ $promo['text'] }}</span>@endforeach</div></div>
    @endif

    {{-- ═════ Carrusel de productos ═════ --}}
    @if($rails->contains(fn ($items) => $items->isNotEmpty()) && ($tplPreview || $t->enabled('rail.enabled')))
    <section class="sp-section is-tight" data-tpl-show="rail.enabled" @unless($t->enabled('rail.enabled')) hidden @endunless>
        <div class="sp-narrow sp-rails" data-tpl-choice="rail.source" data-choice="{{ $railSource }}">
            <h2 class="sp-h2 is-left" data-tpl-text="rail.title" data-tpl-hide-empty @if($t->text('rail.title') === '') hidden @endif>{{ $t->text('rail.title') }}</h2>
            @foreach($rails as $source => $railProducts)
                <div class="sp-rail-src" data-src="{{ $source }}">
                    @if($railProducts->isEmpty())
                        <p style="color: var(--sp-muted); padding: 20px 0">{{ $isEn ? 'No products here yet.' : 'Aún no hay productos para mostrar aquí.' }}</p>
                    @else
                        @include('templates.sport-pro._rail', ['products' => $railProducts])
                    @endif
                </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ═════ Lo nuevo ═════ --}}
    @if($tplPreview || $t->enabled('tiles.enabled'))
    <section class="sp-section" data-tpl-show="tiles.enabled" @unless($t->enabled('tiles.enabled')) hidden @endunless>
        <div class="sp-narrow">
            <h2 class="sp-h2" data-tpl-text="tiles.title" data-tpl-hide-empty @if($t->text('tiles.title') === '') hidden @endif>{{ $t->text('tiles.title') }}</h2>
            <div class="sp-tiles">
                @foreach([0, 1, 2] as $k)
                    @php
                        $tileImage = $t->image("tiles.items.{$k}.image");
                        $tileFallback = $fallbackImages[$k] ?? null;
                        $tileLink = $t->link("tiles.items.{$k}.link");
                    @endphp
                    <a class="sp-tile" @if($tileLink) href="{{ $tileLink }}" @endif data-tpl-img-host>
                        <span class="sp-tile-fallback" aria-hidden="true"></span>
                        <img src="{{ $tileImage ?: $tileFallback }}" alt="" loading="lazy" data-tpl-img="tiles.items.{{ $k }}.image" data-tpl-img-fallback="{{ $tileFallback }}" @unless($tileImage ?: $tileFallback) hidden @endunless>
                        <span class="sp-tile-copy">
                            <h3 data-tpl-text="tiles.items.{{ $k }}.title">{{ $t->text("tiles.items.{$k}.title") }}</h3>
                            <span class="sp-btn is-white" data-tpl-text="tiles.items.{{ $k }}.cta" data-tpl-hide-empty @if($t->text("tiles.items.{$k}.cta") === '') hidden @endif>{{ $t->text("tiles.items.{$k}.cta") }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═════ Campañas ═════ --}}
    @if($tplPreview || $t->enabled('campaigns.enabled'))
    <section class="sp-section sp-campaigns-wrap" data-tpl-show="campaigns.enabled" @unless($t->enabled('campaigns.enabled')) hidden @endunless>
        <div class="sp-wrap" style="display: grid; gap: 48px">
            @foreach([[0, 1], [2, 3]] as $row => $pair)
                @if($row === 0 || $tplPreview || $t->enabled('campaigns.second_row'))
                <div class="sp-campaigns" @if($row === 1) data-tpl-show="campaigns.second_row" @unless($t->enabled('campaigns.second_row')) hidden @endunless @endif>
                    @foreach($pair as $k)
                        @php
                            $cImage = $t->image("campaigns.items.{$k}.image");
                            $cFallback = $fallbackImages[$k + 3] ?? ($fallbackImages[$k] ?? null);
                            $cLink = $t->link("campaigns.items.{$k}.link");
                        @endphp
                        <div class="sp-campaign">
                            <a class="sp-campaign-media" @if($cLink) href="{{ $cLink }}" @endif data-tpl-img-host aria-label="{{ $t->text("campaigns.items.{$k}.title") ?: $store->name }}">
                                <span class="sp-tile-fallback" aria-hidden="true"></span>
                                <img src="{{ $cImage ?: $cFallback }}" alt="" loading="lazy" data-tpl-img="campaigns.items.{{ $k }}.image" data-tpl-img-fallback="{{ $cFallback }}" @unless($cImage ?: $cFallback) hidden @endunless>
                            </a>
                            <h3 data-tpl-text="campaigns.items.{{ $k }}.title" data-tpl-hide-empty @if($t->text("campaigns.items.{$k}.title") === '') hidden @endif>{{ $t->text("campaigns.items.{$k}.title") }}</h3>
                            <p data-tpl-text="campaigns.items.{{ $k }}.subtitle" data-tpl-hide-empty @if($t->text("campaigns.items.{$k}.subtitle") === '') hidden @endif>{{ $t->text("campaigns.items.{$k}.subtitle") }}</p>
                            <a class="sp-btn" @if($cLink) href="{{ $cLink }}" @endif data-tpl-text="campaigns.items.{{ $k }}.cta" data-tpl-hide-empty @if($t->text("campaigns.items.{$k}.cta") === '') hidden @endif>{{ $t->text("campaigns.items.{$k}.cta") }}</a>
                        </div>
                    @endforeach
                </div>
                @endif
            @endforeach
        </div>
    </section>
    @endif

    {{-- ═════ Categorías ═════ --}}
    @if($cats->isNotEmpty() && ($tplPreview || $t->enabled('categories.enabled')))
    <section class="sp-section" data-tpl-show="categories.enabled" @unless($t->enabled('categories.enabled')) hidden @endunless>
        <div class="sp-narrow">
            <h2 class="sp-h2" data-tpl-text="categories.title" data-tpl-hide-empty @if($t->text('categories.title') === '') hidden @endif>{{ $t->text('categories.title') }}</h2>
            <div class="sp-cats">
                @foreach($cats as $cat)
                    @php $image = $catImage($cat); @endphp
                    <a class="sp-cat" href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}">
                        <span class="sp-cat-media">
                            @if($image)<img src="{{ $image }}" alt="" loading="lazy">@else<span class="sp-tile-fallback" aria-hidden="true"></span>@endif
                        </span>
                        <strong>{{ $cat->getTranslatedName() }}</strong>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═════ Grilla ═════ --}}
    <section class="sp-section">
        <div class="sp-narrow">
            <div class="sp-head">
                <h2 class="sp-h2 is-left" data-tpl-text="sections.products_title">{{ $t->text('sections.products_title') }}</h2>
                @if($popularProducts->isNotEmpty())
                    <a class="sp-link" href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'View all' : 'Ver todo' }}</a>
                @endif
            </div>
            @if($popularProducts->isEmpty())
                <div class="sp-empty">
                    <h2>{{ $isEn ? 'Coming soon' : 'Muy pronto' }}</h2>
                    <p>{{ $isEn ? 'New products are on their way.' : 'Estamos preparando nuevos productos para ti.' }}</p>
                </div>
            @else
                <div class="sp-grid">
                    @foreach($popularProducts->take(8) as $product)
                        @include('templates.sport-pro._product-card', ['product' => $product])
                    @endforeach
                </div>
                <div style="display: flex; justify-content: center; margin-top: 44px">
                    <a class="sp-btn is-outline" href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'Shop all products' : 'Ver todos los productos' }}</a>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
<style>
    .sp-rails[data-choice="featured"] .sp-rail-src:not([data-src="featured"]),
    .sp-rails[data-choice="newest"] .sp-rail-src:not([data-src="newest"]),
    .sp-rails[data-choice="sale"] .sp-rail-src:not([data-src="sale"]) { display: none; }
</style>
<script>
    document.addEventListener('alpine:init', () => {
        // Carrusel de banners: cuenta solo los visibles, así la vista previa puede prender/apagar banners.
        Alpine.data('spHero', ({ autoplay }) => ({
            index: 0, count: 0, paused: false, touchX: null,
            init() {
                this.refresh();
                document.addEventListener('tribio:template-preview-applied', () => this.refresh());
                if (autoplay && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    setInterval(() => { if (!this.paused && !document.hidden) this.go(this.index + 1); }, 6500);
                }
            },
            slides() { return [...this.$refs.track.querySelectorAll('[data-slide]:not([hidden])')]; },
            refresh() { this.count = this.slides().length; if (this.index >= this.count) this.index = 0; this.render(); },
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
            touchStart(e) { this.touchX = e.changedTouches[0].clientX; },
            touchEnd(e) {
                if (this.touchX === null) return;
                const dx = e.changedTouches[0].clientX - this.touchX;
                if (Math.abs(dx) > 40) this.go(this.index + (dx < 0 ? 1 : -1));
                this.touchX = null;
            },
        }));
    });
</script>
@endpush
