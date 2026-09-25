@extends('layouts.public')
@section('title', 'Negocios en Tribio — busca productos y compra directo a cada tienda')
@section('meta_description', 'Busca entre los productos de todos los negocios que venden con Tribio, mira sus tiendas en vivo y compra directo a quien lo vende.')

@push('head')
    @vite('resources/css/directory.css')
    <script>
        // A missing upload must never show a broken-image icon: drop it and let CSS draw
        // the fallback (store color + initial) from the container's data-initial.
        window.dxBroken = function (img) {
            const box = img.parentElement;
            img.remove();
            box && box.classList.add('is-broken');
        };
    </script>
    @if($query !== '' || $category)
        <meta name="robots" content="noindex, follow">
    @endif
@endpush

@php
    $stats = $home['stats'];
    $wall = $home['wall'];
    $wallColumns = [[], [], []];
    foreach ($wall as $i => $tile) {
        $wallColumns[$i % 3][] = $tile;
    }
    $showWall = count($wall) >= 6;
    $config = [
        'searchUrl' => route('directory.search'),
        'query' => $query,
        'category' => $category,
        'sort' => $sort,
        'results' => $results,
        'stores' => $home['stores'],
        'categories' => $home['categories'],
    ];
@endphp

@section('content')
<main class="tribio-dir" x-data="tribioDirectory(@js($config))" @keydown.window="shortcut($event)">

    {{-- ─── Hero: one search box for every Tribio store ─── --}}
    <section class="dx-hero" :class="searching && 'is-searching'" aria-labelledby="dx-title">
        <div class="dx-shell dx-hero-grid {{ $showWall ? '' : 'is-solo' }}">
            <div>
                <div class="dx-live"><span class="dx-live-dot" aria-hidden="true"></span> Vitrina Tribio · tiendas reales, en vivo</div>
                <h1 id="dx-title">Todo lo que venden los negocios Tribio, <span>en un solo buscador.</span></h1>
                <p class="dx-lead">Encuentra el producto, mira la tienda en vivo y compra directo a quien lo vende. Sin intermediarios: cada resultado te lleva a la tienda del negocio.</p>

                <form class="dx-search" action="{{ route('directory') }}" method="GET" role="search" x-ref="heroSearch" @submit.prevent="submit()">
                    <label for="dx-q" class="sr-only">Buscar productos o negocios</label>
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-5.2-5.2m2.2-5.3a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/></svg>
                    <input id="dx-q" x-ref="heroInput" type="search" name="q" value="{{ $query }}" x-model="query" @input="onType()"
                           placeholder="Busca tortas, repuestos, organizadores…" autocomplete="off" maxlength="80" enterkeyhint="search">
                    <button type="button" class="dx-search-clear" x-show="query" x-cloak @click="clear(); $refs.heroInput.focus()" aria-label="Borrar búsqueda">×</button>
                    <span class="dx-kbd" x-show="!query" aria-hidden="true">/</span>
                    <button type="submit" class="dx-search-go">Buscar</button>
                </form>

                @if(count($home['suggestions']))
                <div class="dx-try">
                    <span>Prueba:</span>
                    @foreach($home['suggestions'] as $suggestion)
                        <button type="button" @click="pick(@js($suggestion))">{{ $suggestion }}</button>
                    @endforeach
                </div>
                @endif

                <div class="dx-proof">
                    <span><b>{{ $stats['stores'] }}</b> {{ $stats['stores'] === 1 ? 'negocio' : 'negocios' }}</span>
                    <span><b>{{ $stats['products'] }}</b> productos</span>
                    <span>Compras directas con cada tienda</span>
                </div>
            </div>

            @if($showWall)
            {{-- Real products from every store, always moving: the ad space all clients share. --}}
            <div class="dx-wall" aria-hidden="true">
                @foreach($wallColumns as $column)
                <div class="dx-wall-col">
                    @foreach(array_merge($column, $column) as $tile)
                        <a class="dx-tile" href="{{ $tile['url'] }}" tabindex="-1" style="--accent: {{ $tile['store']['accent'] }}">
                            <img src="{{ $tile['image'] }}" alt="" loading="lazy" onerror="dxBroken(this)">
                            <span><strong>{{ $tile['name'] }}</strong><small><i></i>{{ $tile['store']['name'] }} · {{ $tile['price_label'] }}</small></span>
                        </a>
                    @endforeach
                </div>
                @endforeach
                <span class="dx-wall-caption"><span class="dx-live-dot"></span> En vivo desde las tiendas Tribio</span>
            </div>
            @endif
        </div>
    </section>

    {{-- ─── Sticky bar: categories + a compact search once the hero scrolls away ─── --}}
    <div class="dx-bar">
        <div class="dx-shell dx-bar-inner">
            <form class="dx-bar-search" x-show="barVisible" x-cloak role="search" @submit.prevent="submit()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-5.2-5.2m2.2-5.3a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"/></svg>
                <input x-ref="barInput" type="search" x-model="query" @input="onType()" placeholder="Buscar en Tribio…" aria-label="Buscar productos o negocios" maxlength="80">
            </form>
            <nav class="dx-chips" aria-label="Categorías de negocios">
                <button type="button" class="dx-chip" :class="!category && 'is-active'" @click="setCategory('')">Todo</button>
                @foreach($home['categories'] as $cat)
                    <button type="button" class="dx-chip" :class="category === '{{ $cat['key'] }}' && 'is-active'" @click="setCategory('{{ $cat['key'] }}')">
                        <span aria-hidden="true">{{ $cat['icon'] }}</span> {{ $cat['label'] }} <small>{{ $cat['count'] }}</small>
                    </button>
                @endforeach
            </nav>
        </div>
    </div>

    {{-- ─── Search results (live) ─── --}}
    <section class="dx-results" x-ref="results" x-show="searching" x-cloak aria-labelledby="dx-results-title">
        <div class="dx-shell">
            <div class="dx-results-head">
                <h2 id="dx-results-title" aria-live="polite" x-text="summary()"></h2>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <label for="dx-sort" class="sr-only">Ordenar resultados</label>
                    <select id="dx-sort" x-model="sort" @change="run()" x-show="results && results.total > 1">
                        <option value="relevancia">Más relevantes</option>
                        <option value="precio_asc">Menor precio</option>
                        <option value="precio_desc">Mayor precio</option>
                        <option value="ofertas">Mayor descuento</option>
                    </select>
                    <button type="button" class="dx-btn dx-btn-line" @click="clear()">Ver toda la vitrina</button>
                </div>
            </div>

            {{-- Loading --}}
            <div class="dx-grid" x-show="loading && !results">
                <template x-for="i in 8" :key="i"><div class="dx-skeleton"></div></template>
            </div>

            {{-- Error --}}
            <div class="dx-empty" x-show="failed && !loading">
                <div class="dx-empty-icon" aria-hidden="true">📡</div>
                <h3>No pudimos buscar en este momento</h3>
                <p>Revisa tu conexión e inténtalo otra vez.</p>
                <button type="button" class="dx-btn dx-btn-dark" @click="run()">Reintentar</button>
            </div>

            <template x-if="results && !failed">
                <div :style="loading ? 'opacity: .55; transition: opacity .2s' : 'transition: opacity .2s'">
                    {{-- Matching stores --}}
                    <div class="dx-results-stores" x-show="results.stores.length">
                        <template x-for="store in results.stores" :key="store.id">
                            <button type="button" class="dx-mini-store" :style="'--accent:' + store.accent" @click="openPreview(store.id)">
                                <span class="dx-avatar" :data-initial="store.initial">
                                    <template x-if="store.logo"><img :src="store.logo" alt="" x-on:error="dxBroken($el)"></template>
                                    <template x-if="!store.logo"><span x-text="store.initial"></span></template>
                                </span>
                                <span><strong x-text="store.name"></strong><small x-text="store.category_icon + ' ' + (store.category_label || 'Tienda') + ' · ' + store.products_count + ' productos'"></small></span>
                                <em>Vista previa</em>
                            </button>
                        </template>
                    </div>

                    {{-- Matching products (twin of public/directory/_product-card) --}}
                    <div class="dx-grid" x-show="results.products.length">
                        <template x-for="p in results.products" :key="p.id">
                            <article class="dx-card" :style="'--accent:' + p.store.accent">
                                <a class="dx-card-media" :href="p.url" :data-initial="p.name.charAt(0)">
                                    <template x-if="p.image"><img :src="p.image" :alt="p.name" loading="lazy" x-on:error="dxBroken($el)"></template>
                                    <template x-if="!p.image"><span class="dx-fallback" aria-hidden="true" x-text="p.name.charAt(0)"></span></template>
                                    <span class="dx-badges">
                                        <span class="dx-badge dx-badge-deal" x-show="p.discount_percent" x-text="'-' + p.discount_percent + '%'"></span>
                                        <span class="dx-badge" x-show="p.made_to_order">✂️ Hecho a pedido</span>
                                        <span class="dx-badge dx-badge-out" x-show="!p.available">Agotado</span>
                                    </span>
                                </a>
                                <div class="dx-card-body">
                                    <a class="dx-card-name" :href="p.url" x-html="highlight(p.name)"></a>
                                    <div class="dx-price"><strong x-text="p.price_label"></strong><s x-show="p.compare_label" x-text="p.compare_label"></s></div>
                                    <span class="dx-rating" x-show="p.rating">★ <span x-text="p.rating && p.rating.toFixed(1)"></span> <small x-text="'(' + p.reviews_count + ')'"></small></span>
                                    <button type="button" class="dx-card-store" @click="openPreview(p.store.id, p.store)" :aria-label="'Vista previa de la tienda ' + p.store.name">
                                        <span class="dx-avatar" :data-initial="p.store.name.charAt(0)">
                                            <template x-if="p.store.logo"><img :src="p.store.logo" alt="" x-on:error="dxBroken($el)"></template>
                                            <template x-if="!p.store.logo"><span x-text="p.store.name.charAt(0)"></span></template>
                                        </span>
                                        <span class="dx-card-store-name" x-text="p.store.name"></span>
                                        <em>Ver tienda</em>
                                    </button>
                                </div>
                            </article>
                        </template>
                    </div>

                    {{-- Nothing found --}}
                    <div class="dx-empty" x-show="!results.products.length && !results.stores.length">
                        <div class="dx-empty-icon" aria-hidden="true">🔎</div>
                        <h3 x-text="results.query ? 'Aún no hay “' + results.query + '” en Tribio' : 'Aún no hay tiendas en esta categoría'"></h3>
                        <p>Prueba con otra palabra o explora por categoría. Y si tú lo vendes, este es tu espacio: tu tienda aparece aquí apenas la creas.</p>
                        <div class="dx-empty-actions">
                            <button type="button" class="dx-btn dx-btn-line" @click="clear()">Ver toda la vitrina</button>
                            <a class="dx-btn dx-btn-dark" href="{{ route('home') }}#precios">Vender en Tribio</a>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>

    {{-- ─── Default showcase ─── --}}
    <div x-show="!searching" @if($results) style="display: none;" @endif>
        @if(count($home['showcase']))
        <section class="dx-section" aria-labelledby="dx-vitrina">
            <div class="dx-shell">
                <div class="dx-head">
                    <div>
                        <p class="dx-kicker">Vitrina del día</p>
                        <h2 id="dx-vitrina">Lo nuevo en las tiendas Tribio</h2>
                        <p>Un producto de cada negocio por turno: la vitrina rota todos los días para que cada tienda tenga su momento.</p>
                    </div>
                    <span class="dx-head-note">↻ Se renueva cada día</span>
                </div>
                <div class="dx-grid">
                    @foreach($home['showcase'] as $p)
                        @include('public.directory._product-card', ['p' => $p])
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        @if(count($home['deals']) >= 2)
        <section class="dx-section" aria-labelledby="dx-ofertas">
            <div class="dx-shell">
                <div class="dx-head">
                    <div>
                        <p class="dx-kicker">Ofertas</p>
                        <h2 id="dx-ofertas">Precios rebajados por los mismos negocios</h2>
                    </div>
                    <button type="button" class="dx-btn dx-btn-line" @click="showDeals()">Ver todas las ofertas</button>
                </div>
                <div class="dx-row">
                    @foreach($home['deals'] as $p)
                        @include('public.directory._product-card', ['p' => $p])
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        <section class="dx-section" id="negocios" aria-labelledby="dx-negocios">
            <div class="dx-shell">
                <div class="dx-head">
                    <div>
                        <p class="dx-kicker">Negocios</p>
                        <h2 id="dx-negocios">Conoce a quién le compras</h2>
                        <p>Toca una tienda para verla funcionando en vivo antes de entrar.</p>
                    </div>
                </div>
                @if(count($home['stores']))
                    <div class="dx-stores">
                        @foreach($home['stores'] as $s)
                            @include('public.directory._store-card', ['s' => $s])
                        @endforeach
                    </div>
                @else
                    <div class="dx-empty">
                        <div class="dx-empty-icon" aria-hidden="true">🏪</div>
                        <h3>Aún no hay negocios publicados</h3>
                        <p>¡Sé el primero en crear tu tienda virtual en Tribio!</p>
                        <a class="dx-btn dx-btn-dark" href="{{ route('home') }}#precios">Registrar mi negocio</a>
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- ─── For store owners ─── --}}
    <section class="dx-cta">
        <div class="dx-shell dx-cta-inner">
            <div>
                <p class="dx-kicker">¿Tienes un negocio?</p>
                <h2>Tu tienda también puede estar en esta vitrina.</h2>
                <p>Cada tienda Tribio aparece aquí automáticamente, con sus productos en el buscador y su vista previa en vivo.</p>
            </div>
            <a class="dx-btn" href="{{ route('home') }}#precios">Crear mi tienda ↗</a>
        </div>
    </section>

    {{-- ─── Live preview of a store ─── --}}
    <template x-if="preview">
        <div class="dx-modal" role="dialog" aria-modal="true" :aria-label="'Vista previa de ' + preview.name" @keydown.escape.window="closePreview()">
            <div class="dx-modal-backdrop" @click="closePreview()"></div>
            <div class="dx-modal-panel" :style="'--accent:' + preview.accent">
                <div class="dx-modal-top">
                    <span class="dx-avatar" :data-initial="preview.name.charAt(0)">
                        <template x-if="preview.logo"><img :src="preview.logo" alt="" x-on:error="dxBroken($el)"></template>
                        <template x-if="!preview.logo"><span x-text="preview.name.charAt(0)"></span></template>
                    </span>
                    <strong x-text="preview.name"></strong>
                    <button type="button" @click="closePreview()" aria-label="Cerrar vista previa">×</button>
                </div>

                <div class="dx-phone">
                    <iframe :src="preview.preview_url" :title="'Tienda ' + preview.name + ' en vivo'" @load="frameReady = true"></iframe>
                    <div class="dx-phone-loading" x-show="!frameReady">
                        <span class="dx-spinner" aria-hidden="true"></span>
                        <span x-text="'Abriendo ' + preview.name + '…'"></span>
                    </div>
                </div>

                <aside class="dx-preview-info">
                    <button type="button" class="dx-preview-close" x-ref="previewClose" @click="closePreview()" aria-label="Cerrar vista previa">×</button>
                    <div class="dx-store-id">
                        <span class="dx-avatar" :data-initial="preview.name.charAt(0)">
                            <template x-if="preview.logo"><img :src="preview.logo" alt="" x-on:error="dxBroken($el)"></template>
                            <template x-if="!preview.logo"><span x-text="preview.name.charAt(0)"></span></template>
                        </span>
                        <div style="min-width: 0;">
                            <h3 x-text="preview.name"></h3>
                            <p x-show="preview.category_label" x-text="(preview.category_icon || '') + ' ' + (preview.category_label || '') + (preview.city ? ' · ' + preview.city : '')"></p>
                        </div>
                    </div>
                    <p class="dx-preview-summary" x-show="preview.summary" x-text="preview.summary"></p>
                    <template x-if="preview.products && preview.products.length">
                        <div>
                            <p class="dx-preview-label" x-text="'Algunos productos · ' + preview.products_count + ' en total'"></p>
                            <div class="dx-preview-products">
                                <template x-for="item in preview.products" :key="item.url">
                                    <a :href="item.url">
                                        <template x-if="item.image"><img :src="item.image" :alt="item.name" loading="lazy" x-on:error="dxBroken($el)"></template>
                                        <template x-if="!item.image"><span class="dx-fallback" x-text="item.name.charAt(0)"></span></template>
                                        <strong x-text="item.name"></strong>
                                        <small x-text="item.price_label"></small>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div class="dx-preview-actions">
                        <a class="dx-btn dx-btn-dark" :href="preview.url">Entrar a la tienda ↗</a>
                        <a class="dx-btn dx-btn-wa" x-show="preview.whatsapp_url" :href="preview.whatsapp_url" target="_blank" rel="noopener">Escribir por WhatsApp</a>
                    </div>
                    <p class="dx-preview-hint">Esta es la tienda real, funcionando ahora mismo. Puedes navegarla aquí mismo.</p>
                </aside>

                <div class="dx-modal-cta">
                    <button type="button" class="dx-btn dx-btn-line" @click="closePreview()">Volver</button>
                    <a class="dx-btn dx-btn-dark" :href="preview.url">Entrar a la tienda ↗</a>
                </div>
            </div>
        </div>
    </template>
