@extends('layouts.dashboard')
@section('title', 'Editar: ' . $product->name)
@section('page_title', '✏️ Editar Producto')
@section('content')
<div class="glass-card p-8 max-w-2xl">
    <form method="POST" action="{{ route('dashboard.productos.update', $product) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf @method('PUT')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div class="sm:col-span-2">
                <label class="input-label">Nombre del producto *</label>
                <input type="text" name="name" class="input-field" value="{{ old('name', $product->name) }}" required>
            </div>
            <div>
                <label class="input-label">Precio (S/.) *</label>
                <input type="number" name="price" class="input-field" value="{{ old('price', $product->price) }}" step="0.01" min="0" required>
            </div>
            <div>
                <label class="input-label">Precio anterior</label>
                <input type="number" name="compare_price" class="input-field" value="{{ old('compare_price', $product->compare_price) }}" step="0.01" min="0">
            </div>
            <div>
                <label class="input-label">SKU</label>
                <input type="text" name="sku" class="input-field" value="{{ old('sku', $product->sku) }}">
            </div>
            <div>
                <label class="input-label">Categoría</label>
                <select name="category_id" class="input-field">
                    <option value="">Sin categoría</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>
                        {{ $cat->icon }} {{ $cat->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="input-label">Stock</label>
                <input type="number" name="stock" class="input-field" value="{{ old('stock', $product->stock) }}" min="0">
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" name="track_stock" id="track_stock" value="1" class="w-4 h-4 accent-tribio-purple" {{ $product->track_stock ? 'checked' : '' }}>
                <label for="track_stock" class="text-white/70 text-sm">Controlar inventario</label>
            </div>
            <div class="sm:col-span-2">
                <label class="input-label">Descripción corta</label>
                <input type="text" name="short_description" class="input-field" value="{{ old('short_description', $product->short_description) }}">
            </div>
            <div class="sm:col-span-2">
                <label class="input-label">Descripción completa</label>
                <textarea name="description" class="input-field" rows="4">{{ old('description', $product->description) }}</textarea>
            </div>
            @if($product->image_path)
            <div class="sm:col-span-2">
                <p class="input-label">Imagen actual</p>
                <img src="{{ $product->image_url }}" class="h-24 w-auto rounded-xl object-cover">
            </div>
            @endif
            <div class="sm:col-span-2">
                <label class="input-label">Reemplazar imagen</label>
                <input type="file" name="image" accept="image/*" class="input-field py-2">
            </div>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" class="accent-tribio-purple" {{ $product->is_active ? 'checked' : '' }}>
                    <span class="text-white/70 text-sm">Activo</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1" class="accent-tribio-purple" {{ $product->is_featured ? 'checked' : '' }}>
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
            <button type="submit" class="btn-primary btn-primary-lg">Guardar cambios</button>
            <a href="{{ route('dashboard.productos.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
@endsection
