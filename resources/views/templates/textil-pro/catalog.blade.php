@extends('templates.textil-pro.layout')

@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $products = $products ?? $allProducts;
    $facets = \App\Services\Storefront\CatalogFacets::forStore($store);
    $activeAttr = array_filter((array) request('attr', []), fn ($v) => is_scalar($v) && $v !== '');
    $activeCategory = request('category') ? $categories->first(fn ($c) => $c->slug === request('category') || (string) $c->id === (string) request('category')) : null;
    // Una subcategoría seleccionada no está en $categories (solo raíces): se busca entre los hijos.
    if (!$activeCategory && request('category')) {
        $activeCategory = $categories->flatMap->children->first(fn ($c) => $c->slug === request('category'));
    }
    $sort = request('sort', 'position');
    $sortOptions = [
        'position' => $isEn ? 'Recommended' : 'Recomendados',
        'newest' => $isEn ? 'Newest' : 'Lo más nuevo',
        'price_asc' => $isEn ? 'Price: low to high' : 'Precio: menor a mayor',
        'price_desc' => $isEn ? 'Price: high to low' : 'Precio: mayor a menor',
        'name_asc' => $isEn ? 'Name: A to Z' : 'Nombre: A a Z',
    ];
    $url = fn (array $changes) => request()->fullUrlWithQuery($changes + ['page' => null]);
    $attrUrl = function (string $name, string $value) use ($activeAttr, $url) {
        $attr = $activeAttr;
        if (($attr[$name] ?? null) === $value) {
            unset($attr[$name]);
        } else {
            $attr[$name] = $value;
        }

        return $url(['attr' => $attr ?: null]);
    };
    // Campos ocultos para que el formulario de precio conserve el resto de filtros.
    $keepFields = function (array $except) {
        $fields = [];
        foreach (request()->except(array_merge($except, ['page'])) as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $sub => $subValue) {
                    if (is_scalar($subValue)) {
                        $fields["{$key}[{$sub}]"] = $subValue;
                    }
                }
            } elseif (is_scalar($value) && $value !== '') {
                $fields[$key] = $value;
            }
        }

        return $fields;
    };
    $title = match (true) {
        request()->filled('q') => ($isEn ? 'Results for' : 'Resultados para') . ' "' . request('q') . '"',
        (bool) $activeCategory => $activeCategory->getTranslatedName(),
        request()->boolean('on_sale') => $isEn ? 'Sale' : 'Ofertas',
        $sort === 'newest' => $isEn ? 'New in' : 'Novedades',
        default => $isEn ? 'All products' : 'Todos los productos',
    };
    $applied = [];
    if (request()->filled('q')) $applied[] = [$isEn ? 'Search' : 'Búsqueda', request('q'), $url(['q' => null])];
    if ($activeCategory) $applied[] = [$isEn ? 'Category' : 'Categoría', $activeCategory->getTranslatedName(), $url(['category' => null])];
    if (request()->filled('brand')) $applied[] = [$isEn ? 'Brand' : 'Marca', optional($brands->firstWhere('slug', request('brand')))->name ?? request('brand'), $url(['brand' => null])];
    foreach ($activeAttr as $name => $value) $applied[] = [$name, $value, $attrUrl($name, (string) $value)];
    if (request()->filled('min_price') || request()->filled('max_price')) $applied[] = [$isEn ? 'Price' : 'Precio', $currencySymbol . ' ' . (request('min_price') ?: 0) . ' – ' . (request('max_price') ?: '∞'), $url(['min_price' => null, 'max_price' => null])];
    if (request()->boolean('on_sale')) $applied[] = [$isEn ? 'Discount' : 'Descuento', $isEn ? 'On sale' : 'En oferta', $url(['on_sale' => null])];
    if (request()->boolean('in_stock')) $applied[] = [$isEn ? 'Stock' : 'Stock', $isEn ? 'Available' : 'Disponible', $url(['in_stock' => null])];
    $chevron = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>';
@endphp

@section('title', $title . ' | ' . $store->name)