</main>
@endsection

@push('scripts')
<script>
function tribioDirectory(config) {
    const fold = (text) => [...String(text)].map((c) => c.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()).join('');
    const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    return {
        query: config.query || '',
        category: config.category || '',
        sort: config.sort || 'relevancia',
        results: config.results,
        stores: config.stores || [],
        loading: false,
        failed: false,
        preview: null,
        frameReady: false,
        barVisible: false,
        timer: null,
        controller: null,
        lastFocus: null,

        get searching() {
            return this.results !== null || this.loading || this.failed;
        },

        init() {
            const hero = this.$refs.heroSearch;
            if (hero && 'IntersectionObserver' in window) {
                new IntersectionObserver(([entry]) => { this.barVisible = !entry.isIntersecting; }, { rootMargin: '-90px 0px 0px 0px' }).observe(hero);
            }
            this.$watch('preview', (open) => { document.body.style.overflow = open ? 'hidden' : ''; });
        },

        onType() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.run(), 260);
        },

        submit() {
            clearTimeout(this.timer);
            this.run(true);
        },

        pick(term) {
            this.query = term;
            this.submit();
        },

        setCategory(key) {
            this.category = this.category === key ? '' : key;
            this.run(true);
        },

        showDeals() {
            this.query = '';
            this.category = '';
            this.sort = 'ofertas';
            this.run(true);
        },

        clear() {
            clearTimeout(this.timer);
            this.controller?.abort();
            this.query = '';
            this.category = '';
            this.sort = 'relevancia';
            this.results = null;
            this.loading = false;
            this.failed = false;
            this.syncUrl(new URLSearchParams());
        },

        params() {
            const params = new URLSearchParams();
            const q = this.query.trim();
            if (q) params.set('q', q);
            if (this.category) params.set('categoria', this.category);
            if (this.sort !== 'relevancia') params.set('orden', this.sort);
            return params;
        },

        async run(scroll = false) {
            const q = this.query.trim();
            if (q.length < 2 && !this.category && this.sort !== 'ofertas') {
                this.controller?.abort();
                this.results = null;
                this.loading = false;
                this.failed = false;
                this.syncUrl(this.params());
                return;
            }

            this.controller?.abort();
            const controller = new AbortController();
            this.controller = controller;
            this.loading = true;
            this.failed = false;
            const params = this.params();

            try {
                const response = await fetch(config.searchUrl + '?' + params.toString(), { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                this.results = await response.json();
                this.syncUrl(this.params());
                if (scroll) this.$nextTick(() => this.$refs.results?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            } catch (error) {
                if (error.name === 'AbortError') return;
                this.results = null;
                this.failed = true;
            } finally {
                if (this.controller === controller) this.loading = false;
            }
        },

        syncUrl(params) {
            const url = new URL(window.location.href);
            url.search = params.toString();
            window.history.replaceState(null, '', url);
        },

        summary() {
            if (this.failed) return 'Búsqueda no disponible';
            if (!this.results) return 'Buscando…';
            const r = this.results;
            const products = r.total === 1 ? '1 producto' : r.total + ' productos';
            const stores = r.stores.length ? ' y ' + (r.stores.length === 1 ? '1 negocio' : r.stores.length + ' negocios') : '';
            const cat = (config.categories || []).find((c) => c.key === r.category)?.label || '';
            if (r.query) return products + stores + ' para “' + r.query + '”' + (cat ? ' en ' + cat : '');
            if (cat) return cat + ': ' + products + stores;
            if (r.sort === 'ofertas') return (r.total === 1 ? '1 producto' : r.total + ' productos') + ' en oferta';
            return products;
        },

        highlight(name) {
            const terms = (this.results?.terms || []).map(fold).filter((t) => t.length >= 2);
            const chars = [...String(name)];
            const folded = chars.map((c) => fold(c)).join('');
            if (!terms.length || folded.length !== chars.length) return escapeHtml(name);
            const marked = new Array(chars.length).fill(false);
            terms.forEach((term) => {
                for (let at = folded.indexOf(term); at !== -1; at = folded.indexOf(term, at + term.length)) {
                    for (let i = at; i < at + term.length; i++) marked[i] = true;
                }
            });
            let html = '';
            chars.forEach((c, i) => {
                if (marked[i] && !marked[i - 1]) html += '<mark>';
                html += escapeHtml(c);
                if (marked[i] && !marked[i + 1]) html += '</mark>';
            });
            return html;
        },

        openPreview(id, fallback = null) {
            const store = this.stores.find((s) => s.id === id)
                || this.results?.stores?.find((s) => s.id === id)
                || (fallback ? { ...fallback, products: [], products_count: 0 } : null);
            if (!store) return;
            this.lastFocus = document.activeElement;
            this.frameReady = false;
            this.preview = store;
            this.$nextTick(() => this.$refs.previewClose?.focus());
        },

        closePreview() {
            this.preview = null;
            this.lastFocus?.focus?.();
        },

        shortcut(event) {
            if (event.key !== '/' || event.ctrlKey || event.metaKey || this.preview) return;
            const tag = document.activeElement?.tagName;
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || document.activeElement?.isContentEditable) return;
            event.preventDefault();
            (this.barVisible ? this.$refs.barInput : this.$refs.heroInput).focus();
        },
    };
}
</script>
@endpush
