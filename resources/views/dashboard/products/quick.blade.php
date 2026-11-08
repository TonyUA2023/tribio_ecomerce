@extends('layouts.dashboard')
@section('title', 'Alta rápida')
@section('page_title', '⚡ Alta rápida de productos')
@section('content')
<div class="qa-app w-full max-w-[1600px] mx-auto space-y-4"
     x-data="quickAdd({ saveUrl: @js(route('dashboard.productos.rapido.store')), maxRows: {{ (int) $maxRows }} })"
     @keydown.ctrl.enter.window.prevent="save()" @keydown.meta.enter.window.prevent="save()">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-3xl">Llena la tabla como una hoja de cálculo. <strong>Enter</strong> pasa al siguiente campo y, al terminar la fila, crea otra. Escanea el código de barras en la primera columna, o <strong>pega desde Excel</strong> (código, nombre, categoría, costo, precio, stock, unidad).</p>
        <a href="{{ route('dashboard.productos.index') }}" class="pos-chip"><x-dashboard-icon name="back"/>Productos</a>
    </div>

    <div class="qa-result" x-show="result" x-cloak role="status">
        <span><strong x-text="result?.message"></strong> Ya están en tu inventario con su stock y costo.</span>
        <a :href="result?.list_url" class="pos-chip">Ver productos</a>
    </div>

    <div class="qa-options">
        <label class="qa-margin">Margen sugerido
            <span><input type="number" min="0" max="95" step="1" x-model.number="margin" aria-describedby="qa-margin-help"> %</span>
        </label>
        <p id="qa-margin-help">Al poner el costo, el precio se llena solo con este margen (precio = costo ÷ (1 − margen)). Puedes cambiarlo.</p>
    </div>

    <div class="qa-grid" x-ref="grid" role="table" aria-label="Productos nuevos" @paste="onPaste($event)">
        <div class="qa-head" role="row">
            <span role="columnheader">Código / barras</span><span role="columnheader">Nombre *</span><span role="columnheader">Categoría</span>
            <span role="columnheader">Costo</span><span role="columnheader">Precio *</span><span role="columnheader">Stock</span><span role="columnheader">Unidad</span><span role="columnheader" class="sr-only">Quitar</span>
        </div>
        <template x-for="(row, r) in rows" :key="row.key">
            <div class="qa-row" :class="rowClass(row, r)" role="row">
                <template x-for="(col, c) in cols" :key="col.key">
                    <label class="qa-cell" :class="'qa-' + col.key" role="cell">
                        <span class="qa-label" x-text="col.label"></span>
                        <input :data-r="r" :data-c="c" x-model="row[col.key]" :inputmode="col.number ? 'decimal' : 'text'" :list="col.list" autocomplete="off" :maxlength="col.max"
                               :aria-label="col.label + ' de la fila ' + (r + 1)" :aria-invalid="!!errors[r + '.' + col.key]"
                               :placeholder="col.key === 'unit' ? 'unidad' : ''"
                               @keydown.enter.prevent="next(r, c)" @keydown.arrow-down.prevent="go(r + 1, c)" @keydown.arrow-up.prevent="go(r - 1, c)"
                               @input="onInput(row, col.key)" @blur="onBlur(row, col.key)" @focus="$event.target.select()">
                        <small class="qa-error" x-show="errors[r + '.' + col.key]" x-text="errors[r + '.' + col.key]" role="alert"></small>
                        <small class="qa-hint" x-show="col.key === 'price' && marginText(row) && !errors[r + '.price']" x-text="marginText(row)" :class="marginClass(row)"></small>
                    </label>
                </template>
                <button type="button" class="qa-remove" @click="remove(r)" :aria-label="'Quitar la fila ' + (r + 1)"><x-dashboard-icon name="close"/></button>
            </div>
        </template>
    </div>

    <datalist id="qa-categories">@foreach($categories as $name)<option value="{{ $name }}">@endforeach</datalist>
    <datalist id="qa-units"><option value="unidad"><option value="kg"><option value="metro"><option value="litro"><option value="caja"><option value="bolsa"><option value="paquete"><option value="par"><option value="juego"><option value="galón"></datalist>

    <div class="qa-bar">
        <div class="qa-bar-info" role="status">
            <strong x-text="filled().length + (filled().length === 1 ? ' producto' : ' productos')"></strong>
            <span x-show="filled().length">· inventario a costo <strong x-text="money(inventoryValue())"></strong></span>
            <span class="qa-bar-error" x-show="message" x-text="message" role="alert"></span>
        </div>
        <div class="qa-bar-actions">
            <button type="button" class="btn-ghost" @click="addRow(true)">+ Fila</button>
            <button type="button" class="btn-ghost" x-show="filled().length" @click="clear()">Limpiar</button>
            <button type="button" class="btn-primary" :disabled="saving || !filled().length" :class="(saving || !filled().length) && 'opacity-50 cursor-not-allowed'" @click="save()">
                <span x-text="saving ? 'Guardando…' : 'Guardar ' + (filled().length || '') + (filled().length === 1 ? ' producto' : (filled().length ? ' productos' : ''))"></span> <kbd>Ctrl+Enter</kbd>
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function quickAdd(config) {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        let seq = 0;
        const blank = () => ({ key: ++seq, sku: '', name: '', category: '', cost: '', price: '', stock: '', unit: '', priceTouched: false });
        // Same order as the pasted Excel columns.
        const cols = [
            { key: 'sku', label: 'Código', max: 50 },
            { key: 'name', label: 'Nombre', max: 255 },
            { key: 'category', label: 'Categoría', list: 'qa-categories', max: 80 },
            { key: 'cost', label: 'Costo', number: true, max: 12 },
            { key: 'price', label: 'Precio', number: true, max: 12 },
            { key: 'stock', label: 'Stock', number: true, max: 9 },
            { key: 'unit', label: 'Unidad', list: 'qa-units', max: 20 },
        ];
        const num = (value) => {
            if (value === '' || value === null || value === undefined) return null;
            const clean = String(value).replace(/[^\d,.\-]/g, '').replace(',', '.');
            const n = Number(clean);
            return clean !== '' && Number.isFinite(n) ? n : null;
        };
        return {
            ...config, cols, rows: Array.from({ length: 6 }, blank), margin: 30, errors: {}, message: '', saving: false, result: null,
            money(value) { return 'S/ ' + Number(value || 0).toFixed(2); },
            isFilled(row) { return cols.some(col => String(row[col.key]).trim() !== ''); },
            filled() { return this.rows.filter(row => this.isFilled(row)); },
            inventoryValue() { return this.filled().reduce((sum, row) => sum + (num(row.cost) || 0) * (Math.floor(num(row.stock)) || 0), 0); },
            rowClass(row, r) {
                const bad = cols.some(col => this.errors[r + '.' + col.key]);
                return { 'is-error': bad, 'is-empty': !this.isFilled(row) };
            },
            marginText(row) {
                const cost = num(row.cost), price = num(row.price);
                if (!cost || !price || price <= 0) return '';
                return 'Margen ' + Math.round((price - cost) * 100 / price) + ' %';
            },
            marginClass(row) {
                const cost = num(row.cost), price = num(row.price);
                if (!cost || !price) return '';
                return price < cost ? 'is-loss' : 'is-ok';
            },
            addRow(focus = false) {
                this.rows.push(blank());
                if (focus) this.$nextTick(() => this.go(this.rows.length - 1, 0));
            },
            remove(r) {
                this.rows.splice(r, 1);
                if (!this.rows.length) this.addRow();
                this.errors = {};
            },
            clear() { this.rows = Array.from({ length: 6 }, blank); this.errors = {}; this.message = ''; },
            go(r, c) {
                if (r < 0 || r >= this.rows.length) return;
                const el = this.$refs.grid.querySelector(`[data-r="${r}"][data-c="${c}"]`);
                if (el) { el.focus(); el.select(); }
            },
            // Enter: next field; after the last one, the next row (a new one at the end).
            next(r, c) {
                if (c < cols.length - 1) return this.go(r, c + 1);
                if (r === this.rows.length - 1) { this.addRow(); this.$nextTick(() => this.go(r + 1, 0)); return; }
                this.go(r + 1, 0);
            },
            onInput(row, key) {
                if (key === 'price') row.priceTouched = String(row.price).trim() !== '';
                if (key === 'cost' && !row.priceTouched) this.suggest(row);
                this.errors = {};
                this.message = '';
            },
            onBlur(row, key) {
                if (['cost', 'price', 'stock'].includes(key) && row[key] !== '') {
                    const n = num(row[key]);
                    row[key] = n === null ? row[key] : (key === 'stock' ? String(Math.floor(n)) : String(Math.round(n * 100) / 100));
                }
                if (key === 'cost' && !row.priceTouched) this.suggest(row);
            },
            // price = cost / (1 − margin), to the nearest 10 céntimos
            suggest(row) {
                const cost = num(row.cost), m = Number(this.margin);
                if (!cost || cost <= 0 || m < 0 || m >= 95) { row.price = ''; return; }
                row.price = (Math.ceil(cost / (1 - m / 100) * 10) / 10).toFixed(2);
            },
            // Pasting rows copied from Excel / Google Sheets: tab-separated columns, one product per line.
            onPaste(event) {
                const text = (event.clipboardData || window.clipboardData).getData('text');
                if (!/[\t\n]/.test(text.trim())) return; // a single value pastes normally
                const target = event.target.closest('[data-r]');
                if (!target) return;
                event.preventDefault();
                const startRow = Number(target.dataset.r), startCol = Number(target.dataset.c);
                const lines = text.replace(/\r/g, '').split('\n').filter(line => line.trim() !== '').slice(0, this.maxRows);
                lines.forEach((line, i) => {
                    while (this.rows.length <= startRow + i) this.rows.push(blank());
                    const row = this.rows[startRow + i];
                    line.split('\t').forEach((value, j) => {
                        const col = cols[startCol + j];
                        if (!col) return;
                        row[col.key] = col.number ? (num(value) === null ? value.trim() : String(col.key === 'stock' ? Math.floor(num(value)) : num(value))) : value.trim();
                        if (col.key === 'price') row.priceTouched = row.price !== '';
                    });
                    if (!row.priceTouched && row.cost !== '') this.suggest(row);
                });
                this.errors = {}; this.message = '';
            },
            async save() {
                if (this.saving) return;
                const sent = this.rows.map((row, index) => ({ row, index })).filter(({ row }) => this.isFilled(row));
                if (!sent.length) return;
                this.saving = true; this.errors = {}; this.message = '';
                try {
                    const response = await fetch(this.saveUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ rows: sent.map(({ row }) => ({ sku: row.sku, name: row.name, category: row.category, cost: row.cost, price: row.price, stock: row.stock, unit: row.unit })) }) });
                    const data = await response.json().catch(() => ({}));
                    if (response.ok) {
                        this.result = data;
                        this.clear();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        document.getElementById('dashboard-content')?.scrollTo({ top: 0, behavior: 'smooth' });
                        this.$nextTick(() => this.go(0, 0));
                    } else if (response.status === 422 && data.errors) {
                        // errors come keyed by position in what was sent: map them back to the table rows
                        let first = null;
                        Object.entries(data.errors).forEach(([key, messages]) => {
                            const m = key.match(/^rows\.(\d+)\.(\w+)$/);
                            if (m && sent[m[1]]) { const at = sent[m[1]].index; this.errors[at + '.' + m[2]] = messages[0]; first ??= [at, cols.findIndex(col => col.key === m[2])]; }
                            else this.message = messages[0];
                        });
                        this.errors = { ...this.errors };
                        const n = Object.keys(this.errors).length;
                        if (n) this.message = n === 1 ? 'Revisa 1 campo marcado.' : 'Revisa los ' + n + ' campos marcados.';
                        if (first) this.$nextTick(() => this.go(first[0], Math.max(0, first[1])));
                    } else {
                        this.message = data.message || 'No se pudo guardar. Inténtalo de nuevo.';
                    }
                } catch (e) {
                    this.message = 'Sin conexión. No se guardó nada: vuelve a intentarlo.';
                } finally { this.saving = false; }
            },
        };
    }
</script>
@endpush
