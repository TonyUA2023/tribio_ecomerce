@extends('layouts.dashboard')
@section('title','Mi Tienda') @section('page_title','⚙️ Configurar Mi Tienda')
@section('content')
@php
    $supported = \App\Helpers\CurrencyHelper::supportedCountries();
    $hasAdvancedData = old('build_mode', $store?->build_mode) === 'custom_code'
        || old('custom_domain', $store?->custom_domain)
        || old('is_multilanguage_enabled', $store?->is_multilanguage_enabled)
        || old('hero_title', $store?->hero_title)
        || old('hero_badge', $store?->hero_badge)
        || old('hero_subtitle', $store?->hero_subtitle)
        || !empty(old('distributors', $store->distributors ?? []));
@endphp
<div class="w-full max-w-5xl mx-auto space-y-5 sm:space-y-6">
    <form id="store-settings-form" method="POST" action="{{ route('dashboard.store.update') }}" class="space-y-5 sm:space-y-6" x-data="{
        storeName: '{{ old('name', $store?->name) }}',
        advancedOpen: {{ $hasAdvancedData ? 'true' : 'false' }},
        enabledCountries: {{ json_encode(old('enabled_countries', $store?->getEnabledCountriesList() ?? ['PE', 'US'])) }},
        isCountryEnabled(code) { return this.enabledCountries.includes(code); },
        toggleCountry(code) {
            if (code === 'PE') return; // Perú siempre activo como base
            const idx = this.enabledCountries.indexOf(code);
            if (idx > -1) { this.enabledCountries.splice(idx, 1); } else { this.enabledCountries.push(code); }
        },
        expressEnabled: {{ old('is_express_shipping_enabled', $store?->is_express_shipping_enabled) ? 'true' : 'false' }},
        freeShippingEnabled: {{ old('free_shipping_min_quantity', $store?->free_shipping_min_quantity) || old('free_shipping_min_amount', $store?->free_shipping_min_amount) ? 'true' : 'false' }},
        bulkDiscountEnabled: {{ old('bulk_discount_min_quantity', $store?->bulk_discount_min_quantity) ? 'true' : 'false' }},
        langEnabled: {{ old('is_multilanguage_enabled', $store?->is_multilanguage_enabled) ? 'true' : 'false' }},
        regions: {{ json_encode(old('distributors', $store->distributors ?? [])) }} || [],
        addRegion() { this.regions.push({ region: '', locations: [''] }); },
        removeRegion(index) { this.regions.splice(index, 1); },
        addLocation(regionIndex) { this.regions[regionIndex].locations.push(''); },
        removeLocation(regionIndex, locIndex) {
            this.regions[regionIndex].locations.splice(locIndex, 1);
            if (this.regions[regionIndex].locations.length === 0) { this.regions[regionIndex].locations.push(''); }
        }
    }">
        @csrf

        {{-- Barra de acción: pegada arriba, no tapa el contenido al hacer scroll --}}
        <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
            <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
                <a href="{{ route('dashboard.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver al panel" title="Volver al panel">←</a>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Mi Tienda</p>
                    <p class="text-white font-bold text-sm truncate" x-text="storeName || 'Configurar tienda'"></p>
                </div>
                <button type="submit" class="btn-primary flex-shrink-0 !px-4 sm:!px-6 text-xs sm:text-sm">
                    <span class="hidden sm:inline">💾 Guardar Todos los Cambios</span><span class="sm:hidden">💾 Guardar</span>
                </button>
            </div>
        </div>

        @if($errors->any())
        <div class="glass-card p-4 sm:p-5 border border-red-400/20 bg-red-500/5">
            <p class="text-red-400 font-bold text-xs sm:text-sm mb-1.5 flex items-center gap-2">⚠️ Revisa estos campos antes de guardar:</p>
            <ul class="text-xs text-red-400/90 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $error_msg)
                    <li>{{ $error_msg }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Información Básica --}}
        <div class="glass-card p-5 sm:p-8 space-y-5">
            <div>
                <h3 class="text-white font-bold text-lg mb-1 flex items-center gap-2">🏪 Información Básica</h3>
                <p class="text-xs text-white/50">Los detalles principales de tu negocio, los que verán tus clientes primero.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="input-label">Nombre del negocio *</label>
                    <input type="text" name="name" x-model="storeName" class="input-field">
                </div>
                <div>
                    <label class="input-label">Enlace de la tienda (Slug)</label>
                    <div class="flex items-center">
                        <span class="px-3 py-2 bg-black/20 border border-white/5 border-r-0 rounded-l-lg text-xs text-white/50">tienda/</span>
                        <input type="text" name="slug" class="input-field rounded-l-none" value="{{ old('slug', $store?->slug) }}" placeholder="mi-tienda">
                    </div>
                </div>
            </div>
            @if($store)
                <p class="text-[11px] text-white/50 flex items-center gap-1">
                    🔗 Enlace público: <a href="{{ $store->url }}" target="_blank" class="text-tribio-cyan hover:underline">{{ $store->url }}</a>
                </p>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="input-label">Categoría / Rubro *</label>
                    <select name="category" class="input-field">
                        <option value="" disabled>Selecciona una categoría</option>
                        @foreach(['moda' => 'Moda y Ropa', 'calzado' => 'Calzado', 'tecnologia' => 'Tecnología', 'alimentos' => 'Alimentos y Bebidas', 'joyeria' => 'Joyería y Accesorios', 'hogar' => 'Hogar y Decoración', 'deporte' => 'Deportes', 'salud' => 'Salud y Belleza', 'servicios' => 'Servicios', 'otros' => 'Otros'] as $value => $label)
                            <option value="{{ $value }}" {{ old('category', $store?->category) == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="input-label">Eslogan / Tagline</label>
                    <input type="text" name="tagline" class="input-field" value="{{ old('tagline', $store?->tagline) }}" placeholder="Ej: El sabor que te enamora">
                </div>
            </div>
            <div>
                <label class="input-label">Descripción</label>
                <textarea name="description" class="input-field" rows="3" placeholder="Breve descripción de tu negocio...">{{ old('description', $store?->description) }}</textarea>
            </div>
        </div>

        {{-- Contacto y Redes --}}
        <div class="glass-card p-5 sm:p-8">
            <h3 class="text-white font-bold text-lg mb-1 flex items-center gap-2">📱 Contacto y Redes</h3>
            <p class="text-xs text-white/50 mb-5">Dónde pueden encontrarte y escribirte tus clientes.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                <div><label class="input-label">Correo Electrónico de Contacto</label><input type="email" name="contact_email" class="input-field" value="{{ old('contact_email', $store?->contact_email) }}" placeholder="hola@tienda.com"></div>
                <div><label class="input-label">Teléfono Fijo / Móvil Secundario</label><input type="text" name="contact_phone" class="input-field" value="{{ old('contact_phone', $store?->contact_phone) }}" placeholder="+51 01 2345678"></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="input-label">WhatsApp (con código país)</label>
                    <input type="tel" name="whatsapp_phone" class="input-field" value="{{ old('whatsapp_phone', $store?->whatsapp_phone) }}" placeholder="51900000000">
                    <p class="text-[11px] text-white/40 mt-1">Aquí llegarán los pedidos de tus clientes. Escribe el código de país sin el símbolo +.</p>
                </div>
                <div><label class="input-label">Ciudad</label><input type="text" name="city" class="input-field" value="{{ old('city', $store?->city) }}" placeholder="Lima"></div>
                <div><label class="input-label">Facebook</label><input type="url" name="facebook_url" class="input-field" value="{{ old('facebook_url', $store?->facebook_url) }}" placeholder="https://facebook.com/..."></div>
                <div><label class="input-label">Instagram</label><input type="url" name="instagram_url" class="input-field" value="{{ old('instagram_url', $store?->instagram_url) }}" placeholder="https://instagram.com/..."></div>
                <div><label class="input-label">TikTok</label><input type="url" name="tiktok_url" class="input-field" value="{{ old('tiktok_url', $store?->tiktok_url) }}" placeholder="https://tiktok.com/@..."></div>
            </div>
        </div>

        {{-- Países de Venta y Monedas --}}
        <div class="glass-card p-5 sm:p-6">
            <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
                <h3 class="text-white font-bold text-sm flex items-center gap-2"><span>🌎</span> Países de Venta y Monedas</h3>
                <span class="text-[11px] px-2 py-0.5 rounded-full bg-tribio-purple/20 text-tribio-cyan border border-tribio-purple/30 font-bold">Multi-País Automático</span>
            </div>
            <p class="text-xs text-white/50 mb-5">Selecciona en qué países deseas vender. El sistema autocalculará los precios con el tipo de cambio y mostrará la moneda de cada país.</p>
            <label class="input-label mb-2">Países donde está disponible tu tienda</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach($supported as $code => $country)
                    <div @click="toggleCountry('{{ $code }}')"
                         :class="isCountryEnabled('{{ $code }}') ? 'border-tribio-cyan/60 bg-tribio-cyan/10 ring-1 ring-tribio-cyan/30' : 'border-white/10 bg-white/5 opacity-60 hover:opacity-100'"
                         class="p-3 rounded-xl border transition-all cursor-pointer flex flex-col justify-between select-none relative group">
                        <input type="checkbox" name="enabled_countries[]" value="{{ $code }}" :checked="isCountryEnabled('{{ $code }}')" class="hidden">
                        <div class="flex items-center justify-between">
                            <span class="text-2xl">{{ $country['flag'] }}</span>
                            <span x-show="isCountryEnabled('{{ $code }}')" class="text-tribio-cyan text-xs font-bold">✓</span>
                        </div>
                        <div class="mt-2">
                            <p class="text-xs font-bold text-white leading-tight">{{ $country['name'] }}</p>
                            <p class="text-[10px] text-white/50 mt-0.5 font-mono">{{ $country['currency'] }} ({{ $country['symbol'] }})</p>
                        </div>
                        @if($code === 'PE')
                            <span class="absolute -top-1.5 -right-1 text-[9px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 px-1.5 py-0.2 rounded-full">Base</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Envíos --}}
        <div class="glass-card p-5 sm:p-6">
            <h4 class="text-white font-bold text-xs flex items-center gap-1.5 uppercase tracking-wider text-tribio-cyan mb-4"><span>🚚</span> Tarifas de Envío</h4>

            <div class="p-3.5 rounded-xl bg-tribio-cyan/5 border border-tribio-cyan/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <p class="text-[11px] text-white/60">📍 Estos campos tienen prioridad sobre las tarifas configuradas en <strong class="text-white">Zonas de Envío</strong> para el mismo país. Déjalos vacíos si prefieres manejar ese país solo desde allá (por ejemplo, para tarifas por departamento/estado).</p>
                <a href="{{ route('dashboard.shipping.index') }}" class="btn-secondary py-2 px-4 text-xs flex-shrink-0 whitespace-nowrap">Ir a Zonas de Envío →</a>
            </div>

            <div class="p-4 rounded-xl bg-white/3 border border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                <div>
                    <div class="flex items-center gap-2.5 mb-1">
                        <span class="text-2xl">🇵🇪</span>
                        <div>
                            <span class="text-xs font-bold text-white block">Envío a todo el Perú (Tarifa Única Nacional)</span>
                            <span class="text-[10px] text-white/40 block font-mono">Moneda: Soles (PEN - S/)</span>
                        </div>
                    </div>
                    <p class="text-[11px] text-white/50">Aplica a cualquier departamento de Perú sin tener que configurar uno por uno.</p>
                </div>
                <div class="w-full sm:w-44 flex-shrink-0 relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <span class="text-xs text-tribio-cyan font-bold select-none">S/</span>
                    </div>
                    <input type="number" step="0.01" min="0" name="national_shipping_cost"
                           class="input-field py-2 text-right font-semibold text-sm"
                           style="padding-left: 2.75rem !important; padding-right: 0.875rem !important;"
                           value="{{ old('national_shipping_cost', $store?->national_shipping_cost) }}"
                           placeholder="Ej: 15.00">
                </div>
            </div>
            <p class="text-[10px] text-white/40 -mt-2.5 mb-4">Vacío = no usar una tarifa única nacional; se consultará <strong class="text-white/60">Zonas de Envío</strong> (departamento y luego país "PE") en su lugar. Pon <strong class="text-white/60">0</strong> solo si de verdad el envío nacional es siempre gratis.</p>

            <div class="mb-5">
                <label class="text-xs font-bold text-white block mb-1">Costos de Envío Internacional (por país habilitado):</label>
                <p class="text-[11px] text-white/40 mb-3">Solo se muestran los países que activaste arriba. Igual que arriba: vacío = usar la tarifa de Zonas de Envío para ese país (o "Resto del Mundo" si tampoco hay una específica).</p>
                <div class="space-y-3">
                    @foreach($supported as $code => $country)
                        @if($code !== 'PE')
                            <div x-show="isCountryEnabled('{{ $code }}')" x-cloak class="p-3.5 rounded-xl bg-white/3 border border-white/10 flex items-center justify-between gap-4 transition-all">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-2xl">{{ $country['flag'] }}</span>
                                    <div>
                                        <span class="text-xs font-bold text-white">{{ $country['name'] }}</span>
                                        <span class="text-[10px] text-white/40 block font-mono">Moneda: {{ $country['currency'] }} ({{ $country['symbol'] }})</span>
                                    </div>
                                </div>
                                <div class="w-36 relative flex-shrink-0">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-xs text-tribio-cyan font-bold select-none">{{ $country['symbol'] }}</span>
                                    </div>
                                    <input type="number" step="0.01" min="0"
                                           name="country_shipping_costs[{{ $code }}]"
                                           value="{{ old('country_shipping_costs.' . $code, $store?->country_shipping_costs[$code] ?? '') }}"
                                           class="input-field py-1.5 text-right font-semibold text-xs"
                                           style="padding-left: 2rem !important; padding-right: 0.75rem !important;"
                                           placeholder="0.00">
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="border-t border-white/10 pt-4">
                <label class="flex items-center gap-3 cursor-pointer mb-3">
                    <div class="relative">
                        <input type="checkbox" name="is_express_shipping_enabled" class="sr-only" x-model="expressEnabled" value="1">
                        <div class="block bg-white/10 w-10 h-6 rounded-full transition-colors" :class="{'bg-tribio-cyan': expressEnabled}"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="{'translate-x-4': expressEnabled}"></div>
                    </div>
                    <span class="text-sm font-medium text-white">🚀 Habilitar Envío Express</span>
                </label>
                <div x-show="expressEnabled" x-cloak class="bg-white/3 border border-white/5 p-4 rounded-xl">
                    <label class="input-label">Costo del Envío Express (S/.)</label>
                    <input type="number" step="0.01" min="0" name="express_shipping_cost" class="input-field" value="{{ old('express_shipping_cost', $store?->express_shipping_cost ?? '0.00') }}" placeholder="0.00">
                    <p class="text-[10px] text-white/40 mt-1">Si es gratis, déjalo en 0. Este monto se sumará al subtotal del pedido si el cliente lo elige.</p>
                </div>
            </div>

            {{-- Envío gratis por cantidad o monto --}}
            <div class="border-t border-white/10 pt-4 mt-4">
                <label class="flex items-center gap-3 cursor-pointer mb-3">
                    <div class="relative">
                        <input type="checkbox" class="sr-only" x-model="freeShippingEnabled">
                        <div class="block bg-white/10 w-10 h-6 rounded-full transition-colors" :class="{'bg-tribio-cyan': freeShippingEnabled}"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="{'translate-x-4': freeShippingEnabled}"></div>
                    </div>
                    <span class="text-sm font-medium text-white">🎁 Envío Gratis a partir de una cantidad o monto</span>
                </label>
                <div x-show="freeShippingEnabled" x-cloak class="bg-white/3 border border-white/5 p-4 rounded-xl space-y-4">
                    <p class="text-[11px] text-white/50">Se activa el envío gratis apenas se cumple <strong>cualquiera</strong> de las dos condiciones. Deja una en blanco si no quieres usarla.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="input-label">Cantidad mínima de productos</label>
                            <input type="number" step="1" min="1" name="free_shipping_min_quantity" class="input-field" value="{{ old('free_shipping_min_quantity', $store?->free_shipping_min_quantity) }}" placeholder="Ej: 3">
                            <p class="text-[10px] text-white/40 mt-1">Ej: 3 → gratis desde 3 productos en el carrito.</p>
                        </div>
                        <div>
                            <label class="input-label">Monto mínimo de compra (S/.)</label>
                            <input type="number" step="0.01" min="0" name="free_shipping_min_amount" class="input-field" value="{{ old('free_shipping_min_amount', $store?->free_shipping_min_amount) }}" placeholder="Ej: 100.00">
                            <p class="text-[10px] text-white/40 mt-1">Ej: 100 → gratis si el pedido suma S/ 100 o más.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Descuento por cantidad (compra al por mayor) --}}
            <div class="border-t border-white/10 pt-4 mt-4">
                <label class="flex items-center gap-3 cursor-pointer mb-3">
                    <div class="relative">
                        <input type="checkbox" class="sr-only" x-model="bulkDiscountEnabled">
                        <div class="block bg-white/10 w-10 h-6 rounded-full transition-colors" :class="{'bg-tribio-cyan': bulkDiscountEnabled}"></div>
                        <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="{'translate-x-4': bulkDiscountEnabled}"></div>
                    </div>
                    <span class="text-sm font-medium text-white">🏷️ Descuento por compra al por mayor</span>
                </label>
                <div x-show="bulkDiscountEnabled" x-cloak class="bg-white/3 border border-white/5 p-4 rounded-xl space-y-4">
                    <p class="text-[11px] text-white/50">Aplica un descuento automático al pedido completo cuando el cliente lleva muchos productos.</p>
                    <div>
                        <label class="input-label">Cantidad mínima de productos</label>
                        <input type="number" step="1" min="1" name="bulk_discount_min_quantity" class="input-field" value="{{ old('bulk_discount_min_quantity', $store?->bulk_discount_min_quantity) }}" placeholder="Ej: 10">
                        <p class="text-[10px] text-white/40 mt-1">Ej: 10 → el descuento se activa desde 10 productos en el carrito.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="input-label">Tipo de descuento</label>
                            <select name="bulk_discount_type" class="input-field">
                                <option value="percentage" {{ old('bulk_discount_type', $store?->bulk_discount_type) === 'percentage' ? 'selected' : '' }}>Porcentaje (%)</option>
                                <option value="fixed" {{ old('bulk_discount_type', $store?->bulk_discount_type) === 'fixed' ? 'selected' : '' }}>Monto fijo (S/.)</option>
                            </select>
                        </div>
                        <div>
                            <label class="input-label">Valor del descuento</label>
                            <input type="number" step="0.01" min="0" name="bulk_discount_value" class="input-field" value="{{ old('bulk_discount_value', $store?->bulk_discount_value) }}" placeholder="Ej: 10">
                        </div>
                    </div>
                    <p class="text-[10px] text-white/40">El descuento se aplica sobre el subtotal del pedido, antes de sumar el envío.</p>
                </div>
            </div>
        </div>

        {{-- Pasarela de Pago: ahora vive en su propia página --}}
        <div class="glass-card p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h3 class="text-white font-bold text-sm mb-1">💳 Modo de Venta y Pasarela de Pago</h3>
                    <p class="text-xs text-white/50">Elige cómo concretar tus pedidos y configura Mercado Pago, PayPal o Flow desde su propia página.</p>
                </div>
                <a href="{{ route('dashboard.gateway.edit') }}" class="btn-secondary py-2 px-4 text-xs flex-shrink-0 whitespace-nowrap">Ir a Pasarela de Pago →</a>
            </div>
        </div>

        {{-- Configuración Avanzada: colapsada por defecto, salvo que ya tenga datos --}}
        <div class="glass-card p-5 sm:p-6">
            <button type="button" @click="advancedOpen = !advancedOpen" class="w-full flex items-center justify-between gap-2 text-left">
                <div>
                    <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60 flex items-center gap-2">⚙️ Configuración Avanzada <span class="badge badge-gray">Opcional</span></h3>
                    <p class="text-xs text-white/50 mt-0.5">Dominio propio, modo de construcción, banner principal, idiomas y distribuidores.</p>
                </div>
                <span class="text-white/40 text-xl flex-shrink-0 leading-none" x-text="advancedOpen ? '−' : '+'"></span>
            </button>

            <div x-show="advancedOpen" x-cloak class="mt-6 pt-5 border-t border-white/10 space-y-6">
                {{-- Modo de Construcción --}}
                <div>
                    <h4 class="text-white font-bold text-sm mb-2">🧩 Modo de Construcción de Sitio</h4>
                    <p class="text-xs text-white/50 mb-3">La mayoría de tiendas usa el constructor visual. Solo cambia esto si un desarrollador te creó una plantilla a medida.</p>
                    <select name="build_mode" class="input-field">
                        <option value="builder" {{ old('build_mode', $store?->build_mode) == 'builder' ? 'selected' : '' }}>Constructor Visual Tribio (recomendado)</option>
                        <option value="custom_code" {{ old('build_mode', $store?->build_mode) == 'custom_code' ? 'selected' : '' }}>Código a Medida (Desarrollador)</option>
                    </select>
                    <p class="text-[11px] text-white/40 mt-1">Si seleccionas "Código a Medida", se ignorará el constructor visual y se buscará una plantilla en <code>resources/views/clientes_custom/{{ $store?->slug ?? 'slug' }}/index.blade.php</code>.</p>
                </div>

                {{-- Dominio Personalizado --}}
                <div class="border-t border-white/5 pt-5">
                    <h4 class="text-white font-bold text-sm mb-2">🌐 Dominio Personalizado</h4>
                    <p class="text-xs text-white/50 mb-3">Configura tu propio dominio de internet para mostrar tu tienda de forma profesional e independiente.</p>
                    <label class="input-label">Tu Dominio Propio</label>
                    <input type="text" name="custom_domain" class="input-field" value="{{ old('custom_domain', $store?->custom_domain) }}" placeholder="ejemplo: mitienda.com">
                    <p class="text-[11px] text-white/40 mt-1">Ingresa el dominio limpio (ej. <code>mitienda.com</code> o <code>tienda.miweb.com</code>). Deja en blanco para usar la URL estándar.</p>
                    @if($store?->custom_domain)
                    <div class="p-4 rounded-xl border border-white/5 bg-white/3 space-y-2 text-xs mt-3">
                        <p class="text-white font-bold">⚙️ Configuración de DNS:</p>
                        <p class="text-white/60">Apunta tu dominio propio a la plataforma configurando estos registros en tu proveedor de dominios (GoDaddy, Namecheap, etc.):</p>
                        <div class="grid grid-cols-1 gap-2 mt-2 font-mono bg-black/30 p-3 rounded-lg border border-white/5">
                            <div>
                                <span class="text-tribio-purple font-bold">Tipo:</span> A |
                                <span class="text-tribio-purple font-bold">Nombre:</span> @ |
                                <span class="text-tribio-purple font-bold">Valor:</span> <code>{{ request()->server('SERVER_ADDR') && !in_array(request()->server('SERVER_ADDR'), ['127.0.0.1', '::1']) ? request()->server('SERVER_ADDR') : 'IP_DE_TU_SERVIDOR' }}</code>
                            </div>
                            <div class="border-t border-white/5 pt-2 mt-1">
                                <span class="text-tribio-purple font-bold">Tipo:</span> CNAME |
                                <span class="text-tribio-purple font-bold">Nombre:</span> www |
                                <span class="text-tribio-purple font-bold">Valor:</span> <code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code>
                            </div>
                        </div>
                        <p class="text-[10px] text-white/40">Nota: Los cambios en el proveedor de dominio pueden tardar hasta 24-48 horas en propagarse.</p>
                    </div>
                    @endif
                </div>

                {{-- Multi Idioma --}}
                <div class="border-t border-white/5 pt-5">
                    <h4 class="text-white font-bold text-sm mb-2">🌍 Multi Idioma Dinámico</h4>
                    <p class="text-xs text-white/50 mb-3">Habilita el selector de idiomas dinámico (Español / Inglés) en el menú principal de tu tienda.</p>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" name="is_multilanguage_enabled" class="sr-only" x-model="langEnabled" value="1">
                            <div class="block bg-white/10 w-10 h-6 rounded-full transition-colors" :class="{'bg-tribio-cyan': langEnabled}"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="{'translate-x-4': langEnabled}"></div>
                        </div>
                        <span class="text-sm font-medium text-white">Activar Multi Idioma (ES / EN)</span>
                    </label>
                </div>

                {{-- Banner Hero --}}
                <div class="border-t border-white/5 pt-5">
                    <h4 class="text-white font-bold text-sm mb-2">📢 Textos del Banner Principal (Hero)</h4>
                    <p class="text-xs text-white/50 mb-3">Configura los mensajes y títulos que aparecen en la primera pantalla de tu tienda.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="input-label">Badge Promocional superior</label>
                            <input type="text" name="hero_badge" class="input-field" value="{{ old('hero_badge', $store?->hero_badge) }}" placeholder="Ej: ENVÍO EXPRESS">
                            <p class="text-[10px] text-white/40 mt-1">Texto pequeño resaltado arriba del título.</p>
                        </div>
                        <div>
                            <label class="input-label">Título Principal</label>
                            <input type="text" name="hero_title" class="input-field" value="{{ old('hero_title', $store?->hero_title) }}" placeholder="Ej: Envíos rápidos a todo el Perú">
                        </div>
                        <div>
                            <label class="input-label">Subtítulo secundario</label>
                            <textarea name="hero_subtitle" class="input-field" rows="2" placeholder="Ej: Compra hoy y recoge a partir de 60 min.">{{ old('hero_subtitle', $store?->hero_subtitle) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Red de Distribuidores --}}
                <div class="border-t border-white/5 pt-5">
                    <h4 class="text-white font-bold text-sm mb-4">🌍 Red de Distribuidores Globales</h4>
                    <p class="text-xs text-white/50 mb-4">Agrega las regiones y ubicaciones de tus distribuidores para que aparezcan en tu página de inicio.</p>
                    <div class="space-y-4">
                        <template x-for="(reg, rIdx) in regions" :key="rIdx">
                            <div class="p-4 rounded-xl border border-white/5 bg-white/3 space-y-3 relative">
                                <button type="button" @click="removeRegion(rIdx)" class="absolute top-4 right-4 text-xs text-red-400 hover:underline">Eliminar Región</button>
                                <div>
                                    <label class="input-label">Región (Ej: AMÉRICA DEL SUR)</label>
                                    <input type="text" :name="'distributors[' + rIdx + '][region]'" x-model="reg.region" class="input-field" placeholder="Región o Continente">
                                </div>
                                <div class="space-y-2">
                                    <label class="input-label">Ciudades / Sucursales</label>
                                    <template x-for="(loc, lIdx) in reg.locations" :key="lIdx">
                                        <div class="flex gap-2">
                                            <input type="text" :name="'distributors[' + rIdx + '][locations][' + lIdx + ']'" x-model="reg.locations[lIdx]" class="input-field py-1.5" placeholder="Ej: Perú (Chiclayo - Oficina Central B2B)">
                                            <button type="button" @click="removeLocation(rIdx, lIdx)" class="px-3 text-red-400 hover:text-red-300 font-bold">✕</button>
                                        </div>
                                    </template>
                                    <button type="button" @click="addLocation(rIdx)" class="text-[11px] text-[#8B5CF6] hover:underline font-bold mt-1">+ Agregar Ciudad/Sucursal</button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addRegion()" class="mt-4 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-xs font-bold transition-colors w-full border border-dashed border-white/20">+ Agregar Nueva Región</button>
                </div>
            </div>
        </div>
    </form>

    {{-- Logo y portada: forms independientes que suben archivo aparte de inmediato —
         deben quedar FUERA del <form> principal: un <form> no puede anidarse dentro
         de otro <form>; si se anida, el navegador cierra el <form> principal en el
         primer </form> que encuentra y todo lo que sigue queda fuera de él (sin
         enviarse nunca). Por eso viven como hermanos del formulario, no dentro. --}}
    <div class="glass-card p-5 sm:p-8">
        <h3 class="text-white font-bold text-lg mb-1 flex items-center gap-2">🖼️ Logo y Carrusel Principal (Hero)</h3>
        <p class="text-xs text-white/50 mb-6">Personaliza el logo y las 3 imágenes que rotan en el banner principal de tu tienda. Se guardan de inmediato, por separado del resto del formulario.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 md:gap-8">
            <form method="POST" action="{{ route('dashboard.store.logo') }}" enctype="multipart/form-data" class="space-y-4 bg-white/3 p-6 rounded-xl border border-white/5">@csrf
                <x-image-picker name="logo" label="Logo de tu tienda" :current="$store?->logo_path ? $store->logo_url : null" :max-mb="2" save-label="Guardar logo" />
            </form>
            <form method="POST" action="{{ route('dashboard.store.cover') }}" enctype="multipart/form-data" class="space-y-4 bg-white/3 p-6 rounded-xl border border-white/5">@csrf
                <x-image-picker name="cover" label="Portada de tu tienda — Imagen 1 del carrusel" :current="$store?->cover_path ? $store->cover_url : null" :max-mb="5" :wide="true" save-label="Guardar portada" />
            </form>
            <form method="POST" action="{{ route('dashboard.store.hero_image_2') }}" enctype="multipart/form-data" class="space-y-4 bg-white/3 p-6 rounded-xl border border-white/5">@csrf
                <x-image-picker name="hero_image_2" label="Imagen 2 del carrusel (Hero)" :current="$store?->hero_image_2_path ? $store->hero_image_2_url : null" :max-mb="5" :wide="true" save-label="Guardar imagen" />
            </form>
            <form method="POST" action="{{ route('dashboard.store.hero_image_3') }}" enctype="multipart/form-data" class="space-y-4 bg-white/3 p-6 rounded-xl border border-white/5">@csrf
                <x-image-picker name="hero_image_3" label="Imagen 3 del carrusel (Hero)" :current="$store?->hero_image_3_path ? $store->hero_image_3_url : null" :max-mb="5" :wide="true" save-label="Guardar imagen" />
            </form>
        </div>
    </div>
</div>
@endsection
