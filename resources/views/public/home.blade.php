@extends('layouts.public')

@section('title', 'Crear Tienda Online Gratis en Perú | Tribio')
@section('meta_description', 'Crea tu tienda online gratis con dominio propio en Perú. La mejor plataforma para crear tu tienda virtual en WhatsApp y vender sin comisiones.')

@section('content'){{-- ═══════════════════════════════════════════════════════════
     HERO SECTION (Shopify inspired, light theme)
     ═══════════════════════════════════════════════════════════ --}}
<section class="relative bg-[#F6F6F6] overflow-hidden pt-28 pb-12 lg:pt-36 lg:pb-12">

    <div class="container-tribio relative z-10 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

            {{-- Left: Text & Shopify-Style Form --}}
            <div class="text-left animate-slide-up">

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 tracking-tight leading-tight mb-6">
                    La plataforma para<br>
                    crear tu tienda online
                </h1>

                <p class="text-slate-500 text-lg leading-relaxed mb-10 max-w-lg">
                    Tribio es la mejor app para <strong class="text-slate-800">crear tu tienda virtual gratis en Perú</strong> y vender por WhatsApp sin pagar comisiones por ventas. Publica tus productos y recibe pedidos listos directamente en tu celular.
                </p>

                {{-- Shopify-Style Registration Form --}}
                <form action="{{ route('register') }}" method="GET" class="flex flex-col sm:flex-row gap-3 max-w-lg">
                    <input type="email" name="email" placeholder="Ingresa tu correo electrónico" required
                           class="flex-1 px-5 py-4 bg-white border border-slate-200 rounded-full text-slate-900 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-base transition-all">
                    <button type="submit" class="py-4 px-8 text-base rounded-full bg-slate-900 text-white font-bold hover:bg-slate-800 transition shadow-lg shadow-slate-950/10">
                        Probar gratis
                    </button>
                </form>

                <p class="text-xs text-slate-550 mt-4 leading-relaxed flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span>Prueba gratis de 30 días</span>
                    <span class="text-slate-300">•</span>
                    <span>Precios desde <strong>S/. 29.90 al mes</strong></span>
                    <span class="text-slate-300">•</span>
                    <span>Sin contratos ni plazos forzosos</span>
                </p>
            </div>

            {{-- Right: Mock store preview (Shopify juice design) --}}
            <div class="relative animate-slide-up animation-delay-200 mt-12 lg:mt-0">
                <div class="relative animate-float">
                    {{-- Browser Frame Mockup --}}
                    <div class="relative bg-white border border-slate-200 rounded-3xl p-4 sm:p-6 shadow-xl shadow-slate-200/50 max-w-[340px] sm:max-w-sm mx-auto">
                        {{-- Browser controls --}}
                        <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
                            <div class="flex gap-1.5">
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-200"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-200"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-slate-200"></div>
                            </div>
                            <div class="flex-1 bg-slate-50 border border-slate-100 rounded-lg py-1 px-3 text-[10px] text-slate-400 text-center font-mono">
                                tribio.pe/tienda/<span class="text-sky-500 font-semibold">mi-negocio</span>
                            </div>
                        </div>

                        {{-- Store Header --}}
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 flex items-center justify-center text-lg text-white font-bold shadow-md shadow-sky-500/10">
                                🌿
                            </div>
                            <div>
                                <p class="text-slate-800 font-extrabold text-sm leading-tight">Organic Market</p>
                                <p class="text-slate-400 text-[10px] mt-0.5">⭐⭐⭐⭐⭐ 4.9 • Delivery gratis</p>
                            </div>
                            <div class="ml-auto">
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-green-50 text-green-600 border border-green-100 uppercase tracking-wider">● Abierto</span>
                            </div>
                        </div>

                        {{-- Product Card Mockup --}}
                        <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 mb-4">
                            <div class="w-full h-32 bg-gradient-to-br from-amber-100 to-orange-100 rounded-xl flex items-center justify-center text-5xl mb-3 shadow-inner">
                                🥤
                            </div>
                            <p class="text-slate-800 text-xs font-bold">Jugo Natural Naranja</p>
                            <p class="text-slate-400 text-[10px] mt-1">100% orgánico, sin azúcar añadida.</p>
                            
                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-200/50">
                                <div>
                                    <span class="text-slate-400 text-[9px] block">Precio</span>
                                    <span class="text-slate-900 text-sm font-black">S/. 12.90</span>
                                </div>
                                {{-- Quantity Selector Mockup --}}
                                <div class="flex items-center border border-slate-200 rounded-lg bg-white overflow-hidden text-xs">
                                    <button type="button" class="px-2 py-1 text-slate-400 hover:bg-slate-50">-</button>
                                    <span class="px-2 font-semibold text-slate-700">1</span>
                                    <button type="button" class="px-2 py-1 text-slate-400 hover:bg-slate-50">+</button>
                                </div>
                            </div>
                        </div>

                        {{-- WhatsApp Checkout Button --}}
                        <button type="button" class="w-full py-3 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-xs font-bold flex items-center justify-center gap-2 transition shadow-lg shadow-slate-900/10">
                            <svg class="w-4 h-4 text-sky-400" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                            Pedir por WhatsApp
                        </button>
                    </div>

                    {{-- Floating notification bubble --}}
                    <div class="absolute -bottom-4 -left-4 bg-white border border-slate-100 rounded-2xl p-3 shadow-xl hidden sm:flex items-center gap-3 animate-float" style="animation-delay: 2s;">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                            💬
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 leading-none">Pedido Nuevo</p>
                            <p class="text-[11px] text-slate-800 font-bold mt-0.5">S/. 12.90 recibido</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     ROW OF PARTNER LOGOS
     ═══════════════════════════════════════════════════════════ --}}
{{-- 
<div class="py-12 bg-[#F6F6F6] pb-16">
    <div class="container-tribio">
        <p class="text-center text-xs font-semibold uppercase tracking-widest text-slate-400 mb-6">Impulsamos a millones de negocios de todo el mundo</p>
        <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-6 opacity-65">
            <span class="text-lg font-black tracking-widest text-slate-900">GYMSHARK</span>
            <span class="text-lg font-bold tracking-widest text-slate-900">MEJURI</span>
            <span class="text-lg font-extrabold text-slate-900">KYLIE</span>
            <span class="text-lg font-mono font-bold tracking-widest text-slate-900">brooklinen</span>
            <span class="text-lg font-sans font-black tracking-tighter text-slate-900">DEATH WISH</span>
            <span class="text-lg font-serif font-black tracking-wider text-slate-900">UNTUCKIt</span>
        </div>
    </div>
</div>
--}}

