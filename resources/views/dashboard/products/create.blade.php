@extends('layouts.dashboard')
@section('title', 'Crear Producto')
@section('page_title', '+ Crear Producto')
@section('content')
@php
    $exchangeRates = app(\App\Services\ExchangeRateService::class)->getRates();
    $enabledCountries = $store->getEnabledCountriesWithDetails();
    $currenciesMap = [];
    foreach ($enabledCountries as $cCode => $cInfo) {
        $curr = $cInfo['currency'];
        if ($curr === 'PEN') continue;
        if (!isset($currenciesMap[$curr])) {
            $currenciesMap[$curr] = [
                'currency' => $curr, 'symbol' => $cInfo['symbol'], 'decimals' => $cInfo['decimals'],
                'countries' => [$cInfo['name']], 'flags' => [$cInfo['flag']], 'codes' => [$cCode],
            ];
        } else {
            $currenciesMap[$curr]['countries'][] = $cInfo['name'];
            $currenciesMap[$curr]['flags'][] = $cInfo['flag'];
            $currenciesMap[$curr]['codes'][] = $cCode;
        }
    }
    $hasManualPricing = false;
    foreach ($currenciesMap as $cur => $meta) {
        if (!empty(old('currency_prices.' . $cur))) { $hasManualPricing = true; break; }
    }
