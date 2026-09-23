{{-- Venta por encargo: only rendered for stores that enabled made-to-order selling.
     Serializes the buyer-customization builder into customization_schema_json, which
     App\Actions\Products\SaveMadeToOrderSettings validates before the product is saved. --}}
@php
    $mtoOldSchema = old('customization_schema_json');
    $mtoConfig = [
        'mode' => old('sale_mode', $product?->sale_mode ?? \App\Models\Product::SALE_STOCK),
        'leadTime' => old('lead_time_days', $product?->lead_time_days),
        'fields' => $mtoOldSchema !== null ? (json_decode($mtoOldSchema, true) ?: []) : ($product?->customization_schema ?? []),
        'types' => \App\Services\MadeToOrder\CustomizationSchema::TYPES,
        'maxFields' => \App\Services\MadeToOrder\CustomizationSchema::MAX_FIELDS,
    ];
@endphp
<div class="glass-card p-5 sm:p-6" x-data="madeToOrderBuilder(@js($mtoConfig))">
    <h3 class="text-white font-bold mb-1 text-sm uppercase tracking-wider opacity-60 flex items-center gap-2">🧵 Venta por encargo</h3>
    <p class="text-xs text-white/50 mb-4">Para lo que haces a pedido (bordados, estampados, regalos personalizados): no descuenta stock y el comprador te deja sus datos al comprar.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4" role="radiogroup" aria-label="Cómo vendes este producto">
        <label class="mto-mode" :class="{ 'is-active': mode === 'stock' }">
            <input type="radio" name="sale_mode" value="stock" x-model="mode" class="sr-only">
            <span class="text-lg" aria-hidden="true">📦</span>
            <span><strong>Tengo stock</strong><small>Se vende lo que tienes en inventario.</small></span>
        </label>
        <label class="mto-mode" :class="{ 'is-active': mode === 'made_to_order' }">
            <input type="radio" name="sale_mode" value="made_to_order" x-model="mode" class="sr-only">
            <span class="text-lg" aria-hidden="true">✂️</span>
            <span><strong>Lo hago por encargo</strong><small>Se produce después de cada pedido.</small></span>
        </label>
    </div>

    <input type="hidden" name="customization_schema_json" :value="JSON.stringify(fields)">

    <div x-show="mode === 'made_to_order'" x-cloak class="space-y-4">
        <div class="max-w-xs">
            <label class="input-label" for="lead_time_days">¿En cuántos días lo tienes listo?</label>
            <input type="number" id="lead_time_days" name="lead_time_days" x-model="leadTime" min="0" max="365" class="input-field" placeholder="Ej: 5">
            <p class="text-xs text-white/50 mt-1">Se muestra como “Hecho a pedido · listo en X días”.</p>
        </div>

        <div>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <p class="input-label !mb-0">¿Qué le pides al comprador?</p>
                <span class="text-[11px] text-white/40" x-text="fields.length + ' / ' + maxFields"></span>
            </div>
            <div class="flex flex-wrap gap-2 mb-3" aria-label="Plantillas rápidas">
                <button type="button" class="btn-ghost text-xs" @click="preset('name')">+ Bordado con nombre</button>
                <button type="button" class="btn-ghost text-xs" @click="preset('logo')">+ Logo de empresa</button>
                <button type="button" class="btn-ghost text-xs" @click="preset('sizes')">+ Tallas por cantidad</button>
            </div>

            <div class="space-y-3">
                <template x-for="(field, index) in fields" :key="field._id">
                    <div class="mto-field">
                        <div class="flex flex-wrap items-center gap-2">
                            <select x-model="field.type" class="input-field !w-auto !min-h-[38px] text-xs" aria-label="Tipo de dato">
                                <template x-for="(label, value) in types" :key="value"><option :value="value" x-text="label" :selected="field.type === value"></option></template>
                            </select>
                            <input type="text" x-model="field.label" maxlength="60" class="input-field flex-1 min-w-[160px] !min-h-[38px] text-sm" placeholder="Nombre que verá el comprador (ej: Texto a bordar)" aria-label="Nombre del dato">
                            <div class="flex items-center gap-1 ml-auto">
                                <button type="button" class="mto-icon" @click="move(index, -1)" :disabled="index === 0" aria-label="Subir">↑</button>
                                <button type="button" class="mto-icon" @click="move(index, 1)" :disabled="index === fields.length - 1" aria-label="Bajar">↓</button>
                                <button type="button" class="mto-icon text-red-400" @click="fields.splice(index, 1)" aria-label="Quitar">✕</button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-end gap-3 mt-3">
                            <label class="flex items-center gap-2 text-xs text-white/70 cursor-pointer min-h-[38px]">
                                <input type="checkbox" x-model="field.required" class="w-4 h-4 accent-tribio-cyan"> Obligatorio
                            </label>
                            <template x-if="field.type === 'text' || field.type === 'textarea'">
                                <label class="text-xs text-white/60">Máx. caracteres
                                    <input type="number" x-model.number="field.max" min="1" :max="field.type === 'text' ? 200 : 500" class="input-field !w-24 !min-h-[38px] mt-1">
                                </label>
                            </template>
                            <template x-if="['text', 'textarea', 'file'].includes(field.type)">
                                <label class="text-xs text-white/60">Costo extra por unidad (S/)
                                    <input type="number" x-model.number="field.price" min="0" step="0.01" class="input-field !w-28 !min-h-[38px] mt-1" placeholder="0">
                                </label>
                            </template>
                            <template x-if="field.type === 'sizes'">
                                <label class="text-xs text-white/60 flex-1 min-w-[200px]">Tallas (separadas por coma)
                                    <input type="text" :value="(field.sizes || []).join(', ')" @input="field.sizes = $event.target.value.split(',').map(s => s.trim()).filter(Boolean)" class="input-field !min-h-[38px] mt-1" placeholder="S, M, L, XL">
                                </label>
                            </template>
                        </div>

                        <template x-if="field.type === 'choice'">
                            <div class="mt-3 space-y-2">
                                <p class="text-xs text-white/50">Opciones (el costo extra se suma al precio por unidad)</p>
                                <template x-for="(option, optionIndex) in field.options" :key="optionIndex">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <input type="text" x-model="option.label" maxlength="40" class="input-field flex-1 min-w-[140px] !min-h-[38px] text-sm" placeholder="Ej: Dorado">
                                        <input type="number" x-model.number="option.price" min="0" step="0.01" class="input-field !w-24 !min-h-[38px] text-sm" placeholder="+ S/ 0" aria-label="Costo extra">
                                        <label class="flex items-center gap-1 text-[11px] text-white/50" title="Color de muestra (opcional)">
                                            <input type="checkbox" :checked="!!option.color" @change="option.color = $event.target.checked ? '#C9A227' : null" class="w-3.5 h-3.5 accent-tribio-cyan"> Color
                                        </label>
                                        <input type="color" x-show="option.color" x-model="option.color" class="w-9 h-9 rounded-md border-0 bg-transparent cursor-pointer" aria-label="Color de muestra">
                                        <button type="button" class="mto-icon text-red-400" @click="field.options.splice(optionIndex, 1)" aria-label="Quitar opción">✕</button>
                                    </div>
                                </template>
                                <button type="button" class="btn-ghost text-xs" @click="field.options.push({ label: '', price: 0, color: null })">+ Agregar opción</button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap gap-2 mt-3" x-show="fields.length < maxFields">
                <button type="button" class="btn-secondary text-xs" @click="add('text')">+ Agregar dato</button>
            </div>
            <p x-show="fields.length === 0" class="text-xs text-white/40 mt-2">Sin datos extra: el comprador solo elige la cantidad.</p>
            @error('customization_schema')<p class="text-xs text-red-400 mt-2">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

