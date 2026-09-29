{{-- Precios por mayor: escalas de precio por cantidad total del producto y pedido mínimo.
     Se serializa en price_tiers_json; App\Actions\Products\SaveWholesaleSettings lo valida
     antes de guardar el producto. --}}
@php
    $wholesaleOld = old('price_tiers_json');
    $wholesaleTiers = $wholesaleOld !== null
        ? (json_decode($wholesaleOld, true) ?: [])
        : ($product ? \App\Services\Pricing\WholesalePricing::tiers($product) : []);
    $wholesaleMin = old('min_quantity', $product ? ($product->getAttributes()['min_quantity'] ?? null) : null);
@endphp
<div class="glass-card p-5 sm:p-6" x-data="{
        rows: @js(array_values($wholesaleTiers)),
        basePrice() { const input = document.querySelector('[name=price]'); return parseFloat(input ? input.value : 0) || 0; },
        saving(row) { const b = this.basePrice(); const p = parseFloat(row.price); return b > 0 && p > 0 && p < b ? Math.round((1 - p / b) * 100) : null; },
        add() { if (this.rows.length < {{ \App\Services\Pricing\WholesalePricing::MAX_TIERS }}) this.rows.push({ min_qty: '', price: '' }); },
     }">
    <h3 class="text-white font-bold mb-1 text-sm uppercase tracking-wider opacity-60 flex items-center gap-2">📦 Precios por mayor</h3>
    <p class="text-xs text-white/50 mb-4">Baja el precio por unidad según cuántas lleve el cliente. Se suman todas las tallas y colores de este producto. Los recargos de personalización no se descuentan.</p>

    <input type="hidden" name="price_tiers_json" :value="JSON.stringify(rows.filter(r => String(r.min_qty).trim() !== '' || String(r.price).trim() !== ''))">

    <div class="space-y-2">
        <template x-for="(row, index) in rows" :key="index">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="input-label text-xs" :for="'tier-qty-' + index">Desde (unidades)</label>
                    <input :id="'tier-qty-' + index" type="number" min="2" step="1" class="input-field !w-32" x-model="row.min_qty" placeholder="12">
                </div>
                <div>
                    <label class="input-label text-xs" :for="'tier-price-' + index">Precio por unidad (S/)</label>
                    <input :id="'tier-price-' + index" type="number" min="0.01" step="0.01" class="input-field !w-36" x-model="row.price" placeholder="25.00">
                </div>
                <span class="text-xs text-emerald-400 font-bold pb-3" x-show="saving(row) !== null" x-text="'-' + saving(row) + '%'"></span>
                <button type="button" class="btn-ghost !text-xs !px-2.5 mb-1" @click="rows.splice(index, 1)" aria-label="Quitar este precio">✕</button>
            </div>
        </template>
    </div>
    <button type="button" class="btn-ghost !text-xs mt-3" @click="add()" x-show="rows.length < {{ \App\Services\Pricing\WholesalePricing::MAX_TIERS }}">+ Agregar precio por cantidad</button>
    @error('price_tiers')<p class="text-xs text-red-400 mt-2">{{ $message }}</p>@enderror

    <div class="mt-5 max-w-xs">
        <label class="input-label" for="min_quantity">Pedido mínimo (opcional)</label>
        <input id="min_quantity" type="number" name="min_quantity" min="1" step="1" class="input-field" value="{{ $wholesaleMin }}" placeholder="Sin mínimo">
        <p class="text-[11px] text-white/40 mt-1">Por ejemplo 6 si solo vendes desde media docena.</p>
        @error('min_quantity')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
    </div>
</div>