@section('content')
<div class="tx-narrow">
    <nav class="tx-crumbs" aria-label="{{ $isEn ? 'Breadcrumb' : 'Ruta' }}">
        <a href="{{ route('store.show', $store->slug) }}">{{ $isEn ? 'Home' : 'Inicio' }}</a>
        <i aria-hidden="true"></i>
        @if($activeCategory)
            <a href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'Shop' : 'Catálogo' }}</a>
            <i aria-hidden="true"></i>
            <span>{{ $activeCategory->getTranslatedName() }}</span>
        @else
            <span>{{ $isEn ? 'Shop' : 'Catálogo' }}</span>
        @endif
    </nav>

    <div class="tx-cat-title">
        <h1>{{ $title }}</h1>
        <span>{{ $products->total() }} {{ $isEn ? 'results' : ($products->total() === 1 ? 'resultado' : 'resultados') }}</span>
    </div>

    @if($activeCategory && $activeCategory->children->isNotEmpty())
        <div class="tx-subcats">
            @foreach($activeCategory->children as $child)
                <a class="tx-chip" href="{{ $url(['category' => $child->slug]) }}">{{ $child->getTranslatedName() }}</a>
            @endforeach
        </div>
    @endif

    {{-- Barra de filtros --}}
    <div class="tx-filterbar" x-data="{ open: null, drawer: false }" @keydown.escape.window="open = null" @click.outside="open = null">
        <button type="button" class="tx-filter-icon" @click="drawer = true" aria-label="{{ $isEn ? 'All filters' : 'Todos los filtros' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 7h9m4 0h3M4 17h3m4 0h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/></svg>
        </button>

        <div class="tx-filters">
            @if($categories->isNotEmpty())
            <div class="tx-dd" :class="{ 'is-open': open === 'cat' }">
                <button type="button" class="{{ $activeCategory ? 'is-on' : '' }}" @click="open = open === 'cat' ? null : 'cat'" :aria-expanded="(open === 'cat').toString()">{{ $isEn ? 'Category' : 'Categoría' }} {!! $chevron !!}</button>
                <div class="tx-dd-panel" x-show="open === 'cat'" x-cloak>
                    <a class="tx-opt {{ !$activeCategory ? 'is-active' : '' }}" href="{{ $url(['category' => null]) }}"><span><span class="tx-check"></span>{{ $isEn ? 'All' : 'Todas' }}</span></a>
                    @foreach($categories as $cat)
                        <a class="tx-opt {{ $activeCategory && $activeCategory->id === $cat->id ? 'is-active' : '' }}" href="{{ $url(['category' => $cat->slug]) }}">
                            <span><span class="tx-check"></span>{{ $cat->getTranslatedName() }}</span><small>{{ $cat->active_products_count }}</small>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="tx-dd" :class="{ 'is-open': open === 'sale' }">
                <button type="button" class="{{ request()->boolean('on_sale') ? 'is-on' : '' }}" @click="open = open === 'sale' ? null : 'sale'">{{ $isEn ? 'Discount' : 'Descuento' }} {!! $chevron !!}</button>
                <div class="tx-dd-panel" x-show="open === 'sale'" x-cloak>
                    <a class="tx-opt {{ request()->boolean('on_sale') ? 'is-active' : '' }}" href="{{ $url(['on_sale' => request()->boolean('on_sale') ? null : 1]) }}"><span><span class="tx-check"></span>{{ $isEn ? 'On sale only' : 'Solo productos en oferta' }}</span></a>
                    <a class="tx-opt {{ request()->boolean('in_stock') ? 'is-active' : '' }}" href="{{ $url(['in_stock' => request()->boolean('in_stock') ? null : 1]) }}"><span><span class="tx-check"></span>{{ $isEn ? 'In stock only' : 'Solo con stock' }}</span></a>
                </div>
            </div>

            <div class="tx-dd" :class="{ 'is-open': open === 'price' }">
                <button type="button" class="{{ request()->filled('min_price') || request()->filled('max_price') ? 'is-on' : '' }}" @click="open = open === 'price' ? null : 'price'">{{ $isEn ? 'Price' : 'Precio' }} {!! $chevron !!}</button>
                <div class="tx-dd-panel" x-show="open === 'price'" x-cloak>
                    <form method="GET" action="{{ route('store.catalog', $store->slug) }}" style="display: grid; gap: 12px">
                        @foreach($keepFields(['min_price', 'max_price']) as $name => $value)
                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                        @endforeach
                        <div class="tx-price-form">
                            <label><span class="tx-label">{{ $isEn ? 'From' : 'Desde' }} ({{ $currencySymbol }})</span><input class="tx-input" type="number" step="any" min="0" name="min_price" value="{{ request('min_price') }}" placeholder="{{ (int) $minPricePossible }}"></label>
                            <label><span class="tx-label">{{ $isEn ? 'To' : 'Hasta' }} ({{ $currencySymbol }})</span><input class="tx-input" type="number" step="any" min="0" name="max_price" value="{{ request('max_price') }}" placeholder="{{ (int) $maxPricePossible }}"></label>
                        </div>
                        <button type="submit" class="tx-btn is-block" style="min-height: 44px">{{ $isEn ? 'Apply' : 'Aplicar' }}</button>
                    </form>
                </div>
            </div>

            @foreach($facets as $facetName => $values)
                @php $isSizeFacet = \App\Services\Storefront\CatalogFacets::isSize($facetName); $key = 'attr-' . $loop->index; @endphp
                <div class="tx-dd" :class="{ 'is-open': open === '{{ $key }}' }">
                    <button type="button" class="{{ isset($activeAttr[$facetName]) ? 'is-on' : '' }}" @click="open = open === '{{ $key }}' ? null : '{{ $key }}'">{{ $facetName }} {!! $chevron !!}</button>
                    <div class="tx-dd-panel" x-show="open === '{{ $key }}'" x-cloak>
                        @if($isSizeFacet)
                            <div class="tx-sizes">
                                @foreach($values as $value)
                                    <a class="tx-size {{ ($activeAttr[$facetName] ?? null) === $value ? 'is-active' : '' }}" href="{{ $attrUrl($facetName, $value) }}">{{ $value }}</a>
                                @endforeach
                            </div>
                        @else
                            @foreach($values as $value)
                                <a class="tx-opt {{ ($activeAttr[$facetName] ?? null) === $value ? 'is-active' : '' }}" href="{{ $attrUrl($facetName, $value) }}"><span><span class="tx-check"></span>{{ $value }}</span></a>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endforeach

            @if($brands->where('products_count', '>', 0)->isNotEmpty())
            <div class="tx-dd" :class="{ 'is-open': open === 'brand' }">
                <button type="button" class="{{ request()->filled('brand') ? 'is-on' : '' }}" @click="open = open === 'brand' ? null : 'brand'">{{ $isEn ? 'Brand' : 'Marca' }} {!! $chevron !!}</button>
                <div class="tx-dd-panel" x-show="open === 'brand'" x-cloak>
                    @foreach($brands->where('products_count', '>', 0) as $brand)
                        <a class="tx-opt {{ request('brand') === $brand->slug ? 'is-active' : '' }}" href="{{ $url(['brand' => request('brand') === $brand->slug ? null : $brand->slug]) }}">
                            <span><span class="tx-check"></span>{{ $brand->name }}</span><small>{{ $brand->products_count }}</small>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="tx-dd tx-sort" :class="{ 'is-open': open === 'sort' }">
            <button type="button" @click="open = open === 'sort' ? null : 'sort'">{{ $isEn ? 'Sort by' : 'Ordenar por' }}{{ $sort !== 'position' ? ': ' . ($sortOptions[$sort] ?? '') : '' }} {!! $chevron !!}</button>
            <div class="tx-dd-panel is-right" x-show="open === 'sort'" x-cloak>
                @foreach($sortOptions as $value => $label)
                    <a class="tx-opt {{ $sort === $value ? 'is-active' : '' }}" href="{{ $url(['sort' => $value === 'position' ? null : $value]) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <button type="button" class="tx-btn is-outline tx-mobile-filter-btn" @click="drawer = true">
            {{ $isEn ? 'Filter & sort' : 'Filtrar y ordenar' }}{{ count($applied) ? ' (' . count($applied) . ')' : '' }}
        </button>

        {{-- Todos los filtros (celular y botón de ajustes) --}}
        <template x-teleport="body">
            <div class="tx-drawer" x-show="drawer" x-cloak role="dialog" aria-modal="true" aria-label="{{ $isEn ? 'Filters' : 'Filtros' }}" @keydown.escape.window="drawer = false">
                <div class="tx-drawer-backdrop" x-show="drawer" x-transition.opacity @click="drawer = false"></div>
                <div class="tx-drawer-panel is-right" x-show="drawer"
                     x-transition:enter="tx-tr" x-transition:enter-start="tx-off-right" x-transition:enter-end="tx-on"
                     x-transition:leave="tx-tr" x-transition:leave-start="tx-on" x-transition:leave-end="tx-off-right">
                    <div class="tx-drawer-head">
                        <strong>{{ $isEn ? 'Filter & sort' : 'Filtrar y ordenar' }}</strong>
                        <button type="button" class="tx-icon-btn" @click="drawer = false" aria-label="{{ $isEn ? 'Close' : 'Cerrar' }}">
                            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <div class="tx-drawer-body">
                        <details class="tx-acc" open>
                            <summary>{{ $isEn ? 'Sort by' : 'Ordenar por' }}</summary>
                            <div class="tx-acc-body">
                                @foreach($sortOptions as $value => $label)
                                    <a class="tx-opt {{ $sort === $value ? 'is-active' : '' }}" href="{{ $url(['sort' => $value === 'position' ? null : $value]) }}"><span><span class="tx-check"></span>{{ $label }}</span></a>
                                @endforeach
                            </div>
                        </details>
                        @if($categories->isNotEmpty())
                        <details class="tx-acc" @if($activeCategory) open @endif>
                            <summary>{{ $isEn ? 'Category' : 'Categoría' }}</summary>
                            <div class="tx-acc-body">
                                @foreach($categories as $cat)
                                    <a class="tx-opt {{ $activeCategory && $activeCategory->id === $cat->id ? 'is-active' : '' }}" href="{{ $url(['category' => $activeCategory && $activeCategory->id === $cat->id ? null : $cat->slug]) }}"><span><span class="tx-check"></span>{{ $cat->getTranslatedName() }}</span><small>{{ $cat->active_products_count }}</small></a>
                                @endforeach
                            </div>
                        </details>
                        @endif
                        @foreach($facets as $facetName => $values)
                            <details class="tx-acc" @if(isset($activeAttr[$facetName]) || $loop->first) open @endif>
                                <summary>{{ $facetName }}</summary>
                                <div class="tx-acc-body">
                                    @if(\App\Services\Storefront\CatalogFacets::isSize($facetName))
                                        <div class="tx-sizes">
                                            @foreach($values as $value)
                                                <a class="tx-size {{ ($activeAttr[$facetName] ?? null) === $value ? 'is-active' : '' }}" href="{{ $attrUrl($facetName, $value) }}">{{ $value }}</a>
                                            @endforeach
                                        </div>
                                    @else
                                        @foreach($values as $value)
                                            <a class="tx-opt {{ ($activeAttr[$facetName] ?? null) === $value ? 'is-active' : '' }}" href="{{ $attrUrl($facetName, $value) }}"><span><span class="tx-check"></span>{{ $value }}</span></a>
                                        @endforeach
                                    @endif
                                </div>
                            </details>
                        @endforeach
                        <details class="tx-acc">
                            <summary>{{ $isEn ? 'Price' : 'Precio' }}</summary>
                            <div class="tx-acc-body">
                                <form method="GET" action="{{ route('store.catalog', $store->slug) }}" style="display: grid; gap: 12px">
                                    @foreach($keepFields(['min_price', 'max_price']) as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                    <div class="tx-price-form">
                                        <input class="tx-input" type="number" step="any" min="0" name="min_price" value="{{ request('min_price') }}" placeholder="{{ $isEn ? 'From' : 'Desde' }}" aria-label="{{ $isEn ? 'Minimum price' : 'Precio mínimo' }}">
                                        <input class="tx-input" type="number" step="any" min="0" name="max_price" value="{{ request('max_price') }}" placeholder="{{ $isEn ? 'To' : 'Hasta' }}" aria-label="{{ $isEn ? 'Maximum price' : 'Precio máximo' }}">
                                    </div>
                                    <button type="submit" class="tx-btn is-block" style="min-height: 44px">{{ $isEn ? 'Apply' : 'Aplicar' }}</button>
                                </form>
                            </div>
                        </details>
                        <details class="tx-acc">
                            <summary>{{ $isEn ? 'Discount & stock' : 'Descuento y stock' }}</summary>
                            <div class="tx-acc-body">
                                <a class="tx-opt {{ request()->boolean('on_sale') ? 'is-active' : '' }}" href="{{ $url(['on_sale' => request()->boolean('on_sale') ? null : 1]) }}"><span><span class="tx-check"></span>{{ $isEn ? 'On sale only' : 'Solo en oferta' }}</span></a>
                                <a class="tx-opt {{ request()->boolean('in_stock') ? 'is-active' : '' }}" href="{{ $url(['in_stock' => request()->boolean('in_stock') ? null : 1]) }}"><span><span class="tx-check"></span>{{ $isEn ? 'In stock only' : 'Solo con stock' }}</span></a>
                            </div>
                        </details>
                        @if($brands->where('products_count', '>', 0)->isNotEmpty())
                        <details class="tx-acc">
                            <summary>{{ $isEn ? 'Brand' : 'Marca' }}</summary>
                            <div class="tx-acc-body">
                                @foreach($brands->where('products_count', '>', 0) as $brand)
                                    <a class="tx-opt {{ request('brand') === $brand->slug ? 'is-active' : '' }}" href="{{ $url(['brand' => request('brand') === $brand->slug ? null : $brand->slug]) }}"><span><span class="tx-check"></span>{{ $brand->name }}</span><small>{{ $brand->products_count }}</small></a>
                                @endforeach
                            </div>
                        </details>
                        @endif
                        <div style="display: grid; gap: 10px; margin-top: 22px">
                            <button type="button" class="tx-btn is-block" @click="drawer = false">{{ $isEn ? 'See' : 'Ver' }} {{ $products->total() }} {{ $isEn ? 'results' : 'resultados' }}</button>
                            @if(count($applied))
                                <a class="tx-btn is-outline is-block" href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'Clear all' : 'Limpiar todo' }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @if(count($applied))
        <div class="tx-applied">
            @foreach($applied as [$label, $value, $removeUrl])
                <a class="tx-chip" href="{{ $removeUrl }}" aria-label="{{ $isEn ? 'Remove' : 'Quitar' }} {{ $label }}: {{ $value }}">{{ $label }}: <strong>{{ $value }}</strong></a>
            @endforeach
            <a class="tx-link" style="font-size: 13px" href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'Clear all' : 'Limpiar todo' }}</a>
        </div>
    @endif

    <div class="tx-results">
        @if($products->isEmpty())
            <div class="tx-empty">
                <h2>{{ $isEn ? 'No products found' : 'No encontramos productos' }}</h2>
                <p>{{ $isEn ? 'Try removing a filter or searching for something else.' : 'Prueba quitando algún filtro o buscando otra cosa.' }}</p>
                <a class="tx-btn" href="{{ route('store.catalog', $store->slug) }}">{{ $isEn ? 'See all products' : 'Ver todos los productos' }}</a>
            </div>
        @else
            <div class="tx-grid">
                @foreach($products as $product)
                    @include('templates.textil-pro._product-card', ['product' => $product])
                @endforeach
            </div>

            @if($products->lastPage() > 1)
                @php
                    $current = $products->currentPage();
                    $last = $products->lastPage();
                    $pages = collect([1, $last, $current - 1, $current, $current + 1])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
                @endphp
                <nav class="tx-pager" aria-label="{{ $isEn ? 'Pages' : 'Páginas' }}">
                    @if($current > 1)<a href="{{ $products->url($current - 1) }}" rel="prev" aria-label="{{ $isEn ? 'Previous page' : 'Página anterior' }}">‹</a>@endif
                    @foreach($pages as $index => $page)
                        @if($index > 0 && $page - $pages[$index - 1] > 1)<span class="is-gap">…</span>@endif
                        @if($page === $current)<span class="is-current" aria-current="page">{{ $page }}</span>@else<a href="{{ $products->url($page) }}">{{ $page }}</a>@endif
                    @endforeach
                    @if($current < $last)<a href="{{ $products->url($current + 1) }}" rel="next" aria-label="{{ $isEn ? 'Next page' : 'Página siguiente' }}">›</a>@endif
                </nav>
            @endif
        @endif
    </div>
</div>
@endsection
