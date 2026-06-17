@extends('layouts.public')

@section('title', 'Tribio — Tu Negocio Vendiendo las 24 Horas')
@section('meta_description', 'Tribio te crea una tienda virtual para vender tus productos o servicios por internet sin complicaciones. Desde S/. 29.90 al mes.')

@section('content')

{{-- ═══════════════════════════════════════════════════════════
     HERO SECTION
     ═══════════════════════════════════════════════════════════ --}}
<section class="relative min-h-screen flex items-center hero-bg grid-pattern overflow-hidden">

    {{-- Decorative orbs --}}
    <div class="absolute top-1/4 left-1/4 w-72 h-72 rounded-full bg-tribio-purple/20 blur-3xl animate-pulse pointer-events-none"></div>
    <div class="absolute top-1/3 right-1/4 w-96 h-96 rounded-full bg-tribio-pink/10 blur-3xl animate-pulse animation-delay-300 pointer-events-none" style="animation-delay:1.5s"></div>
    <div class="absolute bottom-1/4 left-1/3 w-64 h-64 rounded-full bg-tribio-cyan/10 blur-3xl animate-pulse pointer-events-none" style="animation-delay:3s"></div>

    <div class="container-tribio relative z-10 pt-28 pb-20">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

            {{-- Left: Copy --}}
            <div class="text-center lg:text-left animate-slide-up">
                {{-- Badge --}}
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-tribio-purple/20 border border-tribio-purple/30 text-tribio-cyan text-sm font-semibold mb-8">
                    <span class="w-2 h-2 rounded-full bg-tribio-cyan animate-pulse"></span>
                    Plataforma #1 para emprendedores 🚀
                </div>

                <h1 class="section-title text-white mb-6">
                    Tu Negocio<br>
                    <span class="gradient-text">Vendiendo</span><br>
                    <span class="text-tribio-gold">las 24 Horas</span>
                </h1>

                <p class="text-white/60 text-lg leading-relaxed mb-10 max-w-lg mx-auto lg:mx-0">
                    Tribio te crea una tienda virtual para vender tus productos o servicios por internet <strong class="text-white/90">sin complicaciones</strong>. Muestra tus productos, recibe pedidos y organiza tus ventas.
                </p>

                {{-- CTA --}}
                <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                    <a href="{{ route('register') }}" class="btn-gold btn-primary-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Crear mi tienda gratis
                    </a>
                    <a href="{{ route('directory') }}" class="btn-secondary btn-primary-lg">
                        Ver negocios →
                    </a>
                </div>

                {{-- Stats --}}
                <div class="flex items-center gap-8 mt-12 justify-center lg:justify-start">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-white">{{ number_format($totalStores) }}+</p>
                        <p class="text-xs text-white/40 mt-1">Tiendas activas</p>
                    </div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-white">{{ number_format($totalProducts) }}+</p>
                        <p class="text-xs text-white/40 mt-1">Productos publicados</p>
                    </div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-tribio-gold">S/. 29.90</p>
                        <p class="text-xs text-white/40 mt-1">Desde al mes</p>
                    </div>
                </div>
            </div>

            {{-- Right: Mock store preview --}}
            <div class="relative animate-slide-up animation-delay-200 hidden lg:block">
                <div class="relative animate-float">
                    {{-- Glow effect --}}
                    <div class="absolute inset-0 bg-gradient-to-r from-tribio-purple/30 to-tribio-pink/20 rounded-3xl blur-2xl scale-105"></div>

                    {{-- Mock store card --}}
                    <div class="relative glass-card p-6 max-w-sm mx-auto">
                        {{-- Fake browser bar --}}
                        <div class="flex items-center gap-2 mb-4 pb-3 border-b border-white/10">
                            <div class="flex gap-1.5">
                                <div class="w-3 h-3 rounded-full bg-red-400/70"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-400/70"></div>
                                <div class="w-3 h-3 rounded-full bg-green-400/70"></div>
                            </div>
                            <div class="flex-1 bg-white/10 rounded-md px-3 py-1 text-xs text-white/40 text-center">
                                tribio.pe/tienda/<span class="text-tribio-cyan">mi-negocio</span>
                            </div>
                        </div>

                        {{-- Logo space --}}
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-tribio-gold to-tribio-pink flex items-center justify-center text-2xl shadow-lg">
                                🛍️
                            </div>
                            <div>
                                <p class="text-white font-bold text-sm">Mi Tienda Online</p>
                                <p class="text-white/40 text-xs">⭐⭐⭐⭐⭐ 4.9</p>
                            </div>
                            <div class="ml-auto">
                                <span class="badge badge-green">● Activo</span>
                            </div>
                        </div>

                        {{-- Mini products --}}
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            @php
                                $mockProducts = [
                                    ['emoji' => '👟', 'name' => 'Zapatillas Nike', 'price' => '49.99', 'old' => '79.99'],
                                    ['emoji' => '⌚', 'name' => 'Reloj Moderno', 'price' => '79.00', 'old' => null],
                                    ['emoji' => '👜', 'name' => 'Bolso Elegante', 'price' => '45.00', 'old' => null],
                                    ['emoji' => '📷', 'name' => 'Cámara DSLR', 'price' => '299.00', 'old' => '350.00'],
                                ];
                            @endphp
                            @foreach($mockProducts as $p)
                            <div class="bg-white/5 rounded-xl p-3 border border-white/5 hover:border-tribio-purple/30 transition-colors group">
                                <div class="text-2xl mb-2">{{ $p['emoji'] }}</div>
                                <p class="text-white text-xs font-medium leading-tight">{{ $p['name'] }}</p>
                                <div class="flex items-center gap-1 mt-2">
                                    <span class="text-tribio-gold text-xs font-bold">S/. {{ $p['price'] }}</span>
                                    @if($p['old'])
                                        <span class="text-white/30 text-xs line-through">{{ $p['old'] }}</span>
                                    @endif
                                </div>
                                <button class="mt-2 w-full text-xs py-1.5 bg-tribio-purple/30 text-tribio-cyan rounded-lg hover:bg-tribio-purple/50 transition-colors font-medium">
                                    + Carrito
                                </button>
                            </div>
                            @endforeach
                        </div>

                        {{-- WhatsApp CTA --}}
                        <button class="w-full py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-green-500 text-white text-xs font-bold flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                            Pedir por WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Wave bottom --}}
    <div class="absolute bottom-0 left-0 right-0">
        <svg viewBox="0 0 1440 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 80L1440 80L1440 40C1200 0 900 80 720 40C540 0 240 80 0 40L0 80Z" fill="#0F0F1A"/>
        </svg>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     BÚSQUEDA PÚBLICA
     ═══════════════════════════════════════════════════════════ --}}