{{-- ═══════════════════════════════════════════════════════════
     BLACK CONTAINER WITH ROUNDED CORNERS (Shopify inspired)
     ═══════════════════════════════════════════════════════════ --}}
<section class="rounded-t-[3rem] md:rounded-t-[4rem] bg-[#000000] py-24 text-white overflow-hidden">
    <div class="container-tribio">
        <div class="text-left mb-16 max-w-2xl" data-animate>
            <h2 class="text-4xl sm:text-5xl font-extrabold text-white leading-tight">Todo lo que necesitas<br>para vender en línea</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            {{-- Card 1 --}}
            <div class="bg-[#111111] border border-white/5 rounded-3xl p-8 flex flex-col justify-between shadow-2xl transition-all duration-300 hover:border-white/10 hover:-translate-y-1 hover:scale-[1.01] hover:shadow-sky-500/5" data-animate>
                <div>
                    <h3 class="text-xl font-bold text-white mb-3">Pedidos directos a WhatsApp</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">Tus clientes eligen sus productos y te envían el pedido detallado en un solo clic, listo para coordinar la entrega y el pago.</p>
                </div>
                <div class="mt-8">
                    <img src="{{ asset('images/checkout_mockup.png') }}" alt="Pedidos directos a WhatsApp" class="w-full h-auto rounded-2xl border border-white/5 shadow-lg">
                </div>
            </div>

            {{-- Card 2 --}}
            <div class="bg-[#111111] border border-white/5 rounded-3xl p-8 flex flex-col justify-between shadow-2xl transition-all duration-300 hover:border-white/10 hover:-translate-y-1 hover:scale-[1.01] hover:shadow-sky-500/5 animation-delay-200" data-animate>
                <div>
                    <h3 class="text-xl font-bold text-white mb-3">Control de inventario simple</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">Lleva el control de tus existencias en tiempo real, agrega variaciones de productos y evita vender sin stock.</p>
                </div>
                <div class="mt-8">
                    <img src="{{ asset('images/inventory_mockup.png') }}" alt="Control de inventario simple" class="w-full h-auto rounded-2xl border border-white/5 shadow-lg">
                </div>
            </div>

            {{-- Card 3 --}}
            <div class="bg-[#111111] border border-white/5 rounded-3xl p-8 flex flex-col justify-between shadow-2xl transition-all duration-300 hover:border-white/10 hover:-translate-y-1 hover:scale-[1.01] hover:shadow-sky-500/5 animation-delay-400" data-animate>
                <div>
                    <h3 class="text-xl font-bold text-white mb-3">Catálogo digital 24/7</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">Tu tienda virtual abierta de día y de noche con un 99.9% de disponibilidad, optimizada para cargar al instante.</p>
                </div>
                <div class="mt-8">
                    <img src="{{ asset('images/uptime_mockup.png') }}" alt="Catálogo digital 24/7" class="w-full h-auto rounded-2xl border border-white/5 shadow-lg">
                </div>
            </div>

        </div>

        {{-- Quote --}}
        <div class="mt-20 text-center max-w-4xl mx-auto border-t border-white/5 pt-12">
            <p class="text-lg sm:text-xl font-medium text-slate-350 italic leading-relaxed">
                “ Todos los planes de Tribio incluyen almacenamiento y publicación de productos sin límites, lo que significa que tu negocio escala a la perfección a medida que crece. ”
            </p>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     BÚSQUEDA PÚBLICA (Capsule style, inside button)
     ═══════════════════════════════════════════════════════════ --}}
