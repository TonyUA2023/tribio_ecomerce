@extends('templates.textil-pro.layout')

@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $t = $storefrontTheme;
    $images = $product->all_images;
    $price = (float) $product->resolvePrice();
    $compare = (float) ($product->resolveComparePrice() ?? 0);
    $symbol = \App\Helpers\CurrencyHelper::symbol();
    $sizeNames = \App\Services\Storefront\CatalogFacets::SIZE_NAMES;
    $sizeGuide = collect(preg_split('/\r\n|\r|\n|;/', $t->text('product.size_guide')))->map(fn ($line) => trim($line))->filter()->values();
    $madeToOrder = $product->isMadeToOrder($store);
    // Por mayor: escalas por cantidad total del producto y pedido mínimo (se cobran en el checkout).
    $wholesaleReady = \App\Services\Pricing\WholesalePricing::ready();
    $wholesaleTiers = $wholesaleReady ? \App\Services\Pricing\WholesalePricing::tiers($product) : [];
    $wholesaleMin = $wholesaleReady ? \App\Services\Pricing\WholesalePricing::minQuantity($product) : 1;
    $basePen = (float) $product->price;
    $tierRows = collect($wholesaleTiers)->map(fn ($tier) => [
        'min' => $tier['min_qty'],
        'unit' => $basePen > 0 ? round($price * $tier['price'] / $basePen, 2) : $tier['price'],
        'off' => $basePen > 0 ? (int) round((1 - $tier['price'] / $basePen) * 100) : 0,
    ])->values();
    $productConfig = [
        'hasVariants' => (bool) $product->has_variants,
        'minQuantity' => $wholesaleMin,
        'trackStock' => (bool) $product->track_stock,
        'basePrice' => $price,
        'baseComparePrice' => $compare,
        'baseStock' => (int) ($product->stock ?? 0),
        'outOfStockMessage' => $product->out_of_stock_message,
        'currencySymbol' => $symbol,
        'options' => array_values($product->variant_options ?? []),
        'sizeNames' => $sizeNames,
        'variants' => $product->activeVariants->map(fn ($v) => [
            'id' => $v->id,
            'title' => $v->title,
            'attributes' => $v->attributes ?? [],
            'sku' => $v->sku,
            'price' => (float) ($v->resolvePrice() ?? 0),
            'compare_price' => (float) ($v->resolveComparePrice() ?? 0),
            'stock' => (int) $v->stock,
            // Solo la foto propia de la variante (image_url cae a la del producto y repetiría la misma en cada color).
            'image' => $v->image_path ? $v->image_url : null,
        ])->values(),
        'product' => ['id' => $product->id, 'name' => $product->name, 'image' => $images[0]],
        'images' => $images,
        'texts' => [
            'chooseSize' => $isEn ? 'Choose your size' : 'Selecciona tu talla',
            'chooseOption' => $isEn ? 'Choose an option' : 'Elige una opción',
            'add' => $isEn ? 'Add to cart' : 'Agregar al carrito',
            'soldOut' => $isEn ? 'Sold out' : 'Agotado',
        ],
    ];
    $specs = array_filter([
        ($isEn ? 'Brand' : 'Marca') => optional($product->brand)->name,
        'SKU' => $product->sku,
        ($isEn ? 'Category' : 'Categoría') => $product->categories && $product->categories->isNotEmpty()
            ? $product->categories->map(fn ($c) => $c->getTranslatedName())->implode(', ')
            : optional($product->category)->getTranslatedName(),
    ]);
@endphp

@section('title', $product->name . ' | ' . $store->name)

