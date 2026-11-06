@extends('layouts.dashboard')
@section('title', $order->exists ? 'Editar ' . $order->number : 'Nueva compra')
@section('page_title', '🧾 Compras')
@section('content')
@php
    $initialLines = old('items_json') ? json_decode(old('items_json'), true) : $lines;
@endphp
<div class="w-full max-w-4xl mx-auto"
     x-data="purchaseForm(@js($products), @js(route('dashboard.compras.products')), @js($initialLines ?: []), {{ (float) old('extra_costs', $order->extra_costs ?? 0) }})">

    <form method="POST" action="{{ $order->exists ? route('dashboard.compras.update', $order->id) : route('dashboard.compras.store') }}" class="space-y-5" @submit="sync()">
        @csrf
        @if($order->exists) @method('PUT') @endif
        <input type="hidden" name="items_json" x-ref="itemsJson">

        {{-- Barra de acciones fija --}}
        <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
            <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-x-3 gap-y-2 flex-wrap">
                <a href="{{ $order->exists ? route('dashboard.compras.show', $order->id) : route('dashboard.compras.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver" title="Volver">←</a>
                <div class="min-w-0 flex-1 basis-40">
                    <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Compras</p>
                    <p class="text-white font-bold text-sm truncate">{{ $order->exists ? $order->number : 'Nueva compra' }} · <span x-text="money(total())"></span></p>
                </div>
                @if(!$order->exists || $order->status === 'draft')
                    <button type="submit" class="btn-secondary flex-shrink-0 text-xs sm:text-sm">Guardar borrador</button>
                    <button type="submit" name="mark_ordered" value="1" class="btn-primary flex-shrink-0 text-xs sm:text-sm"><span class="hidden sm:inline">Guardar y </span>marcar pedida</button>
                @else
                    <button type="submit" class="btn-primary flex-shrink-0 text-xs sm:text-sm">Guardar cambios</button>
                @endif
            </div>
        </div>

        @if($errors->any())
            <div class="glass-card p-4" role="alert">
                @foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach
            </div>
        @endif

        {{-- Productos --}}
        <div class="glass-card p-4 sm:p-6">
            <h3 class="text-white font-bold text-base">Productos</h3>
            <p class="text-xs text-white/50 mt-1">Escribe el nombre o el código y elige de la lista: solo aparecen productos con control de stock. El costo se completa con el último que pagaste; cámbialo si este proveedor te cobra otro.</p>

            <div class="space-y-3 mt-4">
                <template x-for="(line, index) in lines" :key="line.key">
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3 grid grid-cols-6 gap-2 items-end">
                        <div class="col-span-6 pp-field" :class="unitsOf(line).length ? 'sm:col-span-2' : 'sm:col-span-3'" @click.outside="line.open = false">
                            <label class="input-label" :for="'p' + line.key">Producto</label>
                            <template x-if="line.product_id && product(line)">
                                <div class="pp-chosen">
                                    <span class="pp-chosen-text"><strong x-text="product(line).name"></strong><small x-text="detail(product(line))"></small></span>
                                    <button type="button" class="pp-change" @click="change(line)">Cambiar</button>
                                </div>
                            </template>
                            <template x-if="!(line.product_id && product(line))">
                                <div class="pp-search">
                                    <input type="search" class="input-field" :id="'p' + line.key" name="compra_buscar_producto" placeholder="Escribe nombre o código…"
                                           autocomplete="off" autocapitalize="off" spellcheck="false" data-1p-ignore="true" data-lpignore="true"
                                           role="combobox" aria-autocomplete="list" :aria-expanded="line.open" :aria-controls="'pl' + line.key"
                                           x-model="line.q" @focus="openList(line)" @input.debounce.200ms="find(line)"
                                           @keydown.down.prevent="move(line, 1)" @keydown.up.prevent="move(line, -1)"
                                           @keydown.enter.prevent="choose(line, line.results[line.active] || (line.results.length === 1 ? line.results[0] : null))"
                                           @keydown.escape="line.open = false">
                                    <ul class="pp-list" :id="'pl' + line.key" role="listbox" x-show="line.open" x-cloak>
                                        <template x-for="(item, i) in line.results" :key="item.id">
                                            <li role="option" :aria-selected="i === line.active" :class="{ 'is-active': i === line.active }" @mousedown.prevent @click="choose(line, item)" @mouseenter="line.active = i">
                                                <span class="pp-item-main"><strong x-text="item.name"></strong><small x-text="item.sku ? 'Código ' + item.sku : 'Sin código'"></small></span>
                                                <span class="pp-item-side"><b :class="{ 'is-low': item.low }" x-text="item.stock + ' en stock'"></b><small x-text="'Costo ' + money(item.cost)"></small></span>
                                            </li>
                                        </template>
                                        <li class="pp-note" x-show="line.loading && !line.results.length">Buscando…</li>
                                        <li class="pp-note" x-show="!line.loading && !line.results.length" x-text="line.q.trim() ? 'Ningún producto coincide con «' + line.q.trim() + '».' : 'Escribe para buscar entre tus productos.'"></li>
                                        <li class="pp-note" x-show="!line.q.trim() && line.results.length">Por reponer y últimas compras · escribe para buscar</li>
                                    </ul>
                                </div>
                            </template>
                        </div>
                        <div class="col-span-6 sm:col-span-1" x-show="unitsOf(line).length">
                            <label class="input-label" :for="'u' + line.key">Presentación</label>
                            <select class="input-field" :id="'u' + line.key" x-model.number="line.unit_id" @change="line.unit_cost = 0; pickCost(line)">
                                <option value="">Unidad</option>
                                <template x-for="unit in unitsOf(line)" :key="unit.id">
                                    <option :value="unit.id" x-text="unit.name + ' (' + unit.factor + ' und)'" :selected="unit.id === line.unit_id"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label class="input-label" :for="'q' + line.key">Cantidad</label>
                            <input type="number" min="1" class="input-field" :id="'q' + line.key" x-model.number="line.quantity">
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label class="input-label" :for="'c' + line.key" x-text="'Costo por ' + unitName(line)">Costo c/u</label>
                            <input type="number" min="0" step="0.01" class="input-field" :id="'c' + line.key" x-model.number="line.unit_cost">
                        </div>
                        <div class="col-span-2 sm:col-span-1 flex items-center justify-end gap-2 pb-2">
                            <span class="text-sm text-white font-semibold whitespace-nowrap" x-text="money((line.quantity || 0) * (line.unit_cost || 0))"></span>
                            <button type="button" class="text-white/40 hover:text-red-300 text-lg leading-none" @click="lines.splice(index, 1)" aria-label="Quitar producto">×</button>
                        </div>
                    </div>
                </template>
            </div>
            <button type="button" class="btn-ghost text-xs mt-3" @click="add()">+ Agregar producto</button>
            @unless($hasProducts)
                <p class="text-xs text-white/60 mt-3">No tienes productos con control de stock. Actívalo en el producto para poder comprarlo.</p>
            @endunless
        </div>

        {{-- Datos de la compra --}}
        <div class="glass-card p-4 sm:p-6 space-y-4">
            <h3 class="text-white font-bold text-base">Datos de la compra</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="input-label" for="po-supplier">Proveedor</label>
                    <select id="po-supplier" name="supplier_id" class="input-field">
                        <option value="">Sin proveedor</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $order->supplier_id ?? request('proveedor')) === $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('dashboard.proveedores.index') }}#nuevo" class="text-[11px] text-tribio-cyan underline">+ Agregar proveedor</a>
                </div>
                <div>
                    <label class="input-label" for="po-expected">Fecha en que llega <span class="text-white/40 font-normal">(opcional)</span></label>
                    <input id="po-expected" type="date" name="expected_at" class="input-field" value="{{ old('expected_at', $order->expected_at?->toDateString()) }}">
                </div>
                <div>
                    <label class="input-label" for="po-extra">Flete y otros gastos (S/)</label>
                    <input id="po-extra" type="number" name="extra_costs" min="0" step="0.01" class="input-field" x-model.number="extra">
                    <p class="text-[11px] text-white/40 mt-1">Se reparte entre los productos según su valor: así ves cuánto te cuesta de verdad cada uno.</p>
                </div>
                <div>
                    <label class="input-label" for="po-notes">Notas <span class="text-white/40 font-normal">(opcional)</span></label>
                    <textarea id="po-notes" name="notes" rows="2" maxlength="1000" class="input-field">{{ old('notes', $order->notes) }}</textarea>
                </div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-3 text-sm flex flex-wrap justify-end gap-x-6 gap-y-1">
                <span class="text-white/60">Productos: <strong class="text-white" x-text="money(subtotal())"></strong></span>
                <span class="text-white/60">Gastos: <strong class="text-white" x-text="money(extra || 0)"></strong></span>
                <span class="text-white/60">Total: <strong class="text-white" x-text="money(total())"></strong></span>
            </div>
        </div>
    </form>