<section class="pt-12 pb-8 bg-white border-b border-slate-100">
    <div class="container-tribio">
        <div class="max-w-3xl mx-auto" data-animate>
            <p class="text-center text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">Buscar negocios en Tribio</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 text-center mb-8 leading-tight">Encuentra comercios locales</h2>
            
            <form action="{{ route('search') }}" method="GET">
                <div class="relative flex items-center bg-white border border-slate-200 rounded-full p-2 pl-6 shadow-sm focus-within:border-sky-500 focus-within:ring-2 focus-within:ring-sky-100 transition-all">
                    <svg class="w-5 h-5 text-slate-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" name="q" placeholder="Busca zapatillas, postres, joyería, moda..."
                           class="w-full bg-transparent border-0 text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0 text-base py-3 pr-2"
                           style="font-family: 'Plus Jakarta Sans', sans-serif;">
                    <button type="submit"
                            class="py-3.5 px-8 text-sm rounded-full bg-slate-900 text-white font-bold hover:bg-slate-800 transition flex-shrink-0 shadow-md">
                        Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     TIENDAS DESTACADAS (Light Minimal design)
     ═══════════════════════════════════════════════════════════ --}}
@if($featuredStores->count())
<section class="pt-10 pb-16 bg-white">
    <div class="container-tribio">
        <div class="text-left mb-8 max-w-3xl" data-animate>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 leading-tight">Tiendas populares en la plataforma</h2>
            <p class="text-slate-500 text-sm mt-3">Descubre marcas exitosas y comercios que ya están vendiendo en línea.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($featuredStores as $store)
            <a href="{{ route('store.show', $store->slug) }}" class="store-card group rounded-3xl overflow-hidden flex flex-col justify-between {{ $loop->index % 3 == 1 ? 'animation-delay-200' : ($loop->index % 3 == 2 ? 'animation-delay-400' : '') }}" data-animate>
                {{-- Cover --}}
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

        <div class="text-center mt-14">
            <a href="{{ route('directory') }}" class="btn-secondary py-3 px-8 text-sm">Ver todos los negocios →</a>
        </div>
    </div>
</section>
@endif

{{-- ═══════════════════════════════════════════════════════════
     ¿CÓMO FUNCIONA? (2-column split with builder mockup)
     ═══════════════════════════════════════════════════════════ --}}