<section class="py-16 bg-[#0F0F1A]">
    <div class="container-tribio">
        <div class="max-w-2xl mx-auto">
            <p class="text-center text-white/50 text-sm font-medium mb-4 uppercase tracking-widest">Buscar negocios</p>
            <form action="{{ route('search') }}" method="GET">
                <div class="relative flex items-center">
                    <svg class="absolute left-5 w-5 h-5 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" placeholder="Busca zapatillas, tortas, joyería…"
                           class="w-full pl-14 pr-36 py-5 bg-tribio-card border border-tribio-border rounded-2xl text-white placeholder-white/30 focus:outline-none focus:border-tribio-purple/50 focus:ring-2 focus:ring-tribio-purple/20 text-base"
                           style="font-family: 'Outfit', sans-serif;">
                    <button type="submit"
                            class="absolute right-2 btn-primary py-3 px-6 text-sm">
                        Buscar
                    </button>
                </div>
            </form>

            {{-- Categorías rápidas --}}
            <div class="flex flex-wrap gap-2 mt-5 justify-center">
                @foreach(config('tribio.business_categories') as $key => $cat)
                <a href="{{ route('search', ['categoria' => $key]) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-medium text-white/60 border border-white/10 hover:border-tribio-purple/40 hover:text-white hover:bg-tribio-purple/10 transition-all">
                    {{ $cat['icon'] }} {{ $cat['label'] }}
                </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     TIENDAS DESTACADAS
     ═══════════════════════════════════════════════════════════ --}}
