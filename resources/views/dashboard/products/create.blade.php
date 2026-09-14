@extends('layouts.dashboard')
@section('title', 'Crear Producto')
@section('page_title', '+ Crear Producto')
@section('content')
<form method="POST" action="{{ route('dashboard.productos.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Columna Izquierda (2/3) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Info General --}}
            <div class="glass-card p-6">
                <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">Información General</h3>
                <div class="space-y-4">
                    <div>
                        <label class="input-label">Nombre del producto *</label>
                        <input type="text" name="name" class="input-field" value="{{ old('name') }}" required placeholder="Ej: Zapatillas Nike Air">
                    </div>
                    <div>
                        <label class="input-label">Descripción corta</label>
                        <input type="text" name="short_description" class="input-field" value="{{ old('short_description') }}" placeholder="Resumen rápido (máx 200 caracteres)" maxlength="200">
                    </div>
                    <div>
                        <label class="input-label">Descripción completa</label>
                        <textarea name="description" class="input-field" rows="6" placeholder="Describe los detalles de tu producto...">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Precios Multi-País y Origen --}}
            @php
                $exchangeRates = app(\App\Services\ExchangeRateService::class)->getRates();
                $enabledCountries = $store->getEnabledCountriesWithDetails();
                $currenciesMap = [];
                foreach ($enabledCountries as $cCode => $cInfo) {
                    $curr = $cInfo['currency'];
                    if ($curr === 'PEN') continue;
                    if (!isset($currenciesMap[$curr])) {
                        $currenciesMap[$curr] = [
                            'currency' => $curr,
                            'symbol'   => $cInfo['symbol'],
                            'decimals' => $cInfo['decimals'],
                            'countries'=> [$cInfo['name']],
                            'flags'    => [$cInfo['flag']],
                            'codes'    => [$cCode],
                        ];
                    } else {
                        $currenciesMap[$curr]['countries'][] = $cInfo['name'];
                        $currenciesMap[$curr]['flags'][]     = $cInfo['flag'];
                        $currenciesMap[$curr]['codes'][]     = $cCode;
                    }
                }
            @endphp
            <div class="glass-card p-6" x-data="{
                penPrice: '{{ old('price', '') }}',
                comparePenPrice: '{{ old('compare_price', '') }}',
                rates: {{ json_encode($exchangeRates) }},
                currencies: {
                    @foreach($currenciesMap as $cur => $meta)
                        '{{ $cur }}': {
                            manual: {{ old('currency_prices.' . $cur) ? 'true' : 'false' }},
                            price: '{{ old('currency_prices.' . $cur, '') }}',
                            compare: '{{ old('compare_currency_prices.' . $cur, '') }}'
                        },
                    @endforeach
                },
                calcRaw(amount, cur) {
                    const val = parseFloat(amount);
                    if (isNaN(val) || val <= 0) return '';
                    const rate = this.rates[cur] || 1;
                    const res = val * rate;
                    const decimals = (cur === 'COP' || cur === 'CLP' || cur === 'ARS') ? 0 : 2;
                    return res.toFixed(decimals);
                },
                calcRate(amount, cur) {
                    const raw = this.calcRaw(amount, cur);
                    if (!raw) return '0.00';
                    const num = parseFloat(raw);
                    return num.toLocaleString('en-US', {
                        minimumFractionDigits: (cur === 'COP' || cur === 'CLP' || cur === 'ARS') ? 0 : 2,
                        maximumFractionDigits: (cur === 'COP' || cur === 'CLP' || cur === 'ARS') ? 0 : 2
                    });
                },
                syncUsd() {
                    const usdEl = document.getElementById('field_price_usd');
                    if (usdEl && this.currencies['USD']) {
                        usdEl.value = this.currencies['USD'].manual ? this.currencies['USD'].price : this.calcRaw(this.penPrice, 'USD');
                    }
                    const usdCompEl = document.getElementById('field_compare_price_usd');
                    if (usdCompEl && this.currencies['USD']) {
                        usdCompEl.value = this.currencies['USD'].manual ? this.currencies['USD'].compare : this.calcRaw(this.comparePenPrice, 'USD');
                    }
                }
            }" x-init="$watch('penPrice', () => syncUsd()); $watch('comparePenPrice', () => syncUsd()); syncUsd();">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-200">
                    <div>
                        <h3 class="text-slate-900 font-extrabold text-sm uppercase tracking-wider flex items-center gap-2">
                            <span>💰</span> Precios y Tipo de Cambio Multi-País
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Ingresa el precio base en Soles (PEN) y el sistema autocalculará las demás divisas con la API en vivo. Puedes personalizar cualquier moneda a mano.</p>
                    </div>
                    <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold font-mono hidden sm:inline-flex items-center gap-1 shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> API Activa (PEN Base)
                    </span>
                </div>

                {{-- Precio Base en Soles --}}
                <div class="p-4 rounded-xl bg-sky-50/60 border-2 border-sky-300 mb-5 shadow-2xs">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-black text-sky-950 flex items-center gap-1.5 uppercase tracking-wider">
                            <span class="text-base">🇵🇪</span> Moneda Base: Perú (Soles - PEN)
                        </span>
                        <span class="text-xs font-semibold text-sky-900/70">Referencia oficial para conversiones</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs font-bold text-slate-900 block mb-1.5">Precio de Venta (S/.) *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <span class="text-xs text-sky-800 font-black select-none">S/</span>
                                </div>
                                <input type="number" name="price" x-model="penPrice" 
                                       class="w-full rounded-xl bg-white border-2 border-sky-300 focus:border-sky-600 focus:ring-3 focus:ring-sky-500/20 py-2.5 text-right font-black text-slate-900 text-base outline-none transition shadow-2xs" 
                                       style="padding-left: 2.75rem !important; padding-right: 0.875rem !important;" 
                                       value="{{ old('price') }}" step="0.01" min="0" required placeholder="0.00">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-700 block mb-1.5">Precio Anterior / Antes (S/.)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                    <span class="text-xs text-slate-400 font-bold select-none">S/</span>
                                </div>
                                <input type="number" name="compare_price" x-model="comparePenPrice" 
                                       class="w-full rounded-xl bg-white border-2 border-slate-300 focus:border-slate-500 focus:ring-3 focus:ring-slate-400/20 py-2.5 text-right font-bold text-slate-700 text-base outline-none transition shadow-2xs" 
                                       style="padding-left: 2.75rem !important; padding-right: 0.875rem !important;" 
                                       value="{{ old('compare_price') }}" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Precios en otras monedas de los países activos --}}
                @if(!empty($currenciesMap))
                    <div class="space-y-3.5 mb-5">
                        <div class="flex items-center justify-between pb-1">
                            <label class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                <span>🌍</span> Precios en Países Habilitados:
                            </label>
                            <span class="text-xs text-slate-600 font-medium">Alterna entre 'Auto (T.C.)' o 'Personalizado'</span>
                        </div>
                        
                        @foreach($currenciesMap as $cur => $meta)
                            <div class="p-4 rounded-xl transition-all duration-200"
                                 :class="currencies['{{ $cur }}'].manual 
                                         ? 'bg-amber-50/70 border-2 border-amber-400 shadow-xs' 
                                         : 'bg-white border-2 border-slate-200 hover:border-slate-300 shadow-2xs'">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        {{-- Country Code Badges --}}
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            @foreach($meta['codes'] as $idx => $cCode)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-300 text-xs font-extrabold text-slate-900 shadow-2xs">
                                                    <span class="text-sm leading-none">{{ $meta['flags'][$idx] ?? '' }}</span>
                                                    <span class="font-mono">{{ $cCode }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-black text-slate-950 font-mono tracking-tight">{{ $cur }}</span>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-sky-100 text-sky-900 border border-sky-300">
                                                    {{ $meta['symbol'] }}
                                                </span>
                                                <span class="text-xs font-medium text-slate-600">• {{ implode(', ', $meta['countries']) }}</span>
                                            </div>
                                            <div class="text-xs text-slate-700 mt-1 flex items-center gap-1.5" x-show="!currencies['{{ $cur }}'].manual">
                                                <span class="font-semibold text-slate-600">T.C. sugerido:</span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-100 border border-emerald-300 text-emerald-900 font-extrabold font-mono text-xs shadow-2xs">
                                                    {{ $meta['symbol'] }} <span x-text="calcRate(penPrice, '{{ $cur }}')"></span>
                                                </span>
                                                <template x-if="comparePenPrice > 0">
                                                    <span class="text-slate-400 line-through text-xs font-mono ml-1 font-semibold">
                                                        {{ $meta['symbol'] }} <span x-text="calcRate(comparePenPrice, '{{ $cur }}')"></span>
                                                    </span>
                                                </template>
                                            </div>
                                            <div class="text-xs text-amber-950 mt-1 flex items-center gap-1.5" x-show="currencies['{{ $cur }}'].manual" style="display: none;">
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md bg-amber-200 text-amber-950 text-xs font-bold border border-amber-300 shadow-2xs">
                                                    <span>✍️</span> Precio manual activo (anula tipo de cambio automático)
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        {{-- Selector Auto vs Manual --}}
                                        <div class="inline-flex rounded-xl p-1 bg-slate-100 border-2 border-slate-200 text-xs font-semibold shadow-inner">
                                            <button type="button" @click="currencies['{{ $cur }}'].manual = false; currencies['{{ $cur }}'].price = ''; syncUsd();"
                                                    :class="!currencies['{{ $cur }}'].manual 
                                                            ? 'bg-sky-600 text-white font-bold shadow-xs' 
                                                            : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                                    class="px-3 py-1.5 rounded-lg transition-all cursor-pointer flex items-center gap-1.5">
                                                <span>🔄</span> Auto (T.C.)
                                            </button>
                                            <button type="button" @click="currencies['{{ $cur }}'].manual = true; if(!currencies['{{ $cur }}'].price && penPrice) currencies['{{ $cur }}'].price = calcRaw(penPrice, '{{ $cur }}'); syncUsd();"
                                                    :class="currencies['{{ $cur }}'].manual 
                                                            ? 'bg-amber-500 text-slate-950 font-black shadow-xs' 
                                                            : 'text-slate-600 hover:text-slate-900 font-semibold'"
                                                    class="px-3 py-1.5 rounded-lg transition-all cursor-pointer flex items-center gap-1.5">
                                                <span>✍️</span> Personalizado
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Input Manual cuando está activado --}}
                                <div x-show="currencies['{{ $cur }}'].manual" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3.5 pt-3.5 border-t-2 border-amber-200" style="display: none;">
                                    <div>
                                        <label class="text-xs font-bold text-slate-900 block mb-1.5 flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                            <span>Precio fijado en {{ $cur }} ({{ $meta['symbol'] }}) *</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                                <span class="text-xs text-slate-900 font-black select-none">{{ $meta['symbol'] }}</span>
                                            </div>
                                            <input type="number" step="{{ $meta['decimals'] === 0 ? '1' : '0.01' }}" min="0"
                                                   name="currency_prices[{{ $cur }}]"
                                                   x-model="currencies['{{ $cur }}'].price"
                                                   @input="syncUsd()"
                                                   class="w-full rounded-xl bg-white border-2 border-amber-400 focus:border-amber-500 focus:ring-3 focus:ring-amber-400/25 py-2 text-right font-black text-slate-950 text-sm outline-none transition shadow-2xs"
                                                   style="padding-left: 2.5rem !important; padding-right: 0.875rem !important;"
                                                   placeholder="0.00">
                                        </div>
                                        <p class="text-[11px] text-amber-950 font-semibold mt-1">Este será el precio exacto cobrado a clientes de {{ implode(', ', $meta['countries']) }}.</p>
                                    </div>
                                    <div>
                                        <label class="text-xs font-bold text-slate-700 block mb-1.5">
                                            Precio anterior / tachado (opcional)
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                                <span class="text-xs text-slate-500 font-bold select-none">{{ $meta['symbol'] }}</span>
                                            </div>
                                            <input type="number" step="{{ $meta['decimals'] === 0 ? '1' : '0.01' }}" min="0"
                                                   name="compare_currency_prices[{{ $cur }}]"
                                                   x-model="currencies['{{ $cur }}'].compare"
                                                   @input="syncUsd()"
                                                   class="w-full rounded-xl bg-white border-2 border-slate-300 focus:border-slate-500 focus:ring-3 focus:ring-slate-400/20 py-2 text-right font-bold text-slate-800 text-sm outline-none transition shadow-2xs"
                                                   style="padding-left: 2.5rem !important; padding-right: 0.875rem !important;"
                                                   placeholder="0.00">
                                        </div>
                                        <p class="text-[11px] text-slate-500 font-medium mt-1">Muestra una rebaja tachada si es mayor al precio de venta.</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Hidden fields para mantener retrocompatibilidad con price_usd --}}
                <input type="hidden" name="price_usd" id="field_price_usd" value="{{ old('price_usd') }}">
                <input type="hidden" name="compare_price_usd" id="field_compare_price_usd" value="{{ old('compare_price_usd') }}">

                <div class="border-t border-white/5 pt-4">
                    <label class="input-label">Código del producto origen</label>
                    <input type="text" name="origin_code" class="input-field" value="{{ old('origin_code') }}" placeholder="Ej: COD-ORI-99">
                </div>
            </div>

            {{-- Inventario --}}
            <div class="glass-card p-6">
                <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">Inventario y Stock</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="input-label">SKU / Código (Auto-generado si queda vacío)</label>
                        <input type="text" name="sku" class="input-field" value="{{ old('sku') }}" placeholder="Dejar vacío para auto-generar">
                    </div>
                    <div>
                        <label class="input-label">Stock inicial</label>
                        <input type="number" name="stock" class="input-field" value="{{ old('stock', 0) }}" min="0">
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="track_stock" id="track_stock" value="1" class="w-4 h-4 accent-tribio-purple" {{ old('track_stock') ? 'checked' : '' }}>
                    <label for="track_stock" class="text-white/70 text-sm cursor-pointer select-none">Controlar inventario (descontar stock en cada venta)</label>
                </div>
            </div>

            {{-- Variantes y Atributos (Colores, Tallas, Tamaños, etc.) --}}
            <div class="glass-card p-6" x-data="{
                hasVariants: false,
                options: [
                    { name: 'Color', valuesText: 'Negro, Blanco', values: ['Negro', 'Blanco'] },
                    { name: 'Talla', valuesText: 'S, M, L', values: ['S', 'M', 'L'] }
                ],
                variants: [],
                addOption() {
                    this.options.push({ name: '', valuesText: '', values: [] });
                },
                removeOption(index) {
                    this.options.splice(index, 1);
                    this.generateVariants();
                },
                updateOptionValues(opt) {
                    opt.values = opt.valuesText.split(',').map(s => s.trim()).filter(s => s.length > 0);
                    this.generateVariants();
                },
                generateVariants() {
                    const validOptions = this.options.filter(o => o.name.trim() && o.values.length > 0);
                    if (validOptions.length === 0) {
                        this.variants = [];
                        return;
                    }

                    // Producto cartesiano
                    const cartesian = (arrays) => {
                        return arrays.reduce((acc, curr) => {
                            return acc.flatMap(a => curr.map(c => [...a, c]));
                        }, [[]]);
                    };

                    const combinations = cartesian(validOptions.map(o => o.values));
                    const newVariants = [];

                    combinations.forEach((combo) => {
                        const attributes = {};
                        validOptions.forEach((o, i) => {
                            attributes[o.name] = combo[i];
                        });
                        const title = combo.join(' / ');

                        const existing = this.variants.find(v => v.title === title);
                        newVariants.push({
                            id: existing ? existing.id : null,
                            title: title,
                            attributes: attributes,
                            sku: existing && existing.sku ? existing.sku : '',
                            price: existing && existing.price !== undefined ? existing.price : '',
                            stock: existing && existing.stock !== undefined ? existing.stock : 10,
                            is_active: existing ? existing.is_active : true
                        });
                    });

                    this.variants = newVariants;
                },
                init() {
                    this.generateVariants();
                }
            }">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60">Variables y Variantes</h3>
                        <p class="text-xs text-slate-400">Permite a los clientes elegir colores, tallas, tamaños, etc.</p>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer bg-white/5 px-3 py-1.5 rounded-lg border border-white/10 hover:border-tribio-cyan transition">
                        <input type="checkbox" name="has_variants" value="1" x-model="hasVariants" class="w-4 h-4 accent-tribio-cyan rounded">
                        <span class="text-xs font-bold text-tribio-cyan">Habilitar Variantes</span>
                    </label>
                </div>

                <div x-show="hasVariants" style="display: none;" class="space-y-6 pt-3 border-t border-white/10">
                    <input type="hidden" name="variant_options_json" :value="JSON.stringify(options.filter(o => o.name.trim() && o.values.length > 0))">
                    <input type="hidden" name="variants_json" :value="JSON.stringify(variants)">

                    {{-- Lista de Opciones --}}
                    <div class="space-y-3">
                        <label class="input-label text-xs font-bold text-white/80">1. Define las opciones (ej: Color, Talla, Tamaño)</label>
                        <template x-for="(opt, idx) in options" :key="idx">
                            <div class="flex gap-2 items-center bg-white/5 p-3 rounded-xl border border-white/10">
                                <div class="w-1/3">
                                    <input type="text" x-model="opt.name" @input="generateVariants()" placeholder="Nombre (ej: Color)" class="input-field text-xs py-1.5">
                                </div>
                                <div class="flex-1">
                                    <input type="text" x-model="opt.valuesText" @input="updateOptionValues(opt)" placeholder="Valores separados por comas (ej: Negro, Blanco, Rojo)" class="input-field text-xs py-1.5">
                                </div>
                                <button type="button" @click="removeOption(idx)" class="text-red-400 hover:text-red-300 px-2 py-1 text-sm font-bold">✕</button>
                            </div>
                        </template>

                        <button type="button" @click="addOption()" class="btn-ghost text-xs py-1.5 px-3 border border-white/10 hover:border-tribio-cyan">
                            + Agregar otra opción
                        </button>
                    </div>

                    {{-- Matriz Combinatoria de Variantes --}}
                    <div x-show="variants.length > 0" class="space-y-3">
                        <div class="flex justify-between items-center">
                            <label class="input-label text-xs font-bold text-white/80">2. Configura precio y stock por combinación (<span x-text="variants.length"></span> variantes)</label>
                        </div>

                        <div class="overflow-x-auto max-h-72 overflow-y-auto rounded-xl border border-white/10">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-white/10 text-white/70 sticky top-0 backdrop-blur-md">
                                    <tr>
                                        <th class="p-2.5">Variante</th>
                                        <th class="p-2.5">SKU (opcional)</th>
                                        <th class="p-2.5">Precio (S/.)</th>
                                        <th class="p-2.5">Stock</th>
                                        <th class="p-2.5 text-center">Activo</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5 text-white">
                                    <template x-for="(v, vIdx) in variants" :key="vIdx">
                                        <tr class="hover:bg-white/5">
                                            <td class="p-2.5 font-bold text-tribio-cyan" x-text="v.title"></td>
                                            <td class="p-2.5">
                                                <input type="text" x-model="v.sku" placeholder="SKU" class="input-field text-xs py-1 px-2">
                                            </td>
                                            <td class="p-2.5">
                                                <input type="number" step="0.01" x-model="v.price" placeholder="Mismo precio" class="input-field text-xs py-1 px-2 w-24">
                                            </td>
                                            <td class="p-2.5">
                                                <input type="number" x-model="v.stock" class="input-field text-xs py-1 px-2 w-20" min="0">
                                            </td>
                                            <td class="p-2.5 text-center">
                                                <input type="checkbox" x-model="v.is_active" class="accent-tribio-purple w-4 h-4">
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Columna Derecha (1/3) --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- Estado y Visibilidad --}}
            <div class="glass-card p-6">
                <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">Estado y Visibilidad</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" class="accent-tribio-purple" {{ old('is_active', true) ? 'checked' : '' }}>
                        <span class="text-white/70 text-sm">Activo (visible en la tienda)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" class="accent-tribio-purple" {{ old('is_featured') ? 'checked' : '' }}>
                        <span class="text-white/70 text-sm">Destacado ⭐ (sección especial)</span>
                    </label>
                </div>
            </div>

            {{-- Organización --}}
            <div class="glass-card p-6" x-data="{
                search: '',
                selected: @js(old('categories', old('category_id') ? [(int)old('category_id')] : [])),
                primary: @js(old('category_id', null)),
                categories: @js($categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'icon' => $c->icon ?: '📁'])),
                get filteredCategories() {
                    if (!this.search.trim()) return this.categories;
                    const q = this.search.toLowerCase();
                    return this.categories.filter(c => c.name.toLowerCase().includes(q));
                },
                toggle(id) {
                    id = Number(id);
                    const idx = this.selected.indexOf(id);
                    if (idx > -1) {
                        this.selected.splice(idx, 1);
                        if (this.primary === id) {
                            this.primary = this.selected.length > 0 ? this.selected[0] : null;
                        }
                    } else {
                        this.selected.push(id);
                        if (!this.primary) {
                            this.primary = id;
                        }
                    }
                },
                isSelected(id) {
                    return this.selected.includes(Number(id));
                },
                setPrimary(id) {
                    id = Number(id);
                    if (!this.isSelected(id)) {
                        this.selected.push(id);
                    }
                    this.primary = id;
                },
                remove(id) {
                    id = Number(id);
                    this.selected = this.selected.filter(x => x !== id);
                    if (this.primary === id) {
                        this.primary = this.selected.length > 0 ? this.selected[0] : null;
                    }
                },
                clearAll() {
                    this.selected = [];
                    this.primary = null;
                }
            }">
                <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">Organización</h3>
                <div class="space-y-4">
                    {{-- Categorías Relacionadas --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="input-label mb-0">Categorías relacionadas</label>
                            <div class="flex items-center gap-2">
                                <template x-if="selected.length > 0">
                                    <button type="button" @click="clearAll" class="text-[11px] text-red-400 hover:underline">
                                        Limpiar
                                    </button>
                                </template>
                                <a href="{{ route('dashboard.categorias.create') }}" target="_blank" class="text-[11px] text-sky-400 hover:underline">
                                    + Nueva categoría
                                </a>
                            </div>
                        </div>
                        <p class="text-[11px] text-white/50 mb-2.5">Selecciona una o más categorías. Marca la estrella ⭐ para definir la principal.</p>

                        {{-- Chips de seleccionadas --}}
                        <template x-if="selected.length > 0">
                            <div class="flex flex-wrap gap-1.5 mb-3 p-2 rounded-xl bg-white/5 border border-white/10 max-h-28 overflow-y-auto">
                                <template x-for="catId in selected" :key="catId">
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-sky-500/20 text-sky-300 border border-sky-500/30">
                                        <span x-text="categories.find(c => c.id === catId)?.icon"></span>
                                        <span x-text="categories.find(c => c.id === catId)?.name"></span>
                                        <template x-if="primary === catId">
                                            <span class="text-[10px] px-1 py-0.2 rounded bg-amber-500/30 text-amber-300 border border-amber-500/40" title="Categoría Principal">
                                                ★ Principal
                                            </span>
                                        </template>
                                        <template x-if="primary !== catId">
                                            <button type="button" @click="setPrimary(catId)" class="text-white/40 hover:text-amber-300 text-[11px]" title="Marcar como Principal">
                                                ☆
                                            </button>
                                        </template>
                                        <button type="button" @click="remove(catId)" class="text-white/40 hover:text-red-400 text-xs font-bold leading-none ml-0.5">
                                            ×
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Input buscador de categorías --}}
                        <div class="relative mb-2">
                            <input type="text" x-model="search" placeholder="🔍 Buscar o filtrar categorías..." class="input-field text-xs py-1.5 pl-3 pr-8">
                            <template x-if="search.length > 0">
                                <button type="button" @click="search = ''" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-white/40 hover:text-white text-xs font-bold">✕</button>
                            </template>
                        </div>

                        {{-- Lista de categorías con checkboxes --}}
                        <div class="border border-white/10 rounded-xl p-2 max-h-48 overflow-y-auto space-y-1 bg-black/20">
                            <template x-if="filteredCategories.length === 0">
                                <p class="text-xs text-white/40 text-center py-3">No se encontraron categorías.</p>
                            </template>
                            <template x-for="cat in filteredCategories" :key="cat.id">
                                <div class="flex items-center justify-between p-1.5 rounded-lg hover:bg-white/5 transition-colors cursor-pointer"
                                     :class="{'bg-sky-500/10 border border-sky-500/20': isSelected(cat.id)}">
                                    <label class="flex items-center gap-2 flex-1 cursor-pointer select-none">
                                        <input type="checkbox" :value="cat.id" :checked="isSelected(cat.id)" @change="toggle(cat.id)" class="accent-sky-500 w-4 h-4 rounded">
                                        <span class="text-sm" x-text="cat.icon"></span>
                                        <span class="text-xs text-white" x-text="cat.name"></span>
                                    </label>
                                    <template x-if="isSelected(cat.id)">
                                        <button type="button" @click="setPrimary(cat.id)" 
                                                class="text-xs px-2 py-0.5 rounded transition-all"
                                                :class="primary === cat.id ? 'bg-amber-500/20 text-amber-300 font-bold' : 'text-white/30 hover:text-amber-300'">
                                            <span x-text="primary === cat.id ? '★ Principal' : '☆ Hacer principal'"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Inputs ocultos para envío de formulario --}}
                        <template x-for="catId in selected" :key="'input-' + catId">
                            <input type="hidden" name="categories[]" :value="catId">
                        </template>
                        <input type="hidden" name="category_id" :value="primary">
                    </div>

                    {{-- Marca --}}
                    <div>
                        <label class="input-label">Marca</label>
                        <select name="brand_id" class="input-field">
                            <option value="">Sin marca</option>
                            @foreach($brands as $brand)
                            <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>
                                🏷️ {{ $brand->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Imagen --}}
            <div class="glass-card p-6 space-y-6">
                <div>
                    <h3 class="text-white font-bold mb-3 text-sm uppercase tracking-wider opacity-60">Imagen Principal</h3>
                    <div>
                        <label class="input-label">Subir foto principal</label>
                        <input type="file" name="image" accept="image/*" class="input-field py-2">
                    </div>
                </div>

                <div class="pt-5 border-t border-white/10">
                    <h3 class="text-white font-bold mb-1 text-sm uppercase tracking-wider opacity-60">Fotos Secundarias (Galería)</h3>
                    <p class="text-xs text-slate-400 mb-3">Sube imágenes adicionales desde distintos ángulos para la galería.</p>
                    <div>
                        <label class="input-label">Agregar fotos secundarias</label>
                        <input type="file" name="gallery[]" multiple accept="image/*" class="input-field py-2">
                        <p class="text-[11px] text-slate-400 mt-1">Puedes seleccionar varias fotos a la vez (PNG, JPG, WEBP).</p>
                    </div>
                </div>
            </div>

            {{-- Video Corto del Producto (Máx 4 MB) --}}
            <div class="glass-card p-6 space-y-5" x-data="{
                hasVideo: false,
                videoPreview: null,
                showOnHome: false,
                replaceId: '',
                currentHomeSlots: {{ Js::from($homeVideoProducts->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'image' => $p->image_url,
                    'price' => $p->price
                ])) }},
                get isHomeSlotsFull() {
                    return this.currentHomeSlots.length >= 3;
                },
                handleVideoSelect(e) {
                    const file = e.target.files[0];
                    if (file) {
                        if (file.size > 4 * 1024 * 1024) {
                            alert('⚠️ El video supera el límite de 4 MB permitido. Por favor comprímelo o elige otro archivo más corto.');
                            e.target.value = '';
                            this.videoPreview = null;
                            this.hasVideo = false;
                            return;
                        }
                        this.videoPreview = URL.createObjectURL(file);
                        this.hasVideo = true;
                    } else {
                        this.videoPreview = null;
                        this.hasVideo = false;
                    }
                }
            }">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60 flex items-center gap-2">
                            <span>🎬 Video Corto del Producto</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Demostración en video vertical o cuadrado (máx. 4 MB).</p>
                    </div>
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        Máx 4 MB
                    </span>
                </div>

                {{-- Video Preview --}}
                <template x-if="videoPreview">
                    <div class="space-y-2">
                        <p class="input-label mb-1 text-xs text-emerald-400 font-bold flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Vista previa del video seleccionado:</span>
                        </p>
                        <div class="relative rounded-2xl overflow-hidden bg-black border border-white/10 aspect-[4/5] max-w-[200px] shadow-md">
                            <video :src="videoPreview" class="w-full h-full object-cover" autoplay loop muted playsinline></video>
                        </div>
                    </div>
                </template>

                {{-- Input para subir video --}}
                <div>
                    <label class="input-label">Subir video (MP4 o WebM)</label>
                    <input type="file" name="video" accept="video/mp4,video/webm" @change="handleVideoSelect($event)" class="input-field py-2 text-xs">
                    <p class="text-[11px] text-slate-400 mt-1">Formato recomendado: MP4 vertical (9:16 o 4:5), 5 a 15 segundos.</p>
                </div>

                {{-- Configuración de Destacado en el Home (Máximo 3 videos) --}}
                <div class="pt-4 border-t border-white/10 space-y-3" x-show="hasVideo || videoPreview" style="display: none;">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-white block">Mostrar en el Home (Portada)</span>
                            <span class="text-[11px] text-slate-400">Aparecerá en la sección de 3 videos después del Hero.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="show_video_on_home" value="1" x-model="showOnHome" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#C8A68B]"></div>
                        </label>
                    </div>

                    {{-- Reemplazo Intuitivo si los 3 cupos están ocupados --}}
                    <div x-show="showOnHome && isHomeSlotsFull" 
                         class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 space-y-3" style="display: none;">
                        <div class="flex items-center gap-2 text-amber-300 text-xs font-bold">
                            <span>⚠️</span>
                            <span>Los 3 cupos de video del Home ya están ocupados</span>
                        </div>
                        <p class="text-[11px] text-slate-300 leading-snug">
                            Para mostrar este producto, selecciona a cuál de los 3 productos actuales deseas reemplazar:
                        </p>

                        <div class="space-y-2">
                            <template x-for="slot in currentHomeSlots" :key="slot.id">
                                <label class="flex items-center justify-between p-2.5 rounded-xl border transition cursor-pointer"
                                       :class="replaceId == slot.id ? 'bg-amber-500/20 border-amber-400 text-white ring-1 ring-amber-400' : 'bg-black/30 border-white/10 hover:border-white/30 text-slate-300'">
                                    <div class="flex items-center gap-2.5">
                                        <input type="radio" name="replace_home_video_id" :value="slot.id" x-model="replaceId" class="accent-amber-400 w-4 h-4">
                                        <img :src="slot.image" class="w-10 h-10 rounded-lg object-cover bg-slate-800 border border-white/10">
                                        <div>
                                            <p class="text-xs font-bold line-clamp-1" x-text="slot.name"></p>
                                            <p class="text-[10px] text-slate-400" x-text="'S/. ' + slot.price"></p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md"
                                          :class="replaceId == slot.id ? 'bg-amber-400 text-stone-950' : 'bg-white/10 text-slate-400'">
                                        Reemplazar
                                    </span>
                                </label>
                            </template>
                        </div>
                        <p class="text-[10px] text-amber-300/80">
                            * El producto que elijas dejará de mostrarse en el Home y este nuevo tomará su lugar.
                        </p>
                    </div>

                    {{-- Indicador de cupo disponible --}}
                    <template x-if="showOnHome && !isHomeSlotsFull">
                        <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-[11px] text-emerald-300 flex items-center gap-2">
                            <span>✓</span>
                            <span>Cupo disponible en el Home (<span x-text="currentHomeSlots.length"></span> de 3 ocupados).</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
        @foreach($errors->all() as $e) <p>• {{ $e }}</p> @endforeach
    </div>
    @endif

    <div class="flex gap-3 pt-2">
        <button type="submit" class="btn-primary btn-primary-lg">Guardar producto</button>
        <a href="{{ route('dashboard.productos.index') }}" class="btn-ghost">Cancelar</a>
    </div>
</form>
@endsection