<section id="como-funciona" class="py-24 bg-slate-50 border-t border-b border-slate-100">
    <div class="container-tribio">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            
            {{-- Left: Steps --}}
            <div data-animate>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-10 leading-tight">Crea tu tienda virtual en tres simples pasos</h2>
                
                <div class="space-y-8">
                    {{-- Step 1 --}}
                    <div class="flex gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-base font-bold text-slate-800 flex-shrink-0 shadow-sm">
                            1
                        </div>
                        <div>
                            <h3 class="text-slate-900 text-lg font-bold mb-1">Regístrate en la plataforma</h3>
                            <p class="text-slate-500 text-xs leading-relaxed">Crea tu cuenta en un minuto. Solo ingresa tu correo, nombre y los datos básicos de tu negocio.</p>
                        </div>
                    </div>
                    
                    {{-- Step 2 --}}
                    <div class="flex gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-base font-bold text-slate-800 flex-shrink-0 shadow-sm">
                            2
                        </div>
                        <div>
                            <h3 class="text-slate-900 text-lg font-bold mb-1">Personaliza tu diseño</h3>
                            <p class="text-slate-500 text-xs leading-relaxed">Sube tu logotipo, elige tus colores de acento y selecciona el estilo visual que más se adapte a tu marca.</p>
                        </div>
                    </div>

                    {{-- Step 3 --}}
                    <div class="flex gap-5">
                        <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-base font-bold text-slate-800 flex-shrink-0 shadow-sm">
                            3
                        </div>
                        <div>
                            <h3 class="text-slate-900 text-lg font-bold mb-1">Sube tus productos y vende</h3>
                            <p class="text-slate-500 text-xs leading-relaxed">Publica tus artículos con fotos y precios, comparte tu enlace y empieza a recibir pedidos directamente en WhatsApp.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Right: Dashboard Builder Mockup Image --}}
            <div class="relative animation-delay-200" data-animate>
                <div class="absolute inset-0 bg-gradient-to-r from-sky-400/5 to-blue-500/5 rounded-[2rem] blur-2xl scale-105"></div>
                <img src="{{ asset('images/store_builder_mockup.png') }}" alt="Panel de personalización de Tribio" class="w-full h-auto rounded-3xl border border-slate-200 shadow-xl bg-white">
            </div>
            
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     PRECIOS (Light minimalistic cards)
     ═══════════════════════════════════════════════════════════ --}}