@section('content')
<div class="tx-narrow" x-data="txProduct(@js($productConfig))">
    <nav class="tx-crumbs" aria-label="{{ $isEn ? 'Breadcrumb' : 'Ruta' }}" style="padding-bottom: 18px">
        <a href="{{ route('store.show', $store->slug) }}">{{ $isEn ? 'Home' : 'Inicio' }}</a>
        <i aria-hidden="true"></i>
        @if($product->category)
            <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $product->category->slug]) }}">{{ $product->category->getTranslatedName() }}</a>
            <i aria-hidden="true"></i>
        @else
            <a href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'Shop' : 'Catálogo' }}</a>
            <i aria-hidden="true"></i>
        @endif
        <span>{{ $product->name }}</span>
    </nav>

    <div class="tx-pdp">
        {{-- Galería: grilla en computadora, deslizable en celular --}}
        <div>
            <div class="tx-gallery" x-ref="gallery" @scroll.passive.debounce.50ms="slide = Math.round($el.scrollLeft / $el.clientWidth)">
                @foreach($images as $index => $imageUrl)
                    <button type="button" class="tx-gallery-item {{ count($images) % 2 === 0 || $index > 0 ? '' : 'is-wide' }}" @click="zoom = {{ $index === 0 ? 'mainImage' : Js::from($imageUrl) }}" aria-label="{{ $isEn ? 'Zoom image' : 'Ampliar imagen' }} {{ $index + 1 }}">
                        @if($index === 0)
                            <img :src="mainImage" src="{{ $imageUrl }}" alt="{{ $product->name }}">
                        @else
                            <img src="{{ $imageUrl }}" alt="{{ $product->name }} — {{ $index + 1 }}" loading="lazy">
                        @endif
                        @if($index === 0 && $compare > $price && $compare > 0)
                            <span class="tx-badge">-{{ round((($compare - $price) / $compare) * 100) }}%</span>
                        @endif
                    </button>
                @endforeach
                @if($product->video_path)
                    <div class="tx-gallery-item" style="cursor: default">
                        <video src="{{ $product->video_url }}" autoplay loop muted playsinline preload="metadata"></video>
                    </div>
                @endif
            </div>
            @php $slideCount = count($images) + ($product->video_path ? 1 : 0); @endphp
            @if($slideCount > 1)
                <div class="tx-gallery-dots" aria-hidden="true">
                    @for($d = 0; $d < $slideCount; $d++)<i :class="{ 'is-active': slide === {{ $d }} }"></i>@endfor
                </div>
            @endif
        </div>

        {{-- Compra --}}
        <div class="tx-buybox">
            <div>
                <span class="tx-buybox-kicker">{{ optional($product->brand)->name ?: optional($product->category)->getTranslatedName() ?: $store->name }}</span>
                <h1>{{ $product->name }}</h1>
            </div>

            <div class="tx-price" aria-live="polite">
                <span :class="{ 'is-sale': currentComparePrice > currentPrice }" x-text="money(currentPrice)" class="{{ $compare > $price ? 'is-sale' : '' }}">{{ $symbol }} {{ number_format($price, 2) }}</span>
                <s x-show="currentComparePrice > currentPrice" x-text="money(currentComparePrice)" @if(!($compare > $price)) style="display: none" @endif>{{ $symbol }} {{ number_format($compare, 2) }}</s>
                <span class="tx-tag" x-show="currentComparePrice > currentPrice" x-cloak x-text="'-' + Math.round((1 - currentPrice / currentComparePrice) * 100) + '%'"></span>
            </div>

            @if(($reviewSection['summary']['count'] ?? 0) > 0)
                <a class="tx-rating" href="#resenas">
                    <span style="letter-spacing: .08em; background: linear-gradient(90deg, #111 {{ round($reviewSection['summary']['average'] / 5 * 100, 1) }}%, #CFCFCF {{ round($reviewSection['summary']['average'] / 5 * 100, 1) }}%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent" aria-hidden="true">★★★★★</span>
                    <strong style="color: var(--tx-ink)">{{ number_format($reviewSection['summary']['average'], 1) }}</strong>
                    ({{ $reviewSection['summary']['count'] }} {{ $isEn ? 'reviews' : ($reviewSection['summary']['count'] === 1 ? 'reseña' : 'reseñas') }})
                </a>
            @endif

            {{-- Opciones: colores como muestras, tallas como cajas (tachadas si no hay stock) --}}
            <template x-for="opt in options" :key="opt.name">
                <div class="tx-opt-block">
                    <div class="tx-opt-head">
                        <span><strong x-text="opt.name"></strong><span x-show="selected[opt.name]" x-text="': ' + (selected[opt.name] || '')"></span></span>
                        @if($sizeGuide->isNotEmpty())
                            <button type="button" x-show="isSize(opt.name)" @click="guideOpen = true">{{ $isEn ? 'Size guide' : 'Guía de tallas' }}</button>
                        @endif
                    </div>
                    <div :class="isSize(opt.name) ? 'tx-sizes' : 'tx-swatches'">
                        <template x-for="val in opt.values" :key="val">
                            <button type="button" @click="choose(opt.name, val)"
                                    :class="(isSize(opt.name) ? 'tx-size' : 'tx-swatch') + (selected[opt.name] === val ? ' is-active' : '') + (available(opt.name, val) ? '' : ' is-out')"
                                    :aria-pressed="(selected[opt.name] === val).toString()"
                                    :aria-label="opt.name + ' ' + val + (available(opt.name, val) ? '' : ' — {{ $isEn ? 'sold out' : 'agotado' }}')">
                                <template x-if="!isSize(opt.name) && imageFor(opt.name, val)"><img :src="imageFor(opt.name, val)" alt=""></template>
                                <span x-text="val"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
            <p class="tx-hint" x-show="hint" x-text="hint" x-cloak role="alert"></p>

            @if($tierRows->isNotEmpty() || $wholesaleMin > 1)
                {{-- Precio por cantidad: se suman todas las tallas y colores de este producto --}}
                <div class="tx-tierbox">
                    <p class="tx-tierbox-title">{{ $isEn ? 'Price per quantity' : 'Precio por cantidad' }}</p>
                    @if($tierRows->isNotEmpty())
                        <ul>
                            @if($tierRows[0]['min'] > $wholesaleMin)
                            <li :class="{ 'is-on': quantity < {{ $tierRows[0]['min'] }} }">
                                <span>{{ $wholesaleMin }}{{ $tierRows[0]['min'] - 1 > $wholesaleMin ? ' – ' . ($tierRows[0]['min'] - 1) : '' }} {{ $isEn ? 'units' : 'unid.' }}</span>
                                <strong>{{ $symbol }} {{ number_format($price, 2) }}</strong>
                            </li>
                            @endif
                            @foreach($tierRows as $i => $row)
                                @php $next = $tierRows[$i + 1]['min'] ?? null; @endphp
                                <li :class="{ 'is-on': quantity >= {{ $row['min'] }}{{ $next ? ' && quantity < ' . $next : '' }} }">
                                    <span>{{ $next ? $row['min'] . ' – ' . ($next - 1) : ($isEn ? $row['min'] . '+' : 'Desde ' . $row['min']) }} {{ $isEn ? 'units' : 'unid.' }}</span>
                                    <strong>{{ $symbol }} {{ number_format($row['unit'], 2) }} @if($row['off'] > 0)<em>-{{ $row['off'] }}%</em>@endif</strong>
                                </li>
                            @endforeach
                        </ul>
                        <p class="tx-tierbox-note">{{ $isEn ? 'All sizes and colors of this product add up.' : 'Se suman todas las tallas y colores de este producto.' }}</p>
                    @endif
                    @if($wholesaleMin > 1)
                        <p class="tx-tierbox-min">{{ $isEn ? 'Minimum order' : 'Pedido mínimo' }}: <strong>{{ $wholesaleMin }} {{ $isEn ? 'units' : 'unidades' }}</strong></p>
                    @endif
                </div>
            @endif

            @if($madeToOrder)
                {{-- Hecho a pedido: el comprador personaliza antes de añadir al carrito --}}
                @include('templates.textil-pro._made-to-order')
            @else
                <div>
                    @if($product->track_stock && !$product->out_of_stock_message)
                        <p class="tx-stock" :class="{ 'is-out': isOutOfStock, 'is-warn': !isOutOfStock && stockLeft > 0 && stockLeft <= 3 }" style="margin: 0 0 12px">
                            <i aria-hidden="true"></i>
                            <span x-text="isOutOfStock ? '{{ $isEn ? 'Sold out' : 'Agotado' }}' : (stockLeft > 0 && stockLeft <= 3 ? '{{ $isEn ? 'Only' : 'Solo quedan' }} ' + stockLeft : '{{ $isEn ? 'In stock' : 'Disponible' }}')"></span>
                        </p>
                    @elseif($product->out_of_stock_message && $product->stock <= 0)
                        <p class="tx-stock is-warn" style="margin: 0 0 12px"><i aria-hidden="true"></i>{{ $product->out_of_stock_message }}</p>
                    @endif
                    <div class="tx-buy-row" x-ref="buy">
                        <div class="tx-qty">
                            <button type="button" @click="quantity = Math.max(minQuantity || 1, quantity - 1)" aria-label="{{ $isEn ? 'Less' : 'Menos' }}">−</button>
                            <input type="number" min="{{ $wholesaleMin }}" x-model.number="quantity" aria-label="{{ $isEn ? 'Quantity' : 'Cantidad' }}">
                            <button type="button" @click="quantity++" aria-label="{{ $isEn ? 'More' : 'Más' }}">+</button>
                        </div>
                        <button type="button" class="tx-btn is-block" @click="addToCart($event)" :disabled="isOutOfStock" x-text="buttonLabel">{{ $productConfig['texts']['add'] }}</button>
                    </div>
                </div>
            @endif

            @if($t->text('product.shipping_note') !== '')
                <p class="tx-note" style="margin: 0">
                    <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="17.5" cy="17.5" r="1.5"/></svg>
                    <span>{{ $t->text('product.shipping_note') }}</span>
                </p>
            @endif

            <div>
                <details class="tx-acc" open>
                    <summary>{{ $isEn ? 'Description' : 'Descripción' }}</summary>
                    <div class="tx-acc-body" style="white-space: pre-line">{{ $product->description ?: ($product->short_description ?: ($isEn ? 'No description yet.' : 'Sin descripción por ahora.')) }}</div>
                </details>
                @if($specs)
                <details class="tx-acc">
                    <summary>{{ $isEn ? 'Details' : 'Detalles' }}</summary>
                    <div class="tx-acc-body">
                        <dl class="tx-specs">
                            @foreach($specs as $label => $value)<dt>{{ $label }}</dt><dd>{{ $value }}</dd>@endforeach
                        </dl>
                    </div>
                </details>
                @endif
                @if($t->text('product.returns') !== '')
                <details class="tx-acc">
                    <summary>{{ $isEn ? 'Shipping & returns' : 'Envíos, cambios y devoluciones' }}</summary>
                    <div class="tx-acc-body">{{ $t->text('product.returns') }}</div>
                </details>
                @endif
            </div>
        </div>
    </div>

    {{-- Barra de compra fija en celular: aparece cuando el botón principal sale de pantalla --}}
    @unless($madeToOrder)
    <div class="tx-mobile-buy" :class="{ 'is-visible': showSticky }" aria-hidden="true">
        <strong x-text="money(currentPrice)">{{ $symbol }} {{ number_format($price, 2) }}</strong>
        <button type="button" class="tx-btn" tabindex="-1" @click="stickyBuy($event)" :disabled="isOutOfStock" x-text="buttonLabel">{{ $productConfig['texts']['add'] }}</button>
    </div>
    @endunless

    {{-- Guía de tallas --}}
    @if($sizeGuide->isNotEmpty())
    <div class="tx-modal" x-show="guideOpen" x-cloak @click.self="guideOpen = false" @keydown.escape.window="guideOpen = false" role="dialog" aria-modal="true" aria-labelledby="tx-guide-title">
        <div class="tx-modal-card">
            <button type="button" class="tx-modal-close" @click="guideOpen = false" aria-label="{{ $isEn ? 'Close' : 'Cerrar' }}"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
            <h2 id="tx-guide-title">{{ $isEn ? 'Size guide' : 'Guía de tallas' }}</h2>
            <ul class="tx-guide-list">@foreach($sizeGuide as $line)<li>{{ $line }}</li>@endforeach</ul>
        </div>
    </div>
    @endif

    {{-- Zoom de imagen --}}
    <div class="tx-zoom" x-show="zoom" x-cloak @click="zoom = null" @keydown.escape.window="zoom = null" role="dialog" aria-modal="true" aria-label="{{ $isEn ? 'Image' : 'Imagen' }}">
        <img :src="zoom" alt="{{ $product->name }}">
        <button type="button" class="tx-modal-close" aria-label="{{ $isEn ? 'Close' : 'Cerrar' }}"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg></button>
    </div>