@once
@push('scripts')
<style>
    .mto-mode { display: flex; gap: 12px; align-items: flex-start; padding: 14px; border-radius: 12px; border: 1px solid var(--dash-line, #e7edf4); background: #fff; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
    .mto-mode strong { display: block; font-size: 13px; color: #24364b; }
    .mto-mode small { display: block; font-size: 11px; color: #64758a; margin-top: 2px; }
    .mto-mode.is-active { border-color: #2176d2; box-shadow: 0 0 0 3px #eaf3fd; }
    .mto-mode:focus-within { outline: 3px solid #2176d2; outline-offset: 2px; }
    .mto-field { padding: 12px; border-radius: 12px; border: 1px solid var(--dash-line, #e7edf4); background: #f8fafd; }
    .mto-icon { min-width: 32px; min-height: 32px; border-radius: 8px; color: #536476; }
    .mto-icon:hover { background: #eef5fc; }
    .mto-icon:disabled { opacity: .35; cursor: not-allowed; }
</style>
<script>
document.addEventListener('alpine:init', () => {
    let seq = 0;
    const withId = (field) => ({ required: false, max: 40, price: 0, options: [], sizes: [], ...field, _id: ++seq });
    const presets = {
        name: [
            { type: 'text', label: 'Texto a bordar', required: true, max: 20 },
            { type: 'choice', label: 'Color de hilo', required: true, options: [
                { label: 'Dorado', price: 0, color: '#C9A227' }, { label: 'Plateado', price: 0, color: '#B8BCC2' },
                { label: 'Blanco', price: 0, color: '#FFFFFF' }, { label: 'Negro', price: 0, color: '#1E1D1B' },
            ] },
        ],
        logo: [
            { type: 'file', label: 'Tu logo o diseño', required: true },
            { type: 'choice', label: 'Posición', required: true, options: [
                { label: 'Pecho', price: 0, color: null }, { label: 'Espalda', price: 8, color: null }, { label: 'Manga', price: 5, color: null },
            ] },
        ],
        sizes: [{ type: 'sizes', label: 'Tallas', required: true, sizes: ['S', 'M', 'L', 'XL'] }],
    };

    Alpine.data('madeToOrderBuilder', (config) => ({
        mode: config.mode,
        leadTime: config.leadTime,
        types: config.types,
        maxFields: config.maxFields,
        fields: (config.fields || []).map(withId),
        add(type) {
            if (this.fields.length >= this.maxFields) return;
            this.fields.push(withId({ type, label: '', options: type === 'choice' ? [{ label: '', price: 0, color: null }] : [] }));
        },
        preset(name) {
            presets[name].forEach((field) => {
                if (this.fields.length < this.maxFields && !this.fields.some((f) => f.type === field.type && (field.type === 'sizes' || f.label === field.label))) {
                    this.fields.push(withId(JSON.parse(JSON.stringify(field))));
                }
            });
        },
        move(index, step) {
            const target = index + step;
            if (target < 0 || target >= this.fields.length) return;
            const [field] = this.fields.splice(index, 1);
            this.fields.splice(target, 0, field);
        },
    }));
});
</script>
@endpush
@endonce
