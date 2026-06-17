@extends('layouts.dashboard')
@section('title', 'Crear Producto')
@section('page_title', '+ Crear Producto')
@section('content')
<div class="glass-card p-8 max-w-2xl">
    <form method="POST" action="{{ route('dashboard.productos.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="sm:col-span-2">
                <label class="input-label">Nombre del producto *</label>
                <input type="text" name="name" class="input-field" value="{{ old('name') }}" required placeholder="Ej: Zapatillas Nike Air">
            </div>
            <div>
                <label class="input-label">Precio (S/.) *</label>
                <input type="number" name="price" class="input-field" value="{{ old('price') }}" step="0.01" min="0" required placeholder="0.00">
            </div>
            <div>
                <label class="input-label">Precio anterior (tachado)</label>
                <input type="number" name="compare_price" class="input-field" value="{{ old('compare_price') }}" step="0.01" min="0" placeholder="0.00">
            </div>
            <div>
                <label class="input-label">SKU / Código</label>
                <input type="text" name="sku" class="input-field" value="{{ old('sku') }}" placeholder="Ej: ZAP-001">
            </div>
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
                <label class="input-label">Stock</label>
                <input type="number" name="stock" class="input-field" value="{{ old('stock', 0) }}" min="0">
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" name="track_stock" id="track_stock" value="1" class="w-4 h-4 accent-tribio-purple" {{ old('track_stock') ? 'checked' : '' }}>
                <label for="track_stock" class="text-white/70 text-sm">Controlar inventario</label>
            </div>
            <div class="sm:col-span-2">
                <label class="input-label">Descripción corta</label>
                <input type="text" name="short_description" class="input-field" value="{{ old('short_description') }}" placeholder="Máx 200 caracteres" maxlength="200">
            </div>
            <div class="sm:col-span-2">
                <label class="input-label">Descripción completa</label>
                <textarea name="description" class="input-field" rows="4" placeholder="Describe tu producto...">{{ old('description') }}</textarea>
            </div>
            <div class="sm:col-span-2">
                <label class="input-label">Imagen principal</label>
                <input type="file" name="image" accept="image/*" class="input-field py-2">
            </div>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" class="accent-tribio-purple" {{ old('is_active', true) ? 'checked' : '' }}>
                    <span class="text-white/70 text-sm">Activo</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1" class="accent-tribio-purple" {{ old('is_featured') ? 'checked' : '' }}>
                    <span class="text-white/70 text-sm">Destacado ⭐</span>
                </label>
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
</div>
@endsection