</div>

<div class="tx-narrow" style="padding-bottom: 20px">
    @include('components.storefront.reviews', ['variant' => 'industrial'])
</div>

@if($relatedProducts->count() > 0)
    <section class="tx-section">
        <div class="tx-narrow">
            <h2 class="tx-h2 is-left">{{ $isEn ? 'You may also like' : 'También te puede gustar' }}</h2>
            @include('templates.textil-pro._rail', ['products' => $relatedProducts])
        </div>
    </section>
@endif
@endsection

@push('scripts')
@if($tierRows->isNotEmpty() || $wholesaleMin > 1)
<script>
    // El carrito re-calcula el precio por unidad con estas escalas (TribioCart.applyWholesale).
    window.TribioWholesale = window.TribioWholesale || {};
    window.TribioWholesale[{{ $product->id }}] = @json(['base' => $basePen, 'tiers' => $wholesaleTiers, 'min' => $wholesaleMin]);
</script>
@endif
<script>
    document.addEventListener('alpine:init', () => {
        // Misma API que las demás plantillas (currentPrice, currentVariant, quantity, addToCart):
        // el formulario de "hecho a pedido" la lee desde adentro.
        Alpine.data('txProduct', (config) => ({
            ...config,
            selected: {},
            quantity: config.minQuantity || 1,
            hint: '',
            zoom: null,
            guideOpen: false,
            slide: 0,
            showSticky: false,
            init() {
                // Colores y demás opciones empiezan en su primer valor disponible; la talla la elige el cliente.
                this.options.forEach((opt) => {
                    if (this.isSize(opt.name) || !opt.values || !opt.values.length) return;
                    this.selected[opt.name] = opt.values.find((v) => this.available(opt.name, v)) ?? opt.values[0];
                });
                const buy = this.$refs.buy;
                if (buy && 'IntersectionObserver' in window) {
                    new IntersectionObserver(([entry]) => {
                        this.showSticky = !entry.isIntersecting && entry.boundingClientRect.top < 0;
                        document.body.classList.toggle('tx-has-mobile-buy', this.showSticky);
                    }).observe(buy);
                }
            },
            isSize(name) { return this.sizeNames.includes(String(name).trim().toLowerCase()); },
            money(value) { return this.currencySymbol + ' ' + Number(value || 0).toFixed(2); },
            matches(v, overrides = {}) {
                const wanted = { ...this.selected, ...overrides };
                return Object.entries(wanted).every(([key, val]) => val === undefined || (v.attributes || {})[key] === val);
            },
            available(name, val) {
                const candidates = this.variants.filter((v) => (v.attributes || {})[name] === val && this.matches(v, { [name]: val }));
                if (!candidates.length) return false;
                if (!this.trackStock || this.outOfStockMessage) return true;
                return candidates.some((v) => v.stock > 0);
            },
            imageFor(name, val) {
                const v = this.variants.find((x) => (x.attributes || {})[name] === val && x.image);
                return v ? v.image : null;
            },
            choose(name, val) {
                this.selected[name] = this.selected[name] === val && this.isSize(name) ? undefined : val;
                this.hint = '';
            },
            get currentVariant() {
                if (!this.hasVariants || !this.variants.length) return null;
                if (this.options.some((opt) => !this.selected[opt.name])) return null;
                return this.variants.find((v) => this.matches(v)) || null;
            },
            get missingOption() { return this.hasVariants ? this.options.find((opt) => !this.selected[opt.name]) || null : null; },
            get mainImage() {
                const variant = this.currentVariant || this.variants.find((v) => v.image && this.matches(v));
                return (variant && variant.image) || this.images[0];
            },
            get currentPrice() { return this.currentVariant && this.currentVariant.price > 0 ? this.currentVariant.price : this.basePrice; },
            get currentComparePrice() { return this.currentVariant && this.currentVariant.compare_price > 0 ? this.currentVariant.compare_price : this.baseComparePrice; },
            get stockLeft() { return this.hasVariants ? (this.currentVariant ? this.currentVariant.stock : 0) : this.baseStock; },
            get isOutOfStock() {
                if (this.outOfStockMessage || !this.trackStock) return false;
                if (this.hasVariants) return !!this.currentVariant && this.currentVariant.stock <= 0;
                return this.baseStock <= 0;
            },
            get buttonLabel() {
                if (this.isOutOfStock) return this.texts.soldOut;
                if (this.missingOption) return this.isSize(this.missingOption.name) ? this.texts.chooseSize : this.texts.chooseOption;
                return this.texts.add;
            },
            addToCart(event = null) {
                if (this.missingOption) {
                    this.hint = (this.isSize(this.missingOption.name) ? this.texts.chooseSize : this.texts.chooseOption) + ': ' + this.missingOption.name;
                    return;
                }
                if (this.isOutOfStock || !window.TribioCart) return;
                const v = this.currentVariant;
                const variant = v ? { id: v.id, title: v.title, attributes: v.attributes, sku: v.sku } : null;
                const qty = Math.max(this.minQuantity || 1, parseInt(this.quantity, 10) || 1);
                for (let i = 0; i < qty; i++) {
                    window.TribioCart.add(this.product.id, this.product.name, this.currentPrice, (v && v.image) || this.product.image, variant, i === 0 ? (event && event.currentTarget) : null);
                }
                window.dispatchEvent(new CustomEvent('open-cart-drawer'));
            },
            stickyBuy(event) {
                if (this.missingOption) {
                    this.$refs.buy.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                this.addToCart(event);
            },
            buyNow(event = null) { this.addToCart(event); },
        }));
    });
</script>
@endpush