@if($featuredStores->count())
<section class="py-20 bg-[#0F0F1A]">
    <div class="container-tribio">
        <div class="text-center mb-14">
            <span class="badge badge-gold mb-4">⭐ Destacados</span>
            <h2 class="section-title text-white mt-3">Tiendas <span class="gradient-text">Populares</span></h2>
            <p class="section-subtitle mt-4">Descubre negocios exitosos en la plataforma.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($featuredStores as $store)
            <a href="{{ route('store.show', $store->slug) }}" class="store-card group">
                {{-- Cover --}}
                <div class="h-40 relative overflow-hidden bg-gradient-to-br from-tribio-purple/30 to-tribio-pink/20">
                    @if($store->cover_path)
                        <img src="{{ $store->cover_url }}" alt="{{ $store->name }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-6xl opacity-30">
                            {{ config('tribio.business_categories')[$store->category]['icon'] ?? '🛍️' }}
                        </div>
                    @endif
                    {{-- Badge featured --}}
                    <div class="absolute top-3 right-3">
                        <span class="badge badge-gold">⭐ Destacado</span>
                    </div>
                </div>

                {{-- Info --}}
                <div class="p-5">
                    <div class="flex items-start gap-3">
                        {{-- Logo --}}
                        <div class="w-12 h-12 rounded-xl overflow-hidden border-2 border-white/10 flex-shrink-0 -mt-8 relative z-10 bg-tribio-dark">
                            @if($store->logo_path)
                                <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-xl">
                                    {{ substr($store->name, 0, 1) }}
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0 pt-1">
                            <h3 class="text-white font-bold text-base truncate">{{ $store->name }}</h3>
                            <p class="text-white/40 text-xs mt-0.5">
                                {{ config('tribio.business_categories')[$store->category]['icon'] ?? '' }}
                                {{ config('tribio.business_categories')[$store->category]['label'] ?? $store->category }}
                            </p>
                        </div>
                    </div>

                    @if($store->description)
                    <p class="text-white/50 text-sm mt-3 leading-relaxed line-clamp-2">
                        {{ $store->description }}
                    </p>
                    @endif

                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-white/5">
                        <span class="text-white/40 text-xs">{{ $store->active_products_count }} productos</span>
                        <span class="text-tribio-cyan text-xs font-semibold group-hover:gap-2 transition-all">Ver tienda →</span>
                    </div>
                </div>
            </a>
            @endforeach
        </div>

        <div class="text-center mt-12">
            <a href="{{ route('directory') }}" class="btn-secondary btn-primary-lg">Ver todos los negocios →</a>
        </div>
    </div>
</section>
@endif

{{-- ═══════════════════════════════════════════════════════════
     ¿CÓMO FUNCIONA?
     ═══════════════════════════════════════════════════════════ --}}
