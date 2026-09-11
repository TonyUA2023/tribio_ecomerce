@extends('layouts.dashboard')
@section('title','Editar Categoría') 
@section('page_title','✏️ Editar Categoría')

@section('content')
<div class="max-w-3xl" x-data="{
    selectedEmoji: '{{ old('icon', $category->icon ?? '⭐') }}',
    customEmoji: '',
    previewUrl: '{{ $category->image_path ? Storage::disk('public')->url($category->image_path) : '' }}',
    removeImage: false,
    previewImage(event) {
        const file = event.target.files[0];
        if (file) {
            this.previewUrl = URL.createObjectURL(file);
            this.removeImage = false;
        }
    },
    emojis: [
        { label: 'Destacados', list: ['⭐', '🔥', '✨', '🏷️', '💎', '🎁'] },
        { label: 'Moda & Ropa', list: ['👕', '👗', '👟', '🎒', '🧢', '🕶️', '💍'] },
        { label: 'Hogar & Cocina', list: ['🛋️', '🛏️', '🍳', '☕', '🛁', '🪴', '🕯️'] },
        { label: 'Tecnología', list: ['📱', '💻', '🎧', '🎮', '📷', '⌚'] },
        { label: 'Belleza & Cuidado', list: ['💄', '🧴', '💅', '🌸', '🧼'] },
        { label: 'Varios', list: ['📦', '🍕', '🍷', '⚽', '🚗', '🧸', '📚'] }
    ]
}">
    <form method="POST" action="{{ route('dashboard.categorias.update', $category) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Datos Principales --}}
        <div class="glass-card p-6 space-y-4">
            <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60">Datos de la Categoría</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="input-label">Nombre de la Categoría *</label>
                    <input type="text" name="name" class="input-field" value="{{ old('name', $category->name) }}" required placeholder="Ej: Ropa, Cocina, Calzado">
                </div>
                <div>
                    <label class="input-label">Categoría Padre (Opcional)</label>
                    <select name="parent_id" class="input-field">
                        <option value="">-- Ninguna (Categoría Principal) --</option>
                        @foreach($parentCategories as $parent)
                            <option value="{{ $parent->id }}" {{ old('parent_id', $category->parent_id) == $parent->id ? 'selected' : '' }}>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="input-label">Color de Acento / Fondo</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="color" value="{{ old('color', $category->color ?? '#8B5CF6') }}" class="h-10 w-20 rounded-xl border-0 bg-transparent cursor-pointer">
                    <span class="text-xs text-white/50">Se usará como fondo en las tarjetas si la categoría no tiene foto.</span>
                </div>
            </div>
        </div>

        {{-- Selector Intuitivo de Emojis / Ícono --}}
        <div class="glass-card p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60">Ícono de la Categoría (Emoji)</h3>
                    <p class="text-xs text-white/50 mt-0.5">Elige un emoji representativo que aparecerá en el distintivo de la tarjeta y en los menús.</p>
                </div>
                
                {{-- Preview Box --}}
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-2xl bg-white/10 border border-white/20">
                    <span class="text-xs text-white/60">Seleccionado:</span>
                    <span class="text-2xl" x-text="selectedEmoji"></span>
                    <input type="hidden" name="icon" :value="selectedEmoji">
                </div>
            </div>

            {{-- Paleta de Emojis por Categoría --}}
            <div class="space-y-3 pt-1">
                <template x-for="group in emojis" :key="group.label">
                    <div>
                        <span class="block text-[11px] font-semibold text-white/40 mb-1" x-text="group.label"></span>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="em in group.list" :key="em">
                                <button type="button" 
                                        @click="selectedEmoji = em; customEmoji = ''"
                                        :class="selectedEmoji === em ? 'bg-tribio-purple text-white ring-2 ring-tribio-cyan scale-110' : 'bg-white/5 hover:bg-white/15 text-white/80 border border-white/10'"
                                        class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-all transform active:scale-95">
                                    <span x-text="em"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Opción para escribir otro emoji manual --}}
            <div class="pt-2 border-t border-white/10 flex items-center gap-3">
                <span class="text-xs text-white/50">¿Prefieres otro emoji?</span>
                <input type="text" x-model="customEmoji" @input="if(customEmoji) selectedEmoji = customEmoji" 
                       placeholder="Escribe o pega aquí (Ej: 🥑)" maxlength="4"
                       class="input-field py-1 px-3 text-sm w-36 text-center">
            </div>
        </div>

        {{-- Foto de la Categoría --}}
        <div class="glass-card p-6 space-y-4">
            <div>
                <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60">Foto de la Categoría</h3>
                <p class="text-xs text-white/50 mt-0.5">Sube una foto que se mostrará como imagen de fondo en la sección de <strong>Categorías destacadas</strong>.</p>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-5">
                {{-- Image Preview Box --}}
                <div class="w-32 h-36 rounded-2xl overflow-hidden bg-white/5 border-2 border-dashed border-white/20 flex items-center justify-center relative shrink-0">
                    <template x-if="previewUrl && !removeImage">
                        <img :src="previewUrl" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!previewUrl || removeImage">
                        <div class="text-center p-2">
                            <span class="text-3xl opacity-40">🖼️</span>
                            <span class="block text-[10px] text-white/40 mt-1">Sin foto</span>
                        </div>
                    </template>
                </div>

                {{-- Upload Button & Options --}}
                <div class="flex-1 space-y-3 w-full">
                    <div>
                        <label class="input-label">Cambiar imagen (JPG, PNG, WEBP)</label>
                        <input type="file" name="image" accept="image/*" @change="previewImage($event)"
                               class="input-field py-2 text-xs">
                    </div>

                    @if($category->image_path)
                    <div class="pt-1">
                        <label class="flex items-center gap-2 cursor-pointer text-xs text-red-400 hover:text-red-300">
                            <input type="checkbox" name="remove_image" value="1" x-model="removeImage" class="accent-red-500 rounded">
                            <span>Quitar foto actual de esta categoría</span>
                        </label>
                    </div>
                    @endif

                    <p class="text-[11px] text-white/40">Recomendado: Imágenes verticales o cuadradas de al menos 400x500px para una mejor definición visual.</p>
                </div>
            </div>
        </div>

        {{-- Visibilidad y Opciones --}}
        <div class="glass-card p-6 space-y-3">
            <h3 class="text-white font-bold text-sm uppercase tracking-wider opacity-60 mb-2">Visibilidad en la Tienda</h3>
            
            <label class="flex items-start gap-3 cursor-pointer p-2.5 rounded-xl hover:bg-white/5 transition">
                <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $category->is_featured) ? 'checked' : '' }} class="mt-0.5 accent-tribio-purple w-4 h-4 rounded">
                <div>
                    <span class="text-white font-bold text-sm flex items-center gap-1.5">
                        ⭐ Categoría destacada
                    </span>
                    <p class="text-xs text-white/50 mt-0.5">Mostrar en la cuadrícula de "Categorías destacadas" en la página de inicio de la tienda virtual.</p>
                </div>
            </label>

            <label class="flex items-start gap-3 cursor-pointer p-2.5 rounded-xl hover:bg-white/5 transition">
                <input type="checkbox" name="show_in_header" value="1" {{ old('show_in_header', $category->show_in_header) ? 'checked' : '' }} class="mt-0.5 accent-tribio-cyan w-4 h-4 rounded">
                <div>
                    <span class="text-white font-bold text-sm flex items-center gap-1.5">
                        📌 Mostrar en el encabezado
                    </span>
                    <p class="text-xs text-white/50 mt-0.5">Aparece en la barra de navegación superior (máximo 5 categorías para un diseño estético).</p>
                </div>
            </label>

            <label class="flex items-start gap-3 cursor-pointer p-2.5 rounded-xl hover:bg-white/5 transition">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $category->is_active) ? 'checked' : '' }} class="mt-0.5 accent-tribio-purple w-4 h-4 rounded">
                <div>
                    <span class="text-white font-bold text-sm">Activa</span>
                    <p class="text-xs text-white/50 mt-0.5">La categoría está habilitada para clasificar productos y visible para los clientes.</p>
                </div>
            </label>
        </div>

        @if($errors->any())
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm space-y-1">
            @foreach($errors->all() as $e) <p>• {{ $e }}</p> @endforeach
        </div>
        @endif

        <div class="flex gap-3 pt-2">
            <button type="submit" class="btn-primary btn-primary-lg">Guardar Cambios</button>
            <a href="{{ route('dashboard.categorias.index') }}" class="btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
@endsection
