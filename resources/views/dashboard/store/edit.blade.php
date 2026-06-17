@extends('layouts.dashboard')
@section('title','Mi Tienda') @section('page_title','⚙️ Configurar Mi Tienda')
@section('content')
<div class="glass-card p-8 max-w-2xl">
    <form method="POST" action="{{ route('dashboard.store.update') }}" class="space-y-5">@csrf
        <div><label class="input-label">Nombre del negocio *</label><input type="text" name="name" class="input-field" value="{{ old('name', $store?->name) }}" required></div>
        <div><label class="input-label">Eslogan / Tagline</label><input type="text" name="tagline" class="input-field" value="{{ old('tagline', $store?->tagline) }}" placeholder="Ej: El sabor que te enamora"></div>
        <div><label class="input-label">Descripción</label><textarea name="description" class="input-field" rows="3" placeholder="Breve descripción de tu negocio...">{{ old('description', $store?->description) }}</textarea></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div><label class="input-label">WhatsApp (con código país)</label><input type="tel" name="whatsapp_phone" class="input-field" value="{{ old('whatsapp_phone', $store?->whatsapp_phone) }}" placeholder="51900000000"></div>
            <div><label class="input-label">Ciudad</label><input type="text" name="city" class="input-field" value="{{ old('city', $store?->city) }}" placeholder="Lima"></div>
            <div><label class="input-label">Facebook</label><input type="url" name="facebook_url" class="input-field" value="{{ old('facebook_url', $store?->facebook_url) }}" placeholder="https://facebook.com/..."></div>
            <div><label class="input-label">Instagram</label><input type="url" name="instagram_url" class="input-field" value="{{ old('instagram_url', $store?->instagram_url) }}" placeholder="https://instagram.com/..."></div>
        </div>
        <!-- Distributors (Distribuidores) Section -->
        <div class="border-t border-white/10 pt-6" x-data="{
            regions: {{ json_encode(old('distributors', $store->distributors ?? [])) }} || [],
            addRegion() {
                this.regions.push({ region: '', locations: [''] });
            },
            removeRegion(index) {
                this.regions.splice(index, 1);
            },
            addLocation(regionIndex) {
                this.regions[regionIndex].locations.push('');
            },
            removeLocation(regionIndex, locIndex) {
                this.regions[regionIndex].locations.splice(locIndex, 1);
                if (this.regions[regionIndex].locations.length === 0) {
                    this.regions[regionIndex].locations.push('');
                }
            }
        }">
            <h3 class="text-white font-bold text-sm mb-4">🌍 Red de Distribuidores Globales</h3>
            <p class="text-xs text-white/50 mb-4">Agrega las regiones y ubicaciones de tus distribuidores para que aparezcan en tu página de inicio.</p>
            
            <div class="space-y-4">
                <template x-for="(reg, rIdx) in regions" :key="rIdx">
                    <div class="p-4 rounded-xl border border-white/5 bg-white/3 space-y-3 relative">
                        <button type="button" @click="removeRegion(rIdx)" class="absolute top-4 right-4 text-xs text-red-400 hover:underline">Eliminar Región</button>
                        
                        <div>
                            <label class="input-label">Región (Ej: AMÉRICA DEL SUR)</label>
                            <input type="text" :name="'distributors[' + rIdx + '][region]'" x-model="reg.region" class="input-field" required placeholder="Región o Continente">
                        </div>
                        
                        <div class="space-y-2">
                            <label class="input-label">Ciudades / Sucursales</label>
                            <template x-for="(loc, lIdx) in reg.locations" :key="lIdx">
                                <div class="flex gap-2">
                                    <input type="text" :name="'distributors[' + rIdx + '][locations][' + lIdx + ']'" x-model="reg.locations[lIdx]" class="input-field py-1.5" required placeholder="Ej: Perú (Chiclayo - Oficina Central B2B)">
                                    <button type="button" @click="removeLocation(rIdx, lIdx)" class="px-3 text-red-400 hover:text-red-300 font-bold">✕</button>
                                </div>
                            </template>
                            <button type="button" @click="addLocation(rIdx)" class="text-[11px] text-[#8B5CF6] hover:underline font-bold mt-1">+ Agregar Ciudad/Sucursal</button>
                        </div>
                    </div>
                </template>
            </div>
            
            <button type="button" @click="addRegion()" class="mt-4 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-xs font-bold transition-colors">
                + Agregar Nueva Región
            </button>
        </div>

        @if($errors->any())<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">@foreach($errors->all() as $e)<p>• {{ $e }}</p>@endforeach</div>@endif
        <button type="submit" class="btn-primary btn-primary-lg">Guardar cambios</button>
    </form>
</div>

{{-- Logo upload --}}
<div class="glass-card p-6 max-w-2xl mt-6">
    <h3 class="text-white font-bold mb-4">🖼️ Logo y Portada</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <form method="POST" action="{{ route('dashboard.store.logo') }}" enctype="multipart/form-data" class="space-y-3">@csrf
            @if($store?->logo_path)<img src="{{ $store->logo_url }}" class="h-16 w-auto rounded-xl mb-2">@endif
            <label class="input-label">Logo (PNG recomendado)</label>
            <input type="file" name="logo" accept="image/*" class="input-field py-2">
            <button type="submit" class="btn-secondary w-full justify-center">Subir logo</button>
        </form>
        <form method="POST" action="{{ route('dashboard.store.cover') }}" enctype="multipart/form-data" class="space-y-3">@csrf
            @if($store?->cover_path)<img src="{{ $store->cover_url }}" class="h-16 w-full object-cover rounded-xl mb-2">@endif
            <label class="input-label">Imagen de portada</label>
            <input type="file" name="cover" accept="image/*" class="input-field py-2">
            <button type="submit" class="btn-secondary w-full justify-center">Subir portada</button>
        </form>
    </div>
</div>

{{-- Template selection --}}
<div class="glass-card p-6 max-w-2xl mt-6">
    <h3 class="text-white font-bold mb-4">🎨 Diseño de la Tienda</h3>
    <form method="POST" action="{{ route('dashboard.store.template') }}" class="space-y-5">@csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($templates as $key => $tpl)
            <label class="cursor-pointer">
                <input type="radio" name="template_name" value="{{ $key }}" {{ $store?->template_name === $key ? 'checked' : '' }} class="hidden peer">
                <div class="template-card peer-checked:selected p-4 text-center {{ $store?->template_name === $key ? 'border-tribio-purple/50 bg-tribio-purple/10' : 'border-white/10 bg-white/3' }} hover:border-white/25 transition-all">
                    <p class="text-white font-bold text-sm mb-1">{{ $tpl['name'] }}</p>
                    <p class="text-white/40 text-xs">{{ $tpl['description'] }}</p>
                </div>
            </label>
            @endforeach
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="input-label">Color principal</label>
                <input type="color" name="accent_color" value="{{ $store?->accent_color ?? '#8B5CF6' }}" class="w-full h-10 rounded-xl border-0 cursor-pointer"></div>
            <div><label class="input-label">Color secundario</label>
                <input type="color" name="secondary_color" value="{{ $store?->secondary_color ?? '#F59E0B' }}" class="w-full h-10 rounded-xl border-0 cursor-pointer"></div>
        </div>
        <button type="submit" class="btn-primary">Guardar diseño</button>
    </form>
</div>
@endsection
