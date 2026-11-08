@extends('layouts.dashboard')
@section('title', 'Productos')
@section('page_title', '📦 Productos')
@section('content')
@php
    $keep = fn (array $over = []) => array_filter(array_merge(request()->except('page'), $over), fn ($v) => $v !== null && $v !== '');
    $money = fn ($v) => 'S/ ' . number_format((float) $v, 2);
    $catName = fn ($p) => ($p->category ?? $p->categories->first())?->name;
    $extraCats = fn ($p) => max(0, $p->categories->count() - ($p->category ? ($p->categories->contains('id', $p->category_id) ? 1 : 0) : 1));
    // The active filters, each with the link that removes it.
    $chips = array_filter([
        $f['q'] !== '' ? ['«' . $f['q'] . '»', 'q'] : null,
        $f['categoria'] ? ['Categoría: ' . ($categories->firstWhere('id', $f['categoria'])?->name ?? '—'), 'categoria'] : null,
        $f['marca'] ? ['Marca: ' . ($brands->firstWhere('id', $f['marca'])?->name ?? '—'), 'marca'] : null,
        $f['stock'] ? [$stocks[$f['stock']], 'stock'] : null,
        $f['estado'] ? [$f['estado'] === 'active' ? 'Activos' : 'Inactivos', 'estado'] : null,
        $f['destacado'] ? ['Destacados', 'destacado'] : null,
        $f['calidad'] ? [$qualities[$f['calidad']], 'calidad'] : null,
        ($f['precio_min'] !== null || $f['precio_max'] !== null) ? ['Precio ' . ($f['precio_min'] !== null ? 'desde ' . $money($f['precio_min']) : '') . ' ' . ($f['precio_max'] !== null ? 'hasta ' . $money($f['precio_max']) : ''), 'precio'] : null,
    ]);
    $moreOpen = $f['precio_min'] !== null || $f['precio_max'] !== null || $f['calidad'] || $f['destacado'] || $f['por_pagina'] !== 25;
