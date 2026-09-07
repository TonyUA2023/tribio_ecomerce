@extends('layouts.dashboard')

@section('title', 'Plantillas')
@section('page_title', '🎨 Diseños y Plantillas')

@section('content')
<div class="space-y-6">
    <div class="glass-card p-8">
        <div class="mb-8">
            <h2 class="text-xl font-extrabold text-white">Elige el diseño de tu e-commerce</h2>
            <p class="text-slate-400 text-sm mt-1">Selecciona una de las plantillas disponibles. Los cambios se verán reflejados inmediatamente en tu tienda virtual.</p>
        </div>

        <form method="POST" action="{{ route('dashboard.store.template') }}" class="space-y-8">
            @csrf

            {{-- Grid de Plantillas --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($templates as $key => $template)
                <label class="cursor-pointer">
                    <input type="radio" name="template_name" value="{{ $key }}"
                           {{ $store->template_name === $key ? 'checked' : '' }}
                           class="hidden peer">
                    
                    <div class="template-card rounded-2xl overflow-hidden bg-slate-900/40 border border-white/10 transition-all hover:scale-[1.01] hover:border-white/20">
                        {{-- Preview simulado --}}
                        <div class="h-36 relative overflow-hidden
                            {{ $key === 'elegant-dark' ? 'bg-gradient-to-br from-gray-900 via-purple-900/30 to-gray-900' :
                               ($key === 'minimal-light' ? 'bg-gradient-to-br from-gray-50 to-white' :
                               ($key === 'elegant-refurbished' ? 'bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900' :
                               ($key === 'industrial-light' ? 'bg-gradient-to-br from-[#1e1e24] via-red-950/20 to-[#1e1e24]' :
                               'bg-gradient-to-br from-pink-50 via-yellow-50 to-orange-50'))) }}">
                            
                            {{-- Elementos decorativos del preview --}}
                            <div class="absolute inset-x-4 top-4 h-8 rounded-lg opacity-80
                                {{ $key === 'elegant-dark' ? 'bg-gradient-to-r from-purple-600 to-pink-500' :
                                   ($key === 'minimal-light' ? 'bg-gray-800' :
                                   ($key === 'elegant-refurbished' ? 'bg-gradient-to-r from-sky-500 to-blue-600' :
                                   ($key === 'industrial-light' ? 'bg-red-600' :
                                   'bg-gradient-to-r from-pink-400 to-yellow-400'))) }}">
                            </div>

                            <div class="absolute inset-x-4 bottom-4 grid grid-cols-3 gap-1">
                                @for($j = 0; $j < 3; $j++)
                                <div class="h-10 rounded-lg
                                    {{ $key === 'elegant-dark' ? 'bg-white/10' :
                                       ($key === 'minimal-light' ? 'bg-gray-200' :
                                       ($key === 'elegant-refurbished' ? 'bg-white/10' :
                                       ($key === 'industrial-light' ? 'bg-white/5' :
                                       'bg-white/70'))) }}">
                                </div>
                                @endfor
                            </div>

                            {{-- Selected checkmark --}}
                            <div class="selected-checkmark absolute top-2 right-2 hidden w-6 h-6 rounded-full bg-purple-500 items-center justify-center text-white text-xs font-bold shadow-sm">✓</div>
                        </div>

                        <div class="p-5">
                            <p class="text-white font-extrabold text-sm">{{ $template['name'] }}</p>
                            <p class="text-slate-400 text-xs mt-1 line-clamp-2 leading-relaxed">{{ $template['description'] }}</p>
                            <div class="flex gap-1.5 mt-4 flex-wrap">
                                @foreach($template['tags'] as $tag)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/5 text-slate-300 border border-white/5">{{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </label>
                @endforeach
            </div>

            {{-- Paleta de Colores --}}
            <div class="border-t border-white/10 pt-6 max-w-xl">
                <h3 class="text-white font-bold text-sm mb-4">🎨 Personalización de Colores</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="input-label">Color principal / Acento</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="accent_color" id="accent_color" value="{{ $store->accent_color ?? '#8B5CF6' }}" class="w-12 h-10 rounded-xl border-0 cursor-pointer bg-transparent">
                            <input type="text" class="input-field py-2 text-sm font-mono" value="{{ $store->accent_color ?? '#8B5CF6' }}" oninput="document.getElementById('accent_color').value = this.value">
                        </div>
                    </div>
                    <div>
                        <label class="input-label">Color secundario</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="secondary_color" id="secondary_color" value="{{ $store->secondary_color ?? '#F59E0B' }}" class="w-12 h-10 rounded-xl border-0 cursor-pointer bg-transparent">
                            <input type="text" class="input-field py-2 text-sm font-mono" value="{{ $store->secondary_color ?? '#F59E0B' }}" oninput="document.getElementById('secondary_color').value = this.value">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Mensajes de Error/Éxito localizados --}}
            @if(session('success'))
            <div class="p-4 rounded-2xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm">
                {{ session('success') }}
            </div>
            @endif

            @if($errors->any())
            <div class="p-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
                @foreach($errors->all() as $e)
                    <p>• {{ $e }}</p>
                @endforeach
            </div>
            @endif

            <div class="flex justify-start">
                <button type="submit" class="btn-primary btn-primary-lg px-8">
                    Guardar diseño y plantilla
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
