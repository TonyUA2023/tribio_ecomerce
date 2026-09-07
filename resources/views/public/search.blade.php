@extends('layouts.public')
@section('title', 'Buscar — Tribio')

@section('content')
<div class="min-h-screen bg-[#F6F6F6] pt-28 pb-16">
    <div class="container-tribio">
        
        {{-- Cabecera con Buscador Integrado --}}
        <div class="max-w-3xl mx-auto text-center mb-16 mt-8">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-4">Buscar negocios en Tribio</p>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 leading-tight mb-8">
                @if($category)
                    Categoría: {{ config("tribio.business_categories.{$category}.label") }}
                @elseif($query)
                    Resultados para: "{{ $query }}"
                @else
                    Búsqueda de Negocios
                @endif
            </h1>
            
            <form action="{{ route('search') }}" method="GET">
                <div class="relative flex items-center bg-white border border-slate-200 rounded-full p-2 pl-6 shadow-sm focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100 transition-all">
                    <svg class="w-5 h-5 text-slate-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" value="{{ $query }}" placeholder="Busca zapatillas, postres, joyería, moda..."
                           class="w-full bg-transparent border-0 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0 text-base py-3 pr-2"
                           style="font-family: 'Plus Jakarta Sans', sans-serif;">
                    <button type="submit"
                            class="py-3.5 px-8 text-sm rounded-full bg-slate-900 text-white font-bold hover:bg-slate-800 transition flex-shrink-0 shadow-md">
                        Buscar
                    </button>
                </div>
            </form>
        </div>

        {{-- Resultados --}}
        @if($stores->isEmpty())
            <div class="bg-white border border-slate-200 rounded-3xl p-12 text-center shadow-sm max-w-lg mx-auto">
                <p class="text-5xl mb-4">🔍</p>
                <p class="text-slate-800 text-base font-semibold">Sin resultados encontrados.</p>
                <p class="text-slate-400 text-sm mt-1">Prueba usando otros términos o cambiando la categoría seleccionada.</p>
                <div class="mt-6 flex justify-center gap-3">
                    <a href="{{ route('directory') }}" class="btn-secondary">Ver todo el directorio</a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($stores as $store)
                <a href="{{ route('store.show', $store->slug) }}" class="store-card group rounded-3xl overflow-hidden bg-white flex flex-col justify-between">
                    {{-- Portada --}}
                    <div class="h-44 relative overflow-hidden bg-slate-100 border-b border-slate-100">
                        @if($store->cover_path)
                            <img src="{{ $store->cover_url }}" alt="{{ $store->name }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-5xl opacity-40">
                                {{ config('tribio.business_categories')[$store->category]['icon'] ?? '🛍️' }}
                            </div>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="p-6 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-start gap-4">
                                {{-- Logo --}}
                                <div class="w-12 h-12 rounded-2xl overflow-hidden border border-slate-200 flex-shrink-0 -mt-10 relative z-10 bg-white p-1">
                                    @if($store->logo_path)
                                        <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="w-full h-full object-cover rounded-xl">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-lg font-bold bg-sky-100 text-sky-600 rounded-xl">
                                            {{ substr($store->name, 0, 1) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <h3 class="text-slate-900 font-extrabold text-base truncate">{{ $store->name }}</h3>
                                    <p class="text-slate-400 text-xs mt-0.5">
                                        {{ config('tribio.business_categories')[$store->category]['icon'] ?? '' }}
                                        {{ config('tribio.business_categories')[$store->category]['label'] ?? $store->category }}
                                    </p>
                                </div>
                            </div>

                            @if($store->description)
                            <p class="text-slate-500 text-xs mt-4 leading-relaxed line-clamp-2">
                                {{ $store->description }}
                            </p>
                            @endif
                        </div>

                        <div class="flex items-center justify-between mt-5 pt-4 border-t border-slate-100">
                            <span class="text-slate-400 text-[11px] font-medium">{{ $store->active_products_count }} productos publicados</span>
                            <span class="text-sky-500 text-xs font-bold group-hover:translate-x-1 transition-transform">Ver tienda →</span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            
            {{-- Paginación --}}
            <div class="mt-12 flex justify-center">
                {{ $stores->links() }}
            </div>
        @endif

    </div>
</div>
@endsection
