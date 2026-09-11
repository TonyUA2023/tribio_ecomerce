@extends('layouts.dashboard')
@section('title','Categorías') 
@section('page_title','🗂️ Categorías')

@section('content')
<div x-data="{
    headerCount: {{ $categories->where('show_in_header', true)->count() }},
    maxHeader: 5,
    categoriesHeader: {
        @foreach($categories as $c)
            {{ $c->id }}: {{ $c->show_in_header ? 'true' : 'false' }},
        @endforeach
    },
    categoriesFeatured: {
        @foreach($categories as $c)
            {{ $c->id }}: {{ $c->is_featured ? 'true' : 'false' }},
        @endforeach
    },
    loading: false,
    alertMsg: '',
    alertType: 'success',
    showAlert(msg, type = 'success') {
        this.alertMsg = msg;
        this.alertType = type;
        setTimeout(() => { this.alertMsg = ''; }, 4000);
    },
    async toggleHeader(catId, catName) {
        const currentlyActive = this.categoriesHeader[catId];
        if (!currentlyActive && this.headerCount >= this.maxHeader) {
            this.showAlert('⚠️ Has alcanzado el límite de 5 categorías en el encabezado. Desmarca una primero para agregar ' + catName + '.', 'error');
            return;
        }

        this.loading = true;
        try {
            const res = await fetch(`/dashboard/categorias/${catId}/toggle-header`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                this.categoriesHeader[catId] = data.show_in_header;
                this.headerCount = data.header_count;
                this.showAlert(data.message, 'success');
            } else {
                this.showAlert(data.message || 'Error al actualizar', 'error');
            }
        } catch (e) {
            this.showAlert('Error de conexión al servidor.', 'error');
        } finally {
            this.loading = false;
        }
    },
    async toggleFeatured(catId, catName) {
        this.loading = true;
        try {
            const res = await fetch(`/dashboard/categorias/${catId}/toggle-featured`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                this.categoriesFeatured[catId] = data.is_featured;
                this.showAlert(data.message, 'success');
            } else {
                this.showAlert(data.message || 'Error al actualizar', 'error');
            }
        } catch (e) {
            this.showAlert('Error de conexión al servidor.', 'error');
        } finally {
            this.loading = false;
        }
    }
}">
    <!-- Notification Alert -->
    <div x-show="alertMsg" x-transition 
         :class="alertType === 'error' ? 'bg-red-500/20 border-red-500/40 text-red-300' : 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300'"
         class="mb-4 p-4 rounded-xl border text-sm flex items-center justify-between shadow-lg" style="display: none;">
        <span x-text="alertMsg"></span>
        <button @click="alertMsg = ''" class="text-xs opacity-70 hover:opacity-100">✕</button>
    </div>

    @if(session('success'))
        <div class="mb-4 p-4 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-xl bg-red-500/20 border border-red-500/40 text-red-300 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-white font-bold text-lg">{{ $categories->count() }} categorías</h2>
            <div class="flex flex-wrap items-center gap-2 mt-1">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border transition-all"
                      :class="headerCount >= 5 ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-tribio-purple/20 text-tribio-cyan border-tribio-purple/30'">
                    📌 En encabezado: <strong x-text="headerCount"></strong> / 5
                </span>
                <span class="text-xs text-white/50">Administra tus categorías destacadas y fotos</span>
            </div>
        </div>
        <a href="{{ route('dashboard.categorias.create') }}" class="btn-primary self-start sm:self-auto">+ Nueva Categoría</a>
    </div>

    <div class="glass-card overflow-hidden">
        @forelse($categories as $cat)
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 border-b hover:bg-white/3 transition-colors" style="border-color:rgba(255,255,255,0.05);">
            <div class="flex items-center gap-3">
                {{-- Category Thumbnail / Photo --}}
                <div class="w-12 h-12 rounded-xl overflow-hidden bg-white/5 border border-white/10 flex items-center justify-center shrink-0 relative">
                    @if($cat->image_path)
                        <img src="{{ Storage::disk('public')->url($cat->image_path) }}" alt="{{ $cat->name }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-2xl">{{ $cat->icon ?? '🗂️' }}</span>
                    @endif
                </div>

                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-white font-bold text-sm">{{ $cat->name }}</span>
                        
                        {{-- Badges --}}
                        <span x-show="categoriesFeatured[{{ $cat->id }}]" 
                              class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1">
                            ⭐ Destacada
                        </span>
                        <span x-show="categoriesHeader[{{ $cat->id }}]" 
                              class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            📌 En encabezado
                        </span>
                    </div>
                    <p class="text-white/40 text-xs mt-0.5">
                        {{ $cat->active_products_count }} productos activos
                        @if($cat->icon) • Ícono: {{ $cat->icon }} @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 self-end sm:self-auto">
                {{-- Botón Destacada (Inicio) --}}
                <button type="button" 
                        @click="toggleFeatured({{ $cat->id }}, '{{ addslashes($cat->name) }}')" 
                        :disabled="loading"
                        class="text-xs px-2.5 py-1.5 rounded-lg border font-medium transition-all flex items-center gap-1"
                        :class="categoriesFeatured[{{ $cat->id }}] 
                            ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 hover:bg-red-500/20 hover:text-red-300 hover:border-red-500/40' 
                            : 'bg-white/5 text-white/70 border-white/10 hover:bg-white/10 hover:text-white'">
                    <span x-text="categoriesFeatured[{{ $cat->id }}] ? '★ Destacada' : '☆ Destacar'"></span>
                </button>

                {{-- Botón Mostrar en Encabezado --}}
                <button type="button" 
                        @click="toggleHeader({{ $cat->id }}, '{{ addslashes($cat->name) }}')" 
                        :disabled="loading"
                        class="text-xs px-2.5 py-1.5 rounded-lg border font-medium transition-all flex items-center gap-1"
                        :class="categoriesHeader[{{ $cat->id }}] 
                            ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 hover:bg-red-500/20 hover:text-red-300 hover:border-red-500/40' 
                            : (headerCount >= 5 
                                ? 'bg-white/5 text-white/30 border-white/5 cursor-not-allowed' 
                                : 'bg-white/5 text-white/70 border-white/10 hover:bg-white/10 hover:text-white')">
                    <span x-text="categoriesHeader[{{ $cat->id }}] ? '✓ Encabezado' : '+ Encabezado'"></span>
                </button>

                <a href="{{ route('dashboard.categorias.edit', $cat) }}" class="btn-ghost py-1.5 px-3 text-xs">Editar</a>
                
                <form method="POST" action="{{ route('dashboard.categorias.destroy', $cat) }}" onsubmit="return confirm('¿Eliminar esta categoría?')">
                    @csrf 
                    @method('DELETE')
                    <button type="submit" class="btn-ghost py-1.5 px-2.5 text-xs text-red-400 hover:text-red-300">Eliminar</button>
                </form>
            </div>
        </div>
        @empty
        <div class="text-center py-12 text-white/40">
            Sin categorías. <a href="{{ route('dashboard.categorias.create') }}" class="text-tribio-cyan underline">Crear primera</a>
        </div>
        @endforelse
    </div>
</div>
@endsection
