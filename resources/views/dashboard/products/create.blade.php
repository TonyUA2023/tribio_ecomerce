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
            <div class="glass-card p-6">
                <h3 class="text-white font-bold mb-4 text-sm uppercase tracking-wider opacity-60">Imagen Principal</h3>
                <div>
                    <label class="input-label">Subir foto</label>
                    <input type="file" name="image" accept="image/*" class="input-field py-2">
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