<section id="precios" class="py-24 bg-white" 
         x-data="{
             openModal: false,
             selectedPlan: '',
             selectedPlanLabel: '',
             selectedPlanPrice: '',
             selectedPlanFeatures: [],
             
             openPlanModal(key, label, price, features) {
                 this.selectedPlan = key;
                 this.selectedPlanLabel = label;
                 this.selectedPlanPrice = price;
                 this.selectedPlanFeatures = features;
                 this.openModal = true;
             }
         }"
         x-init="
             @if($errors->any() && old('plan_key'))
                 openPlanModal(
                     '{{ old('plan_key') }}',
                     '{{ config('tribio.plans.' . old('plan_key') . '.label') }}',
                     '{{ number_format(config('tribio.plans.' . old('plan_key') . '.price'), 2) }}',
                     {!! json_encode(config('tribio.plans.' . old('plan_key') . '.features')) !!}
                 );
             @endif
         ">
    <div class="container-tribio">
        <div class="text-center mb-16" data-animate>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900">Planes diseñados para crecer</h2>
            <p class="text-slate-500 text-sm mt-3">Sin contratos forzosos. Prueba gratis el primer mes y cambia de plan cuando quieras.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto items-stretch">
            @foreach(config('tribio.plans') as $key => $plan)
            <div class="bg-white border rounded-3xl p-8 flex flex-col justify-between relative transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl {{ $plan['highlight'] ? 'border-sky-400 ring-4 ring-sky-50 shadow-xl hover:scale-[1.03]' : 'border-slate-100 shadow-sm' }} {{ $loop->index == 1 ? 'animation-delay-200' : ($loop->index == 2 ? 'animation-delay-400' : '') }}" data-animate>
                @if($plan['highlight'])
                <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold bg-sky-500 text-white uppercase tracking-wider">
                        ⚡ Recomendado
                    </span>
                </div>
                @endif

                <div>
                    <h3 class="text-slate-900 text-lg font-extrabold mb-1">{{ $plan['label'] }}</h3>
                    
                    <div class="flex items-end gap-1 mt-5">
                        <span class="text-slate-400 text-sm font-bold">S/.</span>
                        <span class="text-5xl font-black text-slate-900 leading-none">
                            {{ number_format($plan['price'], 2) }}
                        </span>
                        <span class="text-slate-400 text-xs font-bold mb-1">/mes</span>
                    </div>
                    
                    <ul class="space-y-3.5 mt-8 border-t border-slate-100 pt-6">
                        @foreach($plan['features'] as $feat)
                        <li class="flex items-start gap-3 text-xs text-slate-600">
                            <span class="text-sky-500 font-bold text-sm">✓</span>
                            <span class="pt-0.5">{{ $feat }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-8">
                    <button type="button" 
                            @click="openPlanModal('{{ $key }}', '{{ $plan['label'] }}', '{{ number_format($plan['price'], 2) }}', {{ json_encode($plan['features']) }})"
                            class="{{ $plan['highlight'] ? 'btn-primary' : 'btn-secondary' }} w-full justify-center py-3 text-sm">
                        Comenzar ahora
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Pricing Checkout Modal -->
    <div x-show="openModal"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
         
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="openModal = false"></div>

        <!-- Modal Wrapper -->
        <div class="flex min-h-screen items-center justify-center p-4 text-center">
            <div class="relative w-full max-w-2xl transform overflow-hidden rounded-3xl bg-white p-6 sm:p-10 text-left shadow-2xl transition-all border border-slate-100"
                 @click.stop>
                
                <!-- Close Button -->
                <button type="button" @click="openModal = false" class="absolute top-6 right-6 text-slate-400 hover:text-slate-500 text-sm">
                    ✕
                </button>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                    <!-- Left: Plan Summary -->
                    <div class="md:col-span-5 bg-slate-50 rounded-2xl p-6 border border-slate-100 flex flex-col justify-between">
                        <div>
                            <span class="text-[9px] font-black text-sky-500 uppercase tracking-widest block mb-1">PLAN SELECCIONADO</span>
                            <h3 class="text-xl font-extrabold text-slate-900 leading-tight uppercase" x-text="selectedPlanLabel"></h3>
                            
                            <div class="flex items-end gap-1 mt-4">
                                <span class="text-slate-400 text-xs font-bold">S/.</span>
                                <span class="text-3xl font-black text-slate-900 leading-none" x-text="selectedPlanPrice"></span>
                                <span class="text-slate-400 text-[10px] font-bold mb-0.5">/mes</span>
                            </div>

                            <ul class="space-y-2.5 mt-6 border-t border-slate-200/60 pt-4 text-[11px] text-slate-600 font-medium">
                                <template x-for="feat in selectedPlanFeatures">
                                    <li class="flex items-start gap-2">
                                        <span class="text-sky-500 font-bold">✓</span>
                                        <span x-text="feat" class="pt-0.5"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <!-- Trust Seal -->
                        <div class="mt-6 border-t border-slate-200/60 pt-4 flex items-center gap-2 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span>Pago Seguro vía Mercado Pago</span>
                        </div>
                    </div>

                    <!-- Right: Registration Form -->
                    <div class="md:col-span-7 space-y-4">
                        <div class="border-b border-slate-100 pb-3">
                            <h3 class="text-base font-extrabold text-slate-900">Configura tu Cuenta y Tienda</h3>
                            <p class="text-xs text-slate-400">Ingresa tus datos de acceso y la dirección que tendrá tu tienda virtual.</p>
                        </div>

                        <form method="POST" action="{{ route('plan.checkout') }}" class="space-y-3.5">
                            @csrf
                            <input type="hidden" name="plan_key" :value="selectedPlan">

                            <!-- Laravel Errors Display in Modal -->
                            @if($errors->any())
                                <div class="p-3.5 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-600 text-xs space-y-1">
                                    @foreach($errors->all() as $e)
                                        <p>• {{ $e }}</p>
                                    @endforeach
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre Completo *</label>
                                    <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Ej: Juan Pérez">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Correo Electrónico *</label>
                                    <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="juan@correo.com">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Contraseña *</label>
                                    <input type="password" name="password" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Mínimo 8 caracteres">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Confirmar Contraseña *</label>
                                    <input type="password" name="password_confirmation" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Repite la contraseña">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre de Tienda *</label>
                                    <input type="text" name="store_name" value="{{ old('store_name') }}" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Ej: Repuestos El Sol">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Celular / WhatsApp *</label>
                                    <input type="text" name="whatsapp_phone" value="{{ old('whatsapp_phone') }}" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Ej: 987654321">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Dirección URL de tu Tienda *</label>
                                    <div class="flex items-center">
                                        <span class="text-[10px] text-slate-400 font-bold bg-slate-50 border border-r-0 border-slate-200 rounded-l-xl px-2.5 py-2.5">tribio.pe/</span>
                                        <input type="text" name="store_slug" value="{{ old('store_slug') }}" required class="w-full px-3 py-2 text-xs rounded-r-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900 font-semibold" placeholder="mi-tienda">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Categoría del Negocio *</label>
                                    <select name="store_category" required class="w-full px-3 py-2.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900 bg-white">
                                        <option value="">Seleccionar...</option>
                                        @foreach(config('tribio.business_categories') as $key => $cat)
                                            <option value="{{ $key }}" {{ old('store_category') === $key ? 'selected' : '' }}>{{ $cat['icon'] }} {{ $cat['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <button type="submit"
                                    class="w-full py-3.5 px-4 font-bold bg-sky-500 hover:bg-sky-600 text-white text-xs uppercase tracking-wider rounded-xl transition-all shadow-md shadow-sky-500/10 flex items-center justify-center gap-2.5 mt-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                Proceder al Pago Seguro con Mercado Pago
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     PREGUNTAS FRECUENTES (FAQ Accordions)
     ═══════════════════════════════════════════════════════════ --}}
<section class="py-20 bg-slate-50 border-t border-b border-slate-100">
    <div class="container-tribio max-w-4xl mx-auto">
        <div class="text-center mb-12" data-animate>
            <span class="text-sky-500 text-xs font-bold uppercase tracking-widest">Resuelve tus dudas</span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 mt-2">Preguntas Frecuentes</h2>
            <p class="text-slate-500 text-sm mt-3">Todo lo que necesitas saber sobre cómo crear una tienda virtual en WhatsApp y vender sin complicaciones.</p>
        </div>

        <div class="bg-white rounded-3xl p-6 sm:p-10 border border-slate-100 shadow-sm" data-animate>
            
            {{-- Q1 --}}
            <div class="border-b border-slate-100 pb-6" x-data="{ open: false }">
                <button class="w-full flex items-center justify-between text-left focus:outline-none group" @click="open = !open">
                    <span class="text-base sm:text-lg font-bold text-slate-900 group-hover:text-sky-500 transition-colors">¿Cómo puedo crear mi propio e-commerce con Tribio?</span>
                    <span class="ml-4 flex-shrink-0 text-slate-400 transform transition-transform duration-300" :class="open ? 'rotate-45 text-sky-500' : ''">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                </button>
                <div class="mt-4 text-xs sm:text-sm text-slate-550 leading-relaxed" x-show="open" x-collapse>
                    <p>Crear tu propio e-commerce es sumamente sencillo con Tribio. Solo necesitas registrar tu correo electrónico, elegir un nombre y dirección web para tu tienda (ej: tribio.pe/tu-tienda) y configurar tu número de WhatsApp. Luego, puedes subir tus productos con fotos, precios y descripciones de forma inmediata. Estarás vendiendo en minutos.</p>
                </div>
            </div>

            {{-- Q2 --}}
            <div class="border-b border-slate-100 py-6" x-data="{ open: false }">
                <button class="w-full flex items-center justify-between text-left focus:outline-none group" @click="open = !open">
                    <span class="text-base sm:text-lg font-bold text-slate-900 group-hover:text-sky-500 transition-colors">¿Qué se necesita para crear una tienda online?</span>
                    <span class="ml-4 flex-shrink-0 text-slate-400 transform transition-transform duration-300" :class="open ? 'rotate-45 text-sky-500' : ''">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                </button>
                <div class="mt-4 text-xs sm:text-sm text-slate-550 leading-relaxed" x-show="open" x-collapse>
                    <p>Para empezar no requieres de conocimientos técnicos, hosting ni servidores complejos. Únicamente necesitas un teléfono celular para gestionar tus pedidos, las imágenes y descripciones de los artículos que deseas ofrecer, y un número de WhatsApp activo. No solicitamos tarjetas de crédito ni contratos forzosos para iniciar tu prueba gratuita.</p>
                </div>
            </div>

            {{-- Q3 --}}
            <div class="border-b border-slate-100 py-6" x-data="{ open: false }">
                <button class="w-full flex items-center justify-between text-left focus:outline-none group" @click="open = !open">
                    <span class="text-base sm:text-lg font-bold text-slate-900 group-hover:text-sky-500 transition-colors">¿Cómo hacer e-commerce en Perú de forma efectiva?</span>
                    <span class="ml-4 flex-shrink-0 text-slate-400 transform transition-transform duration-300" :class="open ? 'rotate-45 text-sky-500' : ''">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                </button>
                <div class="mt-4 text-xs sm:text-sm text-slate-550 leading-relaxed" x-show="open" x-collapse>
                    <p>El mercado peruano valora la inmediatez y los medios de pago sencillos. Tribio está optimizado localmente para que puedas recibir pagos mediante Yape, Plin y transferencia bancaria directa sin intermediarios. Tus clientes agregan productos a su carrito digital y la plataforma les genera un mensaje automatizado con el pedido detallado que se envía a tu WhatsApp, permitiéndote cerrar la venta y coordinar el delivery de forma directa y personalizada.</p>
                </div>
            </div>

            {{-- Q4 --}}
            <div class="pt-6" x-data="{ open: false }">
                <button class="w-full flex items-center justify-between text-left focus:outline-none group" @click="open = !open">
                    <span class="text-base sm:text-lg font-bold text-slate-900 group-hover:text-sky-500 transition-colors">¿Cuánto cuesta crear una tienda virtual en Perú?</span>
                    <span class="ml-4 flex-shrink-0 text-slate-400 transform transition-transform duration-300" :class="open ? 'rotate-45 text-sky-500' : ''">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                    </span>
                </button>
                <div class="mt-4 text-xs sm:text-sm text-slate-550 leading-relaxed" x-show="open" x-collapse>
                    <p>Puedes empezar a crear tu tienda online gratis y disfrutar de una prueba de 30 días con todas las funciones activas. Posteriormente, ofrecemos planes fijos desde S/. 29.90 al mes, sin comisiones por transacciones de venta ni sorpresas en tu facturación mensual. Es la alternativa ideal y más económica frente a plataformas costosas.</p>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════
     CTA FINAL (Glow in the dark blue card)
     ═══════════════════════════════════════════════════════════ --}}