@endphp
<div class="w-full max-w-6xl mx-auto space-y-5 sm:space-y-6">
    <form id="product-form" method="POST" action="{{ route('dashboard.productos.store') }}" enctype="multipart/form-data" class="space-y-5 sm:space-y-6" x-data="{ productName: '{{ old('name') }}' }">
        @csrf

        {{-- Barra de acción: pegada arriba, no tapa el contenido al hacer scroll --}}
        <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
            <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
                <a href="{{ route('dashboard.productos.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a productos" title="Volver a productos">←</a>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Nuevo producto</p>
                    <p class="text-white font-bold text-sm truncate" x-text="productName || 'Aún sin nombre'"></p>
                </div>
                <button type="submit" class="btn-primary flex-shrink-0 !px-4 sm:!px-6 text-xs sm:text-sm">
                    <span class="hidden sm:inline">💾 Guardar producto</span><span class="sm:hidden">💾 Guardar</span>
                </button>
            </div>
        </div>

        @if($errors->any())
        <div class="glass-card p-4 sm:p-5 border border-red-400/20 bg-red-500/5">
            <p class="text-red-400 font-bold text-xs sm:text-sm mb-1.5 flex items-center gap-2">⚠️ Revisa estos campos antes de guardar:</p>
            <ul class="text-xs text-red-400/90 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- PASO 1: Fotos — lo primero, para todos los tamaños de pantalla --}}
        <div id="product-images" class="glass-card p-5 sm:p-6" tabindex="-1">
            <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60 mb-1 flex items-center gap-2">📸 Fotos del producto</h3>
            <p class="text-xs text-white/50 mb-5">Empieza por aquí: una buena foto principal es lo primero que ven tus clientes.</p>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 lg:gap-6">
                <x-image-picker name="image" label="Imagen principal" save-label="Guardar producto" />
                <div>
                    <x-image-picker name="gallery[]" label="Fotos secundarias (galería)" :multiple="true" save-label="Guardar producto" />
                    <p class="text-[11px] text-white/40 mt-1">Puedes seleccionar varias a la vez (PNG, JPG, WEBP).</p>
                </div>
            </div>
        </div>

        {{-- PASO 2: Video (opcional, colapsado) --}}
        <div class="glass-card p-5 sm:p-6" x-data="{
            videoOpen: false, hasVideo: false, videoPreview: null, videoError: null, videoSizeText: null,
            showOnHome: false, replaceId: '',
            currentHomeSlots: {{ Js::from($homeVideoProducts->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'image' => $p->image_url, 'price' => $p->price])) }},
            get isHomeSlotsFull() { return this.currentHomeSlots.length >= 3; },
            handleVideoSelect(e) {
                this.videoError = null;
                const file = e.target.files ? e.target.files[0] : null;
                if (file) {
                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    if (file.size > 4 * 1024 * 1024) {
                        this.videoError = `El video pesa ${sizeMB} MB y supera el límite de 4 MB. Comprímelo o elige uno más corto.`;
                        e.target.value = ''; this.videoPreview = null; this.videoSizeText = null; this.hasVideo = false;
                        return;
                    }
                    this.videoSizeText = `${sizeMB} MB`; this.videoPreview = URL.createObjectURL(file); this.hasVideo = true;
                } else { this.videoPreview = null; this.videoSizeText = null; this.hasVideo = false; }
            },
            clearVideoSelection(inputRef) {
                this.videoError = null; this.videoPreview = null; this.videoSizeText = null; this.hasVideo = false; this.showOnHome = false;
                if (inputRef) inputRef.value = '';
            }
        }">
            <button type="button" @click="videoOpen = !videoOpen" class="w-full flex items-center justify-between gap-2 text-left">
                <div>
                    <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60 flex items-center gap-2">🎬 Video corto <span class="badge badge-gray">Opcional</span></h3>
                    <p class="text-xs text-white/50 mt-0.5">Muestra tu producto en movimiento (máx. 4 MB).</p>
                </div>
                <span class="text-white/40 text-xl flex-shrink-0 leading-none" x-text="videoOpen ? '−' : '+'"></span>
            </button>

            <div x-show="videoOpen" x-cloak class="mt-5 pt-4 border-t border-white/10 space-y-4">
                <template x-if="videoError">
                    <div class="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-xs text-red-300 flex items-start gap-2">
                        <span class="text-base leading-none">⚠️</span><span x-text="videoError"></span>
                    </div>
                </template>

                <template x-if="videoPreview">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between flex-wrap gap-1.5">
                            <p class="input-label mb-0 text-xs text-tribio-cyan font-bold flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-tribio-cyan animate-pulse"></span><span>Vista previa del video:</span>
                            </p>
                            <span class="badge badge-green" x-text="'Tamaño: ' + videoSizeText + ' / 4 MB ✓'"></span>
                        </div>
                        <div class="relative rounded-2xl overflow-hidden bg-black border border-white/10 aspect-[4/5] max-w-[180px] group">
                            <video :src="videoPreview" class="w-full h-full object-cover" autoplay loop muted playsinline></video>
                            <button type="button" @click="clearVideoSelection($refs.videoInput)" class="absolute top-2 right-2 px-2 py-1 rounded-lg bg-red-600/90 hover:bg-red-600 text-white text-xs font-bold shadow-md transition">✕ Quitar</button>
                        </div>
                    </div>
                </template>

                <div>
                    <label class="input-label">Subir video (MP4, WebM o MOV)</label>
                    <input type="file" x-ref="videoInput" name="video" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" @change="handleVideoSelect($event)" class="input-field py-2 text-xs">
                    <p class="text-[11px] text-white/40 mt-1">MP4, WebM o MOV. Máximo 4 MB (5 a 15 segundos recomendado).</p>
                    @error('video')<p class="text-xs text-red-400 font-bold mt-2 flex items-center gap-1"><span>⚠️</span> {{ $message }}</p>@enderror
                </div>

                <div class="pt-4 border-t border-white/10 space-y-3" x-show="hasVideo || videoPreview" x-cloak>
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <span class="text-xs font-bold text-white block">Mostrar en el Home (portada)</span>
                            <span class="text-[11px] text-white/50">Aparecerá en la sección de 3 videos después del Hero.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                            <input type="checkbox" name="show_video_on_home" value="1" x-model="showOnHome" class="sr-only peer">
                            <div class="w-10 h-6 bg-white/10 rounded-full peer peer-checked:bg-tribio-cyan transition-colors"></div>
                            <div class="absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform peer-checked:translate-x-4"></div>
                        </label>
                    </div>

                    <div x-show="showOnHome && isHomeSlotsFull" x-cloak class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-3">
                        <div class="flex items-center gap-2 text-white text-xs font-bold"><span>⚠️</span><span>Los 3 cupos de video del Home ya están ocupados</span></div>
                        <p class="text-xs text-white/60 leading-snug">Selecciona a cuál de los 3 productos actuales deseas reemplazar:</p>
                        <div class="space-y-2">
                            <template x-for="slot in currentHomeSlots" :key="slot.id">
                                <label class="flex items-center justify-between p-2.5 rounded-xl border transition cursor-pointer select-none"
                                       :class="replaceId == slot.id ? 'bg-tribio-cyan/10 border-tribio-cyan/40' : 'bg-white/3 border-white/10 hover:border-white/20'">
                                    <div class="flex items-center gap-2.5">
                                        <input type="radio" name="replace_home_video_id" :value="slot.id" x-model="replaceId" class="accent-tribio-cyan w-4 h-4">
                                        <img :src="slot.image" class="w-9 h-9 rounded-lg object-cover bg-white/10 border border-white/10">
                                        <div><p class="text-xs font-bold line-clamp-1 text-white" x-text="slot.name"></p><p class="text-[11px] font-semibold text-white/50" x-text="'S/. ' + slot.price"></p></div>
                                    </div>
                                    <span class="badge" :class="replaceId == slot.id ? 'badge-blue' : 'badge-gray'">Reemplazar</span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <template x-if="showOnHome && !isHomeSlotsFull">
                        <div class="p-3 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-white/70 flex items-center gap-2">
                            <span class="text-tribio-cyan font-bold">✓</span><span>Cupo disponible en el Home (<span x-text="currentHomeSlots.length"></span> de 3 ocupados).</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 sm:gap-6">
            {{-- Columna principal --}}
            <div class="lg:col-span-2 space-y-5 sm:space-y-6">
                {{-- Información General --}}
                <div class="glass-card p-5 sm:p-6">
                    <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">📝 Información General</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="input-label">Nombre del producto *</label>
                            <input type="text" name="name" x-model="productName" class="input-field" required placeholder="Ej: Zapatillas Nike Air">
                        </div>
                        <div>
                            <label class="input-label">Descripción corta</label>
                            <input type="text" name="short_description" class="input-field" value="{{ old('short_description') }}" placeholder="Resumen rápido (máx 200 caracteres)" maxlength="200">
                        </div>
                        <div>
                            <label class="input-label">Descripción completa</label>
                            <textarea name="description" class="input-field" rows="5" placeholder="Describe los detalles de tu producto...">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Precio --}}
                <div class="glass-card p-5 sm:p-6" x-data="{
                    penPrice: '{{ old('price', '') }}',
                    comparePenPrice: '{{ old('compare_price', '') }}',
                    rates: {{ json_encode($exchangeRates) }},
                    currencies: {
                        @foreach($currenciesMap as $cur => $meta)
                            '{{ $cur }}': { manual: {{ old('currency_prices.' . $cur) ? 'true' : 'false' }}, price: '{{ old('currency_prices.' . $cur, '') }}', compare: '{{ old('compare_currency_prices.' . $cur, '') }}' },
                        @endforeach
                    },
                    showCustomPricing: {{ $hasManualPricing ? 'true' : 'false' }},
                    calcRaw(amount, cur) {
                        const val = parseFloat(amount); if (isNaN(val) || val <= 0) return '';
                        const res = val * (this.rates[cur] || 1);
                        return res.toFixed((cur === 'COP' || cur === 'CLP' || cur === 'ARS') ? 0 : 2);
                    },
                    calcRate(amount, cur) {
                        const raw = this.calcRaw(amount, cur); if (!raw) return '0.00';
                        return parseFloat(raw).toLocaleString('en-US', { minimumFractionDigits: (cur === 'COP' || cur === 'CLP' || cur === 'ARS') ? 0 : 2, maximumFractionDigits: (cur === 'COP' || cur === 'CLP' || cur === 'ARS') ? 0 : 2 });
                    },
                    syncUsd() {
                        const usdEl = document.getElementById('field_price_usd');
                        if (usdEl && this.currencies['USD']) usdEl.value = this.currencies['USD'].manual ? this.currencies['USD'].price : this.calcRaw(this.penPrice, 'USD');
                        const usdCompEl = document.getElementById('field_compare_price_usd');
                        if (usdCompEl && this.currencies['USD']) usdCompEl.value = this.currencies['USD'].manual ? this.currencies['USD'].compare : this.calcRaw(this.comparePenPrice, 'USD');
                    }
                }" x-init="$watch('penPrice', () => syncUsd()); $watch('comparePenPrice', () => syncUsd()); syncUsd();">
                    <h3 class="text-white font-bold mb-1 text-sm uppercase tracking-wider opacity-60 flex items-center gap-2">💰 Precio</h3>
                    <p class="text-xs text-white/50 mb-4">El precio de venta principal, en soles (S/.).</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="input-label">Precio de venta (S/.) *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none"><span class="text-xs text-tribio-cyan font-bold select-none">S/</span></div>
                                <input type="number" name="price" x-model="penPrice" class="input-field text-right font-bold" style="padding-left: 2.75rem !important;" step="0.01" min="0" required placeholder="0.00">
                            </div>
                        </div>
                        <div>
                            <label class="input-label">Precio anterior / tachado (opcional)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none"><span class="text-xs text-white/40 font-bold select-none">S/</span></div>
                                <input type="number" name="compare_price" x-model="comparePenPrice" class="input-field text-right" style="padding-left: 2.75rem !important;" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    @if(!empty($currenciesMap))
                    <div class="border-t border-white/10 pt-4 mt-4">
                        <template x-if="!showCustomPricing">
                            <div>
                                <p class="text-xs text-white/50 mb-2.5">Se calcula automáticamente en las demás monedas de tu tienda:</p>
                                <div class="flex flex-wrap gap-2 mb-3">
                                    @foreach($currenciesMap as $cur => $meta)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs">
                                        <span>{{ $meta['flags'][0] ?? '' }}</span><span class="text-white/50 font-mono">{{ $cur }}</span>
                                        <span class="text-white font-bold" x-text="'{{ $meta['symbol'] }} ' + calcRate(penPrice, '{{ $cur }}')"></span>
                                    </span>
                                    @endforeach
                                </div>
                                <button type="button" @click="showCustomPricing = true" class="btn-ghost text-xs !py-1.5 !px-3">✍️ Personalizar precio por país</button>
                            </div>
                        </template>

                        <div x-show="showCustomPricing" x-cloak class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-bold text-white/70 uppercase tracking-wider">Precios por país</p>
                                <button type="button" @click="showCustomPricing = false" class="text-[11px] text-white/40 hover:text-white/70">Ocultar</button>
                            </div>
                            @foreach($currenciesMap as $cur => $meta)
                            <div class="p-3.5 rounded-xl bg-white/3 border border-white/10">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm">{{ $meta['flags'][0] ?? '' }}</span>
                                        <span class="text-xs font-bold text-white font-mono">{{ $cur }}</span>
                                        <span class="text-[11px] text-white/40">{{ implode(', ', $meta['countries']) }}</span>
                                        <span x-show="currencies['{{ $cur }}'].manual" x-cloak class="badge badge-gold">✍️ Manual</span>
                                    </div>
                                    <div class="inline-flex rounded-lg p-0.5 bg-white/5 border border-white/10 text-[11px] font-semibold flex-shrink-0">
                                        <button type="button" @click="currencies['{{ $cur }}'].manual = false; currencies['{{ $cur }}'].price = ''; syncUsd();" :class="!currencies['{{ $cur }}'].manual ? 'bg-tribio-cyan/20 text-tribio-cyan' : 'text-white/40 hover:text-white/70'" class="px-2.5 py-1 rounded-md transition">Auto</button>
                                        <button type="button" @click="currencies['{{ $cur }}'].manual = true; if(!currencies['{{ $cur }}'].price && penPrice) currencies['{{ $cur }}'].price = calcRaw(penPrice, '{{ $cur }}'); syncUsd();" :class="currencies['{{ $cur }}'].manual ? 'bg-tribio-cyan/20 text-tribio-cyan' : 'text-white/40 hover:text-white/70'" class="px-2.5 py-1 rounded-md transition">Manual</button>
                                    </div>
                                </div>
                                <p class="text-xs text-white/50" x-show="!currencies['{{ $cur }}'].manual">T.C. sugerido: <span class="text-white font-mono font-bold" x-text="'{{ $meta['symbol'] }} ' + calcRate(penPrice, '{{ $cur }}')"></span></p>
                                <div x-show="currencies['{{ $cur }}'].manual" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
                                    <div>
                                        <label class="input-label text-[11px]">Precio en {{ $cur }}</label>
                                        <input type="number" step="{{ $meta['decimals'] === 0 ? '1' : '0.01' }}" min="0" name="currency_prices[{{ $cur }}]" x-model="currencies['{{ $cur }}'].price" @input="syncUsd()" class="input-field text-xs py-1.5" placeholder="0.00">
                                    </div>
                                    <div>
                                        <label class="input-label text-[11px]">Precio anterior (opcional)</label>
                                        <input type="number" step="{{ $meta['decimals'] === 0 ? '1' : '0.01' }}" min="0" name="compare_currency_prices[{{ $cur }}]" x-model="currencies['{{ $cur }}'].compare" @input="syncUsd()" class="input-field text-xs py-1.5" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <input type="hidden" name="price_usd" id="field_price_usd" value="{{ old('price_usd') }}">
                    <input type="hidden" name="compare_price_usd" id="field_compare_price_usd" value="{{ old('compare_price_usd') }}">

                    <div class="border-t border-white/10 pt-4 mt-4">
                        <label class="input-label">Código del producto origen (opcional)</label>
                        <input type="text" name="origin_code" class="input-field" value="{{ old('origin_code') }}" placeholder="Ej: COD-ORI-99">
                    </div>
                </div>

                {{-- Inventario --}}
                <div class="glass-card p-5 sm:p-6">
                    <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">📦 Inventario y Stock</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="input-label">SKU / Código</label>
                            <input type="text" name="sku" class="input-field" value="{{ old('sku') }}" placeholder="Dejar vacío para auto-generar">
                        </div>
                        <div>
                            <label class="input-label">Stock inicial</label>
                            <input type="number" name="stock" class="input-field" value="{{ old('stock', 0) }}" min="0">
                        </div>
                    </div>
                    <label class="flex items-center gap-3 mb-4 cursor-pointer">
                        <input type="checkbox" name="track_stock" value="1" class="w-4 h-4 accent-tribio-cyan" {{ old('track_stock') ? 'checked' : '' }}>
                        <span class="text-white/70 text-sm select-none">Controlar inventario (descontar stock en cada venta)</span>
                    </label>
                    <div>
                        <label class="input-label">Mensaje cuando el stock llega a 0 (opcional)</label>
                        <input type="text" name="out_of_stock_message" class="input-field" value="{{ old('out_of_stock_message') }}" placeholder="Ej: Pre-venta: Disponible en 15 días">
                        <p class="text-xs text-white/50 mt-1">Si lo llenas, el producto permitirá compras sin stock mostrando este mensaje en vez de "Agotado".</p>
                    </div>
                </div>

                {{-- Variantes --}}
                <div class="glass-card p-5 sm:p-6" x-data="{
                    hasVariants: false,
                    options: [{ name: 'Color', valuesText: 'Negro, Blanco', values: ['Negro', 'Blanco'] }, { name: 'Talla', valuesText: 'S, M, L', values: ['S', 'M', 'L'] }],
                    variants: [],
                    addOption() { this.options.push({ name: '', valuesText: '', values: [] }); },
                    removeOption(index) { this.options.splice(index, 1); this.generateVariants(); },
                    updateOptionValues(opt) { opt.values = opt.valuesText.split(',').map(s => s.trim()).filter(s => s.length > 0); this.generateVariants(); },
                    generateVariants() {
                        const validOptions = this.options.filter(o => o.name.trim() && o.values.length > 0);
                        if (validOptions.length === 0) { this.variants = []; return; }
                        const cartesian = (arrays) => arrays.reduce((acc, curr) => acc.flatMap(a => curr.map(c => [...a, c])), [[]]);
                        const combinations = cartesian(validOptions.map(o => o.values));
                        const newVariants = [];
                        combinations.forEach((combo) => {
                            const attributes = {}; validOptions.forEach((o, i) => { attributes[o.name] = combo[i]; });
                            const title = combo.join(' / ');
                            const existing = this.variants.find(v => v.title === title);
                            newVariants.push({
                                id: existing ? existing.id : null, title: title, attributes: attributes,
                                sku: existing && existing.sku ? existing.sku : '',
                                price: existing && existing.price !== undefined ? existing.price : '',
                                stock: existing && existing.stock !== undefined ? existing.stock : 10,
                                is_active: existing ? existing.is_active : true
                            });
                        });
                        this.variants = newVariants;
                    },
                    init() { this.generateVariants(); }
                }">
                    <div class="flex items-center justify-between gap-2 mb-4 flex-wrap">
                        <div>
                            <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60">🎨 Variantes <span class="badge badge-gray">Opcional</span></h3>
                            <p class="text-xs text-white/50 mt-0.5">Permite elegir color, talla, tamaño, etc.</p>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer bg-white/5 px-3 py-1.5 rounded-lg border border-white/10 hover:border-tribio-cyan transition">
                            <input type="checkbox" name="has_variants" value="1" x-model="hasVariants" class="w-4 h-4 accent-tribio-cyan rounded">
                            <span class="text-xs font-bold text-tribio-cyan">Habilitar variantes</span>
                        </label>
                    </div>

                    <div x-show="hasVariants" x-cloak class="space-y-5 pt-3 border-t border-white/10">
                        <input type="hidden" name="variant_options_json" :value="JSON.stringify(options.filter(o => o.name.trim() && o.values.length > 0))">
                        <input type="hidden" name="variants_json" :value="JSON.stringify(variants)">

                        <div class="space-y-3">
                            <label class="input-label text-xs font-bold text-white/70">1. Define las opciones (ej: Color, Talla)</label>
                            <template x-for="(opt, idx) in options" :key="idx">
                                <div class="flex gap-2 items-center bg-white/5 p-3 rounded-xl border border-white/10">
                                    <div class="w-1/3"><input type="text" x-model="opt.name" @input="generateVariants()" placeholder="Nombre" class="input-field text-xs py-1.5"></div>
                                    <div class="flex-1"><input type="text" x-model="opt.valuesText" @input="updateOptionValues(opt)" placeholder="Valores separados por comas" class="input-field text-xs py-1.5"></div>
                                    <button type="button" @click="removeOption(idx)" class="text-red-400 hover:text-red-300 px-2 py-1 text-sm font-bold flex-shrink-0">✕</button>
                                </div>
                            </template>
                            <button type="button" @click="addOption()" class="btn-ghost text-xs !py-1.5 !px-3">+ Agregar otra opción</button>
                        </div>

                        <div x-show="variants.length > 0" class="space-y-3">
                            <label class="input-label text-xs font-bold text-white/70">2. Precio y stock por combinación (<span x-text="variants.length"></span> variantes)</label>

                            {{-- Tabla (pantallas medianas y grandes) --}}
                            <div class="hidden sm:block overflow-x-auto max-h-72 overflow-y-auto rounded-xl border border-white/10">
                                <table class="w-full text-xs text-left">
                                    <thead class="bg-white/5 text-white/60 sticky top-0 backdrop-blur-md">
                                        <tr><th class="p-2.5">Variante</th><th class="p-2.5">SKU (opcional)</th><th class="p-2.5">Precio (S/.)</th><th class="p-2.5">Stock</th><th class="p-2.5 text-center">Activo</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5 text-white">
                                        <template x-for="(v, vIdx) in variants" :key="vIdx">
                                            <tr class="hover:bg-white/5">
                                                <td class="p-2.5 font-bold text-tribio-cyan" x-text="v.title"></td>
                                                <td class="p-2.5"><input type="text" x-model="v.sku" placeholder="SKU" class="input-field text-xs py-1 px-2"></td>
                                                <td class="p-2.5"><input type="number" step="0.01" x-model="v.price" placeholder="Mismo precio" class="input-field text-xs py-1 px-2 w-24"></td>
                                                <td class="p-2.5"><input type="number" x-model="v.stock" class="input-field text-xs py-1 px-2 w-20" min="0"></td>
                                                <td class="p-2.5 text-center"><input type="checkbox" x-model="v.is_active" class="accent-tribio-cyan w-4 h-4"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Tarjetas (móvil) --}}
                            <div class="sm:hidden space-y-2 max-h-96 overflow-y-auto pr-0.5">
                                <template x-for="(v, vIdx) in variants" :key="'m' + vIdx">
                                    <div class="p-3 rounded-xl bg-white/5 border border-white/10 space-y-2.5">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-tribio-cyan font-bold text-xs" x-text="v.title"></span>
                                            <label class="flex items-center gap-1.5 text-[11px] text-white/60 flex-shrink-0"><input type="checkbox" x-model="v.is_active" class="accent-tribio-cyan w-3.5 h-3.5"> Activo</label>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2.5">
                                            <div><label class="text-[10px] text-white/40 block mb-1">SKU</label><input type="text" x-model="v.sku" placeholder="SKU" class="input-field text-xs py-1.5 px-2"></div>
                                            <div><label class="text-[10px] text-white/40 block mb-1">Precio (S/.)</label><input type="number" step="0.01" x-model="v.price" placeholder="Igual al general" class="input-field text-xs py-1.5 px-2"></div>
                                            <div class="col-span-2"><label class="text-[10px] text-white/40 block mb-1">Stock</label><input type="number" x-model="v.stock" class="input-field text-xs py-1.5 px-2" min="0"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Columna lateral --}}
            <div class="lg:col-span-1 space-y-5 sm:space-y-6">
                {{-- Estado y Visibilidad --}}
                <div class="glass-card p-5 sm:p-6">
                    <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">👁️ Estado y Visibilidad</h3>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" class="w-4 h-4 accent-tribio-cyan" {{ old('is_active', true) ? 'checked' : '' }}>
                            <span class="text-white/70 text-sm select-none">Activo (visible en la tienda)</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_featured" value="1" class="w-4 h-4 accent-tribio-cyan" {{ old('is_featured') ? 'checked' : '' }}>
                            <span class="text-white/70 text-sm select-none">Destacado ⭐ (sección especial)</span>
                        </label>
                    </div>
                </div>

                {{-- Organización --}}
                <div class="glass-card p-5 sm:p-6" x-data="{
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
                        if (idx > -1) { this.selected.splice(idx, 1); if (this.primary === id) this.primary = this.selected.length > 0 ? this.selected[0] : null; }
                        else { this.selected.push(id); if (!this.primary) this.primary = id; }
                    },
                    isSelected(id) { return this.selected.includes(Number(id)); },
                    setPrimary(id) { id = Number(id); if (!this.isSelected(id)) this.selected.push(id); this.primary = id; },
                    remove(id) { id = Number(id); this.selected = this.selected.filter(x => x !== id); if (this.primary === id) this.primary = this.selected.length > 0 ? this.selected[0] : null; },
                    clearAll() { this.selected = []; this.primary = null; }
                }">
                    <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60 mb-4">🏷️ Organización</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-1.5 flex-wrap gap-1">
                                <label class="input-label mb-0 text-xs">Categorías relacionadas</label>
                                <div class="flex items-center gap-2">
                                    <template x-if="selected.length > 0"><button type="button" @click="clearAll" class="text-xs font-semibold text-red-400 hover:text-red-300">Limpiar</button></template>
                                    <a href="{{ route('dashboard.categorias.create') }}" target="_blank" class="text-xs font-semibold text-tribio-cyan hover:underline">+ Nueva</a>
                                </div>
                            </div>
                            <p class="text-xs text-white/50 mb-3">Marca la estrella ⭐ para definir la principal.</p>

                            <template x-if="selected.length > 0">
                                <div class="flex flex-wrap gap-2 mb-3 p-2.5 rounded-xl bg-white/3 border border-white/10 max-h-32 overflow-y-auto">
                                    <template x-for="catId in selected" :key="catId">
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-bold bg-white/5 border border-white/10 text-white">
                                            <span class="text-sm" x-text="categories.find(c => c.id === catId)?.icon || '🏷️'"></span>
                                            <span x-text="categories.find(c => c.id === catId)?.name"></span>
                                            <template x-if="primary === catId"><span class="badge badge-gold !py-0 !px-1.5" title="Categoría principal">★</span></template>
                                            <template x-if="primary !== catId"><button type="button" @click="setPrimary(catId)" class="text-white/30 hover:text-tribio-gold text-xs font-bold" title="Marcar como principal">☆</button></template>
                                            <button type="button" @click="remove(catId)" class="text-white/30 hover:text-red-400 text-xs font-bold ml-0.5" title="Quitar">×</button>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <div class="relative mb-2.5">
                                <input type="text" x-model="search" placeholder="🔍 Buscar categorías..." class="input-field text-xs py-2 w-full">
                                <template x-if="search.length > 0"><button type="button" @click="search = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white text-xs font-bold">✕</button></template>
                            </div>

                            <div class="border border-white/10 rounded-xl p-2 max-h-52 overflow-y-auto space-y-1 bg-white/3 divide-y divide-white/5">
                                <template x-if="filteredCategories.length === 0"><p class="text-xs text-white/40 text-center py-4">No se encontraron categorías.</p></template>
                                <template x-for="cat in filteredCategories" :key="cat.id">
                                    <div class="flex items-center justify-between p-2 rounded-lg transition-all cursor-pointer select-none" :class="isSelected(cat.id) ? 'bg-tribio-cyan/10 border border-tribio-cyan/30' : 'hover:bg-white/5 border border-transparent'">
                                        <label class="flex items-center gap-2.5 flex-1 cursor-pointer py-0.5">
                                            <input type="checkbox" :value="cat.id" :checked="isSelected(cat.id)" @change="toggle(cat.id)" class="accent-tribio-cyan w-4 h-4">
                                            <span class="text-base" x-text="cat.icon || '🏷️'"></span>
                                            <span class="text-xs" :class="isSelected(cat.id) ? 'font-bold text-white' : 'text-white/70'" x-text="cat.name"></span>
                                        </label>
                                        <template x-if="isSelected(cat.id)">
                                            <button type="button" @click="setPrimary(cat.id)" class="badge !py-1 !px-2" :class="primary === cat.id ? 'badge-gold' : 'badge-gray'">
                                                <span x-text="primary === cat.id ? '★ Principal' : '☆ Principal'"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <template x-for="catId in selected" :key="'input-' + catId"><input type="hidden" name="categories[]" :value="catId"></template>
                            <input type="hidden" name="category_id" :value="primary">
                        </div>

                        <div>
                            <label class="input-label">Marca</label>
                            <select name="brand_id" class="input-field">
                                <option value="">Sin marca</option>
                                @foreach($brands as $brand)
                                <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>🏷️ {{ $brand->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