@endphp
<div class="pr-app w-full max-w-7xl mx-auto"
     x-data="{ cols: { code: true, category: true, brand: true, cost: false, margin: false, unit: false, sold: false }, pick: false,
               init() { try { Object.assign(this.cols, JSON.parse(localStorage.getItem('tribio.products.cols') || '{}')); } catch (e) {} this.$watch('cols', v => { try { localStorage.setItem('tribio.products.cols', JSON.stringify(v)); } catch (e) {} }); },
               go(e, url) { if (!e.target.closest('a, button, form, summary, [role=menu]')) location.href = url; } }"
     :class="Object.entries(cols).filter(([, on]) => !on).map(([k]) => 'hide-' + k).join(' ')">

    @if(session('success'))<div class="pr-flash is-ok" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="pr-flash is-bad" role="alert">{{ session('error') }}</div>@endif

    <header class="pr-head">
        <div>
            <h2>{{ number_format($counts['total']) }} {{ $counts['total'] === 1 ? 'producto' : 'productos' }}</h2>
            <p>{{ $filtered ? 'Mostrando ' . number_format($products->total()) . ' con tus filtros' : 'Todo tu catálogo, con su código para vender escaneando.' }}</p>
        </div>
        <div class="pr-head-actions">
            <a href="{{ route('dashboard.productos.export', request()->query()) }}" class="pr-btn" title="Descargar esta lista para Excel"><x-dashboard-icon name="list"/><span>Exportar</span></a>
            <a href="{{ route('dashboard.productos.create') }}" class="pr-btn"><x-dashboard-icon name="plus"/><span>Producto completo</span></a>
            <a href="{{ route('dashboard.productos.rapido') }}" class="pr-btn is-primary"><x-dashboard-icon name="plus"/><span>Alta rápida</span></a>
        </div>
    </header>

    {{-- Vistas rápidas con su cantidad --}}
    <nav class="pr-quick" aria-label="Vistas rápidas">
        <a href="{{ route('dashboard.productos.index') }}" class="{{ !$filtered ? 'is-active' : '' }}">Todos <b>{{ $counts['total'] }}</b></a>
        <a href="{{ route('dashboard.productos.index', ['stock' => 'low']) }}" class="{{ $f['stock'] === 'low' && !$f['q'] ? 'is-active' : '' }} {{ $counts['low'] ? 'is-warn' : '' }}">Stock bajo <b>{{ $counts['low'] }}</b></a>
        <a href="{{ route('dashboard.productos.index', ['stock' => 'out']) }}" class="{{ $f['stock'] === 'out' && !$f['q'] ? 'is-active' : '' }} {{ $counts['out'] ? 'is-bad' : '' }}">Sin stock <b>{{ $counts['out'] }}</b></a>
        <a href="{{ route('dashboard.productos.index', ['calidad' => 'nocode']) }}" class="{{ $f['calidad'] === 'nocode' ? 'is-active' : '' }}" title="Sin código no se pueden vender escaneando">Sin código <b>{{ $counts['nocode'] }}</b></a>
        <a href="{{ route('dashboard.productos.index', ['calidad' => 'nocost']) }}" class="{{ $f['calidad'] === 'nocost' ? 'is-active' : '' }}" title="Sin costo no se calcula la ganancia">Sin costo <b>{{ $counts['nocost'] }}</b></a>
    </nav>

    {{-- Buscador y filtros --}}
    <form method="GET" action="{{ route('dashboard.productos.index') }}" class="pr-filters" role="search" x-data="{ open: matchMedia('(min-width: 900px)').matches }">
        <div class="pr-search">
            <x-dashboard-icon name="search"/>
            <label for="pr-q" class="sr-only">Buscar productos</label>
            <input id="pr-q" type="search" name="q" value="{{ $f['q'] }}" placeholder="Nombre, código, código de barras, marca o categoría" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" data-1p-ignore="true" data-lpignore="true">
            <button type="submit" class="pr-search-go">Buscar</button>
        </div>
        <button type="button" class="pr-fbtn" @click="open = !open" :aria-expanded="open">Filtros <span x-text="open ? '▴' : '▾'"></span>@if($chips)<b>{{ count($chips) }}</b>@endif</button>
        <div class="pr-panel" x-show="open" x-cloak>
        <div class="pr-selects">
            <label><span>Categoría</span>
                <select name="categoria" onchange="this.form.requestSubmit()">
                    <option value="">Todas</option>
                    @foreach($categories->whereNull('parent_id') as $cat)
                        <option value="{{ $cat->id }}" @selected($f['categoria'] === $cat->id)>{{ $cat->name }}</option>
                        @foreach($categories->where('parent_id', $cat->id) as $child)<option value="{{ $child->id }}" @selected($f['categoria'] === $child->id)>— {{ $child->name }}</option>@endforeach
                    @endforeach
                </select></label>
            <label><span>Marca</span>
                <select name="marca" onchange="this.form.requestSubmit()"><option value="">Todas</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected($f['marca'] === $brand->id)>{{ $brand->name }}</option>@endforeach</select></label>
            <label><span>Stock</span>
                <select name="stock" onchange="this.form.requestSubmit()"><option value="">Todo</option>@foreach($stocks as $key => $label)<option value="{{ $key }}" @selected($f['stock'] === $key)>{{ $label }}</option>@endforeach</select></label>
            @if($storefront)
            <label><span>Estado</span>
                <select name="estado" onchange="this.form.requestSubmit()"><option value="">Todos</option><option value="active" @selected($f['estado'] === 'active')>Activos</option><option value="inactive" @selected($f['estado'] === 'inactive')>Inactivos</option></select></label>
            @endif
            <label><span>Ordenar</span>
                <select name="orden" onchange="this.form.requestSubmit()">@foreach($sorts as $key => $label)<option value="{{ $key }}" @selected($f['orden'] === $key)>{{ $label }}</option>@endforeach</select></label>
        </div>
        </div>
        <details class="pr-more" @if($moreOpen) open @endif>
            <summary>Más filtros</summary>
            <div class="pr-more-body">
                <label><span>Precio desde (S/)</span><input type="number" name="precio_min" step="0.01" min="0" inputmode="decimal" value="{{ $f['precio_min'] }}"></label>
                <label><span>Precio hasta (S/)</span><input type="number" name="precio_max" step="0.01" min="0" inputmode="decimal" value="{{ $f['precio_max'] }}"></label>
                <label><span>Datos que faltan</span>
                    <select name="calidad"><option value="">—</option>@foreach($qualities as $key => $label)<option value="{{ $key }}" @selected($f['calidad'] === $key)>{{ $label }}</option>@endforeach</select></label>
                <label><span>Por página</span>
                    <select name="por_pagina">@foreach([25, 50, 100] as $n)<option value="{{ $n }}" @selected($f['por_pagina'] === $n)>{{ $n }}</option>@endforeach</select></label>
                @if($storefront)<label class="pr-check"><input type="checkbox" name="destacado" value="1" @checked($f['destacado'])> Solo destacados</label>@endif
                <button type="submit" class="pr-btn is-primary">Aplicar</button>
            </div>
        </details>
        </div>
    </form>

    <div class="pr-bar">
        <div class="pr-chips" aria-label="Filtros activos">
            @foreach($chips as [$label, $key])
                <a href="{{ route('dashboard.productos.index', $keep($key === 'precio' ? ['precio_min' => null, 'precio_max' => null] : [$key => null])) }}" class="pr-chip" title="Quitar este filtro">{{ trim($label) }} <span aria-hidden="true">✕</span></a>
            @endforeach
            @if($chips)<a href="{{ route('dashboard.productos.index') }}" class="pr-clear">Limpiar todo</a>@endif
        </div>
        <div class="pr-cols" @click.outside="pick = false" @keydown.escape.window="pick = false">
            <button type="button" class="pr-btn" @click="pick = !pick" :aria-expanded="pick" aria-haspopup="true">Columnas</button>
            <div class="pr-cols-list" x-show="pick" x-cloak>
                <label><input type="checkbox" x-model="cols.code"> Código</label>
                <label><input type="checkbox" x-model="cols.category"> Categoría</label>
                <label><input type="checkbox" x-model="cols.brand"> Marca</label>
                <label><input type="checkbox" x-model="cols.cost"> Costo</label>
                <label><input type="checkbox" x-model="cols.margin"> Margen</label>
                <label><input type="checkbox" x-model="cols.unit"> Unidad</label>
                <label><input type="checkbox" x-model="cols.sold"> Vendidos</label>
            </div>
        </div>
    </div>

    @if($storefront && $homeVideoProducts->count() > 0)
    <details class="pr-videos">
        <summary>🎬 Videos destacados en la portada <b>{{ $homeVideoProducts->count() }}/3</b></summary>
        <div class="pr-videos-body">
            @foreach($homeVideoProducts as $idx => $hProd)
                <form action="{{ route('dashboard.productos.toggle-home-video', $hProd->id) }}" method="POST">@csrf
                    <span>{{ $hProd->name }}</span><button type="submit" class="pr-link-danger">Quitar</button></form>
            @endforeach
        </div>
    </details>
    @endif

    @if($products->isEmpty())
        <div class="pr-empty">
            @if($filtered)
                <strong>Ningún producto coincide</strong>
                <p>Prueba con otra palabra o quita algún filtro.</p>
                <a href="{{ route('dashboard.productos.index') }}" class="pr-btn is-primary">Limpiar filtros</a>
            @else
                <strong>Aún no tienes productos</strong>
                <p>Cárgalos en minutos: pega desde Excel o escanea sus códigos.</p>
                <a href="{{ route('dashboard.productos.rapido') }}" class="pr-btn is-primary">Alta rápida</a>
            @endif
        </div>
    @else
    {{-- Escritorio: tabla --}}
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead><tr>
                <th>Producto</th>
                <th data-col="code">Código</th>
                <th data-col="category">Categoría</th>
                <th data-col="brand">Marca</th>
                <th class="r">Precio</th>
                <th class="r" data-col="cost">Costo</th>
                <th class="r" data-col="margin">Margen</th>
                <th class="r">Stock</th>
                <th data-col="unit">Unidad</th>
                <th class="r" data-col="sold">Vendidos</th>
                @if($storefront)<th>Estado</th>@endif
                <th><span class="sr-only">Acciones</span></th>
            </tr></thead>
            <tbody>
            @foreach($products as $product)
                @php $edit = route('dashboard.productos.edit', $product); $margin = (float) $product->price > 0 && (float) $product->cost_price > 0 ? ((float) $product->price - (float) $product->cost_price) * 100 / (float) $product->price : null; @endphp
                <tr @click="go($event, '{{ $edit }}')">
                    <td class="pr-name">
                        <span class="pr-thumb">@if($product->image_path)<img src="{{ $product->image_url }}" alt="" loading="lazy">@else<x-dashboard-icon name="box"/>@endif</span>
                        <span class="pr-name-text"><a href="{{ $edit }}">{{ $product->name }}</a>
                            @if($storefront && $product->is_featured)<span class="pr-tag"><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.8 6.2 20.9l1.1-6.5L2.6 9.8l6.5-.9z"/></svg>Destacado</span>@endif</span>
                    </td>
                    <td data-col="code" class="pr-code">@if($product->sku){{ $product->sku }}@else<a href="{{ $edit }}" class="pr-missing">Sin código</a>@endif</td>
                    <td data-col="category">{{ $catName($product) ?? '—' }}@if($extraCats($product) > 0) <span class="pr-more-n" title="{{ $product->categories->pluck('name')->implode(', ') }}">+{{ $extraCats($product) }}</span>@endif</td>
                    <td data-col="brand">{{ $product->brand?->name ?? '—' }}</td>
                    <td class="r pr-price">{{ $money($product->price) }}</td>
                    <td class="r" data-col="cost">{{ $product->cost_price ? $money($product->cost_price) : '—' }}</td>
                    <td class="r" data-col="margin">{{ $margin !== null ? number_format($margin, 0) . ' %' : '—' }}</td>
                    <td class="r">@include('dashboard.products._stock', ['product' => $product])</td>
                    <td data-col="unit">{{ $product->unit }}</td>
                    <td class="r" data-col="sold">{{ number_format((int) $product->sold_count) }}</td>
                    @if($storefront)<td><span class="pr-state {{ $product->is_active ? 'is-on' : '' }}">{{ $product->is_active ? 'Activo' : 'Inactivo' }}</span></td>@endif
                    <td class="pr-act">@include('dashboard.products._menu', ['product' => $product, 'edit' => $edit])</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- Celular: tarjetas --}}
    <ul class="pr-cards">
        @foreach($products as $product)
            @php $edit = route('dashboard.productos.edit', $product); @endphp
            <li class="pr-card">
                <a href="{{ $edit }}" class="pr-card-main">
                    <span class="pr-thumb">@if($product->image_path)<img src="{{ $product->image_url }}" alt="" loading="lazy">@else<x-dashboard-icon name="box"/>@endif</span>
                    <span class="pr-card-text">
                        <strong>{{ $product->name }}</strong>
                        <small>@if($product->sku)<code>{{ $product->sku }}</code>@else<span class="pr-missing">Sin código</span>@endif @if($catName($product)) · {{ $catName($product) }}@endif @if($product->brand) · {{ $product->brand->name }}@endif</small>
                        @if($storefront && ($product->is_featured || !$product->is_active))<small>@if($product->is_featured)<span class="pr-tag">Destacado</span>@endif @unless($product->is_active)<span class="pr-state">Inactivo</span>@endunless</small>@endif
                    </span>
                    <span class="pr-card-side"><b>{{ $money($product->price) }}</b>@include('dashboard.products._stock', ['product' => $product])</span>
                </a>
                @include('dashboard.products._menu', ['product' => $product, 'edit' => $edit])
            </li>
        @endforeach
    </ul>
    <div class="pr-pager">{{ $products->links() }}</div>
    @endif
</div>
@endsection