<section class="py-16 bg-white">
    <div class="container-tribio">
        <div class="bg-slate-950 text-white rounded-[2.5rem] p-12 md:p-20 text-center relative overflow-hidden shadow-2xl" data-animate>
            {{-- Radial glows inside --}}
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-sky-500/20 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full bg-blue-600/10 blur-3xl pointer-events-none"></div>

            <div class="max-w-2xl mx-auto relative z-10">
                <span class="text-sky-400 text-xs font-bold uppercase tracking-widest">¿Listo para comenzar?</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white mt-4 leading-tight">
                    Lleva tu negocio<br>al siguiente nivel hoy mismo
                </h2>
                <p class="text-white/60 text-base mt-4 mb-10 leading-relaxed">
                    Únete a cientos de emprendedores que ya están vendiendo las 24 horas del día con Tribio. Es momento de digitalizar tu marca.
                </p>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="btn-primary bg-white text-slate-950 hover:bg-slate-100 py-4 px-8 text-base shadow-lg shadow-white/5 font-extrabold rounded-full">
                        🚀 Crear mi tienda ahora
                    </a>
                    <a href="https://wa.me/51902699916?text=Hola! Quiero información sobre Tribio" target="_blank"
                       class="inline-flex items-center justify-center gap-2 border border-white/20 text-white hover:bg-white/5 py-4 px-8 text-base font-bold rounded-full transition duration-200">
                        Hablar con un asesor
                    </a>
                </div>
                <p class="text-white/40 text-xs mt-6">📞 <a href="https://wa.me/51902699916" class="text-white font-bold hover:underline">+51 902 699 916</a> • Sin contratos ni plazos forzosos</p>
            </div>
        </div>
    </div>
</section>

@endsection