<section id="como-funciona" class="py-24" style="background: linear-gradient(180deg, #0F0F1A 0%, #131325 100%);">
    <div class="container-tribio">
        <div class="text-center mb-16">
            <span class="badge badge-purple mb-4">💡 Simple y rápido</span>
            <h2 class="section-title text-white mt-3">¿Cómo <span class="gradient-text">Empezar</span>?</h2>
            <p class="section-subtitle mt-4">En 3 pasos tienes tu tienda online lista para vender.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative">
            {{-- Line connector --}}
            <div class="hidden md:block absolute top-16 left-1/4 right-1/4 h-0.5 bg-gradient-to-r from-tribio-purple/20 via-tribio-purple/60 to-tribio-purple/20"></div>

            @php
            $steps = [
                ['number' => '01', 'icon' => '📝', 'title' => 'Regístrate', 'desc' => 'Crea tu cuenta en minutos. Solo necesitas tu nombre, correo y los datos básicos de tu negocio.'],
                ['number' => '02', 'icon' => '🎨', 'title' => 'Elige tu diseño', 'desc' => 'Selecciona una plantilla de tienda elegante y personalízala con los colores de tu marca.'],
                ['number' => '03', 'icon' => '🚀', 'title' => 'Empieza a vender', 'desc' => 'Sube tus productos, comparte tu enlace y recibe pedidos directamente en tu WhatsApp.'],
            ];
            @endphp

            @foreach($steps as $step)
            <div class="relative glass-card p-8 text-center group hover:border-tribio-purple/30 transition-all">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-tribio-purple/20 to-tribio-pink/10 border border-tribio-purple/20 flex items-center justify-center text-3xl mx-auto mb-5 group-hover:scale-110 transition-transform">
                    {{ $step['icon'] }}
                </div>
                <span class="text-tribio-purple/50 text-xs font-bold uppercase tracking-widest">Paso {{ $step['number'] }}</span>
                <h3 class="text-white text-xl font-bold mt-2 mb-3">{{ $step['title'] }}</h3>
                <p class="text-white/50 text-sm leading-relaxed">{{ $step['desc'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Features --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-16">
            @php
            $features = [
                ['icon' => '📱', 'label' => 'Mostrar productos'],
                ['icon' => '💬', 'label' => 'Pedidos por WhatsApp'],
                ['icon' => '📊', 'label' => 'Control de inventario'],
                ['icon' => '🔗', 'label' => 'Compartir tu link'],
            ];
            @endphp
            @foreach($features as $f)
            <div class="glass-card-light p-5 text-center">
                <span class="text-3xl mb-3 block">{{ $f['icon'] }}</span>
                <p class="text-white/70 text-sm font-medium">{{ $f['label'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     PRECIOS
     ═══════════════════════════════════════════════════════════ --}}
<section id="precios" class="py-24 bg-[#0F0F1A]">
    <div class="container-tribio">
        <div class="text-center mb-16">
            <span class="badge badge-gold mb-4">💰 Inversión inteligente</span>
            <h2 class="section-title text-white mt-3">Planes y <span class="gradient-text-gold">Precios</span></h2>
            <p class="section-subtitle mt-4">Elige el plan perfecto para tu negocio. Sin contratos, cancela cuando quieras.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
            @foreach(config('tribio.plans') as $key => $plan)
            <div class="glass-card p-8 relative {{ $plan['highlight'] ? 'border-tribio-purple/50 ring-1 ring-tribio-purple/30 scale-105' : '' }}">
                @if($plan['highlight'])
                <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                    <span class="px-4 py-1 rounded-full text-xs font-bold bg-gradient-to-r from-tribio-purple to-tribio-pink text-white shadow-lg">
                        ⚡ Más popular
                    </span>
                </div>
                @endif

                <div class="mb-6">
                    <h3 class="text-white text-xl font-bold mb-1">{{ $plan['label'] }}</h3>
                    <div class="flex items-end gap-1 mt-4">
                        <span class="text-white/50 text-sm">S/.</span>
                        <span class="text-5xl font-black {{ $plan['highlight'] ? 'gradient-text' : 'text-white' }}">
                            {{ number_format($plan['price'], 2) }}
                        </span>
                        <span class="text-white/50 text-sm mb-1">/mes</span>
                    </div>
                </div>

                <ul class="space-y-3 mb-8">
                    @foreach($plan['features'] as $feat)
                    <li class="flex items-center gap-3 text-sm text-white/70">
                        <span class="text-green-400 flex-shrink-0">✓</span>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>

                <a href="{{ route('register') }}"
                   class="{{ $plan['highlight'] ? 'btn-primary' : 'btn-secondary' }} w-full justify-center">
                    Comenzar ahora
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     CTA FINAL
     ═══════════════════════════════════════════════════════════ --}}
<section class="py-24" style="background: linear-gradient(135deg, rgba(124,58,237,0.15) 0%, rgba(236,72,153,0.08) 100%), #0F0F1A;">
    <div class="container-tribio text-center">
        <div class="max-w-3xl mx-auto">
            <p class="text-tribio-cyan font-semibold mb-4 uppercase tracking-widest text-sm">¿Listo para comenzar?</p>
            <h2 class="section-title text-white mb-6">
                Tu negocio merece<br>estar en <span class="gradient-text">internet</span>
            </h2>
            <p class="section-subtitle mb-10">
                Únete a los emprendedores que ya venden las 24 horas del día con Tribio. El primer mes es tuyo para probarlo.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-gold btn-primary-lg">
                    🚀 Crear mi tienda ahora
                </a>
                <a href="https://wa.me/51902699916?text=Hola! Quiero información sobre Tribio" target="_blank" class="btn-whatsapp" style="font-size:1rem; padding:0.875rem 2rem; border-radius:1rem;">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    Escribir por WhatsApp
                </a>
            </div>
            <p class="text-white/30 text-sm mt-6">📞 +51 902 699 916 • Sin compromisos</p>
        </div>
    </div>
</section>

@endsection