</div>

<script>

function purchaseForm(known, searchUrl, lines, extra) {
    let seq = 0;
    return {
        extra,
        // Every product seen so far (the order's own plus search results), by id.
        known: Object.fromEntries(known.map(p => [p.id, p])),
        lines: lines.map(line => ({ ...blank(), product_id: line.product_id, unit_id: line.unit_id || '', quantity: line.quantity, unit_cost: line.unit_cost })),
        init() { if (!this.lines.length) this.add(); },
        add() { this.lines.push(blank()); },
        product(line) { return this.known[line.product_id]; },
        unitsOf(line) { return this.product(line)?.units || []; },
        unit(line) { return this.unitsOf(line).find(u => u.id === line.unit_id); },
        unitName(line) { return this.unit(line)?.name.toLowerCase() || 'unidad'; },
        detail(p) { return (p.sku ? 'Código ' + p.sku + ' · ' : '') + p.stock + ' en stock'; },
        // Type-ahead: the server returns the best 20 matches; the latest request wins.
        openList(line) { line.open = true; if (!line.results.length) this.find(line); },
        find(line) {
            line.open = true; line.loading = true;
            line.ctrl?.abort();
            line.ctrl = new AbortController();
            fetch(searchUrl + '?q=' + encodeURIComponent(line.q.trim()), { headers: { Accept: 'application/json' }, signal: line.ctrl.signal })
                .then(r => r.ok ? r.json() : [])
                .then(items => { items.forEach(p => this.known[p.id] = p); line.results = items; line.active = 0; line.loading = false; })
                .catch(e => { if (e.name !== 'AbortError') line.loading = false; });
        },
        move(line, step) {
            if (!line.open) return this.openList(line);
            if (line.results.length) line.active = (line.active + step + line.results.length) % line.results.length;
        },
        choose(line, item) {
            if (!item) return;
            line.product_id = item.id; line.unit_id = ''; line.unit_cost = 0;
            line.open = false; line.q = ''; line.results = [];
            this.pickCost(line);
            // Skip auto-focus on touch screens: it would pop the keyboard open.
            if (!matchMedia('(pointer: coarse)').matches) this.$nextTick(() => document.getElementById('q' + line.key)?.select());
        },
        change(line) { line.product_id = ''; line.unit_id = ''; line.unit_cost = 0; this.$nextTick(() => line.open = false); },
        // Last cost is per base unit: a packaging costs factor × that.
        pickCost(line) {
            const product = this.product(line);
            if (!this.unit(line)) line.unit_id = '';
            if (product && !line.unit_cost) line.unit_cost = Math.round(product.cost * (this.unit(line)?.factor || 1) * 100) / 100;
        },
        subtotal() { return this.lines.reduce((sum, l) => sum + (l.quantity || 0) * (l.unit_cost || 0), 0); },
        total() { return this.subtotal() + (Number(this.extra) || 0); },
        money(value) { return 'S/ ' + Number(value || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        sync() {
            this.$refs.itemsJson.value = JSON.stringify(this.lines.filter(l => l.product_id)
                .map(l => ({ product_id: l.product_id, unit_id: l.unit_id || null, quantity: l.quantity, unit_cost: l.unit_cost })));
        },
    };
    function blank() { return { key: ++seq, product_id: '', unit_id: '', quantity: 1, unit_cost: 0, q: '', results: [], open: false, loading: false, active: 0, ctrl: null }; }
}
</script>
@endsection
