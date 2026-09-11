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

            {{-- Precios y Origen --}}
            <div class="glass-card p-6">
                <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">Precios (PEN y USD)</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="input-label font-bold text-tribio-cyan">Precio (S/.) *</label>
                        <input type="number" name="price" class="input-field" value="{{ old('price') }}" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    <div>
                        <label class="input-label text-tribio-cyan">Precio anterior (S/.)</label>
                        <input type="number" name="compare_price" class="input-field" value="{{ old('compare_price') }}" step="0.01" min="0" placeholder="0.00">
                    </div>
                    
                    <div>
                        <label class="input-label font-bold text-green-400">Precio (USD $)</label>
                        <input type="number" name="price_usd" class="input-field border-green-500/30 focus:border-green-500" value="{{ old('price_usd') }}" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <div>
                        <label class="input-label text-green-400">Precio anterior (USD $)</label>
                        <input type="number" name="compare_price_usd" class="input-field border-green-500/30 focus:border-green-500" value="{{ old('compare_price_usd') }}" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                
                <div>
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
            <div class="glass-card p-6">
                <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">Organización</h3>
                <div class="space-y-4">
                    <div>
                        <label class="input-label">Categoría</label>
                        <select name="category_id" class="input-field">
                            <option value="">Sin categoría</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->icon }} {{ $cat->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
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
