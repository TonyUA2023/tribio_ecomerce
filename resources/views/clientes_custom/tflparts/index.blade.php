<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} — Everything That Makes a Tractor Move</title>
    <meta name="description" content="{{ $store->description }}">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- AlpineJS v3 (CDN) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --accent: #E50914; /* Red corporate color */
            --accent-hover: #B80710;
            --secondary: #16A34A; /* Green agricultural color */
            --secondary-hover: #15803D;
            --bg: #FFFFFF;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: #1F2937;
        }
        .btn-accent {
            background-color: var(--accent);
            color: white;
            transition: all 0.2s ease-in-out;
        }
        .btn-accent:hover {
            background-color: var(--accent-hover);
        }
        .btn-secondary {
            background-color: var(--secondary);
            color: white;
            transition: all 0.2s ease-in-out;
        }
        .btn-secondary:hover {
            background-color: var(--secondary-hover);
        }
        .text-accent {
            color: var(--accent);
        }
        .text-secondary {
            color: var(--secondary);
        }
        
        /* Hexagon background pattern */
        .hex-bg {
            background-color: #0b0b0b;
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(20,20,20,0.9), rgba(0,0,0,0.95)),
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='28' height='49' viewBox='0 0 28 49'%3E%3Cpath fill='%231f1f1f' fill-opacity='0.4' fill-rule='evenodd' d='M0 0h28v49H0V0zm14 2.5L26.5 10v15L14 32.5 1.5 25V10L14 2.5zM14 35l12.5 7.5v15L14 65 1.5 57.5v-15L14 35z'/%3E%3C/svg%3E");
            background-size: auto;
        }

        /* Subtle entrance animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* Pulsating ripple animation for floating WhatsApp button */
        @keyframes whatsapp-ripple {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.4), 0 0 0 0 rgba(37, 211, 102, 0.4);
            }
            40% {
                box-shadow: 0 0 0 8px rgba(37, 211, 102, 0), 0 0 0 0 rgba(37, 211, 102, 0.4);
            }
            80% {
                box-shadow: 0 0 0 8px rgba(37, 211, 102, 0), 0 0 0 16px rgba(37, 211, 102, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0), 0 0 0 0 rgba(37, 211, 102, 0);
            }
        }
        .whatsapp-btn {
            animation: whatsapp-ripple 2s infinite;
        }
    </style>
</head>
<body x-data="cartApp()" x-init="initCart()" class="antialiased scroll-smooth bg-white">

    @include('clientes_custom.tflparts.header')
    @if(isset($sections) && $sections->isNotEmpty())
        @foreach($sections as $section)
            @if(View::exists("components.store-sections.{$section->type}"))
                <div class="tribio-section-wrapper relative group" data-section-id="{{ $section->id }}" id="section-{{ $section->id }}">
                    <div class="tribio-section-content">
                        @include("components.store-sections.{$section->type}", ['data' => $section->data])
                    </div>
                </div>
            @endif
        @endforeach
        
        @if(request()->query('editor'))
            @include('components.store-sections.editor-scripts')
        @endif
    @else
    <!-- Slider / Hero Banner Section (Style DercoMaq) -->
    <section class="relative bg-black border-b border-gray-900 overflow-hidden select-none">
        <div class="relative w-full h-[540px] sm:h-[600px] lg:h-[680px] bg-black overflow-hidden group">
            <!-- Background Image -->
            <div class="absolute inset-0 bg-cover bg-center transition-transform duration-700 group-hover:scale-105"
                 style="background-image: linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.55)), url('{{ asset('storage/images/tfl_parts_hero.png') }}');">
            </div>

            <!-- Content Overlay -->
            <div class="absolute inset-0 flex flex-col justify-between p-8 sm:p-16 lg:p-24 text-left z-10">
                <div>
                    <span class="inline-block px-3 py-1 bg-[#E50914] text-white text-[10px] font-black uppercase tracking-widest italic rounded shadow-md">
                        NUEVA LÍNEA B2B
                    </span>
                </div>

                <!-- Title -->
                <div class="space-y-6 sm:space-y-8 max-w-3xl">
                    <h2 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold uppercase tracking-wider text-white leading-[1.15] drop-shadow-md">
                        NUEVA LÍNEA DE <br class="hidden sm:block">
                        REPUESTOS AGRÍCOLAS
                    </h2>
                    <p class="text-xs sm:text-sm font-bold uppercase tracking-widest text-gray-200 drop-shadow max-w-xl">
                        {{ $store->tagline }}
                    </p>
                </div>

                <!-- Bottom Action Button & Controls -->
                <div class="flex items-end justify-between w-full">
                    <div>
                        <a href="{{ route('store.catalog', $store->slug) }}"
                           class="inline-block px-8 py-3 bg-white hover:bg-gray-100 text-[#E50914] font-black text-xs uppercase tracking-widest transition-all rounded shadow-lg transform hover:-translate-y-0.5">
                            VER PRODUCTOS
                        </a>
                    </div>

                    <!-- Slider Controls -->
                    <div class="flex gap-2">
                        <button class="w-10 h-10 border border-white/20 hover:border-white/50 text-white flex items-center justify-center rounded-full bg-black/20 hover:bg-black/40 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <button class="w-10 h-10 border border-white/20 hover:border-white/50 text-white flex items-center justify-center rounded-full bg-black/20 hover:bg-black/40 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Features Section -->
    <section class="py-12 bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Expedited Shipping -->
                <div class="flex items-center gap-5 p-6 rounded-xl border border-gray-100 hover:border-red-500/20 hover:shadow-md hover:-translate-y-1 transition-all duration-300 group bg-gray-50/50">
                    <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-[#E50914] shrink-0 group-hover:bg-[#E50914] group-hover:text-white group-hover:scale-110 transition-all duration-300">
                        <svg class="w-6 h-6 transform group-hover:rotate-12 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 011 1v2.5a.5.5 0 01-.5.5h-2a.5.5 0 01-.5-.5V16m5 0h2a1 1 0 001-1v-4a1 1 0 00-.293-.707l-2-2A1 1 0 0016.5 8H14M14 16a2 2 0 11-4 0M6 16a2 2 0 11-4 0"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-black uppercase tracking-widest text-gray-900 group-hover:text-[#E50914] transition-colors duration-300">
                            ENVÍO RÁPIDO Y GARANTIZADO
                        </h4>
                    </div>
                </div>

                <!-- Professional Support -->
                <div class="flex items-center gap-5 p-6 rounded-xl border border-gray-100 hover:border-red-500/20 hover:shadow-md hover:-translate-y-1 transition-all duration-300 group bg-gray-50/50">
                    <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-[#E50914] shrink-0 group-hover:bg-[#E50914] group-hover:text-white group-hover:scale-110 transition-all duration-300">
                        <svg class="w-6 h-6 transform group-hover:rotate-12 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-black uppercase tracking-widest text-gray-900 group-hover:text-[#E50914] transition-colors duration-300">
                            SOPORTE PROFESIONAL B2B
                        </h4>
                    </div>
                </div>

                <!-- Convenient Payment Solutions -->
                <div class="flex items-center gap-5 p-6 rounded-xl border border-gray-100 hover:border-red-500/20 hover:shadow-md hover:-translate-y-1 transition-all duration-300 group bg-gray-50/50">
                    <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-[#E50914] shrink-0 group-hover:bg-[#E50914] group-hover:text-white group-hover:scale-110 transition-all duration-300">
                        <svg class="w-6 h-6 transform group-hover:rotate-12 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs sm:text-sm font-black uppercase tracking-widest text-gray-900 group-hover:text-[#E50914] transition-colors duration-300">
                            MÉTODOS DE PAGO SEGUROS
                        </h4>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Brands Carousel Section -->
    <section class="py-10 bg-white border-b border-gray-100 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-5 text-center">
            <span class="text-[9px] font-black uppercase tracking-widest text-[#E50914] block">MARCAS QUE DISTRIBUIMOS</span>
            <h4 class="text-xs sm:text-sm font-black uppercase tracking-widest text-gray-900 mt-1">
                Compatibilidad total con las principales maquinarias del mercado
            </h4>
        </div>
        
        <!-- Infinite Scrolling Logo Bar -->
        <div class="relative w-full flex overflow-x-hidden group bg-gray-50/30 py-6 border-y border-gray-100">
            <style>
                @keyframes marquee {
                    0% { transform: translateX(0%); }
                    100% { transform: translateX(-50%); }
                }
                .animate-marquee {
                    display: flex;
                    width: max-content;
                    animation: marquee 25s linear infinite;
                }
                .animate-marquee:hover {
                    animation-play-state: paused;
                }
            </style>
            <!-- Double the list for seamless looping -->
            <div class="animate-marquee gap-16 items-center flex select-none">
                <!-- Brand items -->
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-serif">JOHN DEERE</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-mono">PERKINS</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic">MASSEY FERGUSON</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-sans font-black">CUMMINS</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-semibold">DONALDSON</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-mono font-bold">EATON</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-serif">NEW HOLLAND</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-black">CASE IH</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-semibold">CATERPILLAR</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-serif font-black">VALTRA</span>
                </div>
                
                <!-- Repeated list for loop -->
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-serif">JOHN DEERE</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-mono">PERKINS</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic">MASSEY FERGUSON</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-sans font-black">CUMMINS</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-semibold">DONALDSON</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-mono font-bold">EATON</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-serif">NEW HOLLAND</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-black">CASE IH</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-semibold">CATERPILLAR</span>
                </div>
                <div class="flex flex-col items-center justify-center min-w-[140px] text-gray-400 hover:text-[#E50914] transition-colors duration-300">
                    <span class="text-sm sm:text-base font-extrabold tracking-widest uppercase italic font-serif font-black">VALTRA</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories / Sections (Style DercoMaq) -->
    <section class="py-16 bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Section Header with red vertical line -->
            <div class="border-l-4 border-[#E50914] pl-4 mb-10 text-left">
                <span class="text-[10px] font-black uppercase tracking-widest text-[#E50914] block mb-1">CATEGORÍAS DE REPUESTOS</span>
                <h2 class="text-xl sm:text-2xl font-black uppercase tracking-tight text-gray-900">
                    Explora Nuestras Líneas Especializadas
                </h2>
                <p class="text-xs text-gray-500 font-semibold mt-1">
                    Componentes garantizados y soporte técnico al alcance de todos.
                </p>
            </div>

            <!-- 5-Column Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 animate-fade-in-up">
                @php
                    $categoryBackgrounds = [
                        'repuestos-de-tractores' => 'https://images.unsplash.com/photo-1594913785162-e6785b4938a2?auto=format&fit=crop&q=80&w=600',
                        'motores-y-partes'       => 'https://images.unsplash.com/photo-1486006920555-c77dce18193b?auto=format&fit=crop&q=80&w=600',
                        'retenes-y-sellos'       => 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&q=80&w=600',
                        'sistemas-hidraulicos'   => 'https://images.unsplash.com/photo-1616401784845-180882ba9ba8?auto=format&fit=crop&q=80&w=600',
                    ];

                    $categoryDescriptions = [
                        'repuestos-de-tractores' => 'Componentes premium de motor, embrague y transmisión.',
                        'motores-y-partes'       => 'Pistones, camisas y empaquetaduras de alta durabilidad.',
                        'retenes-y-sellos'       => 'O-rings y sellos hidráulicos de vitón y nitrilo.',
                        'sistemas-hidraulicos'   => 'Bombas hidráulicas y mangueras R2 de alta presión.',
                    ];
                @endphp

                @foreach($categories as $cat)
                    @php
                        $bgImg = $categoryBackgrounds[$cat->slug] ?? 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&q=80&w=600';
                        $desc = $categoryDescriptions[$cat->slug] ?? 'Repuestos premium de alta calidad garantizada.';
                    @endphp
                    <a href="{{ route('store.catalog', [$store->slug, 'category' => $cat->slug]) }}" 
                       class="relative overflow-hidden group rounded-xl h-[340px] flex flex-col justify-between p-6 shadow-md hover:shadow-xl transition-all duration-300">
                        
                        <!-- Background Image -->
                        <div class="absolute inset-0 w-full h-full bg-cover bg-center transition-transform duration-500 group-hover:scale-110"
                             style="background-image: url('{{ $bgImg }}');">
                        </div>

                        <!-- Dark Overlay -->
                        <div class="absolute inset-0 bg-black/60 group-hover:bg-black/75 transition-colors duration-300 z-0"></div>

                        <!-- Central Icon & Title (Normal State) -->
                        <div class="relative z-10 flex flex-col items-center justify-center text-center my-auto space-y-4">
                            <!-- Thin white line-art SVG Icon -->
                            <div class="w-16 h-16 rounded-full border border-white/20 flex items-center justify-center bg-white/5 group-hover:bg-white/10 transition-colors">
                                @if($cat->slug === 'repuestos-de-tractores')
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0 M13 17a5 5 0 1 0 10 0a5 5 0 1 0 -10 0 M9 14h6 M18 12V8h-5v4 M9 14V10h4"/>
                                    </svg>
                                @elseif($cat->slug === 'motores-y-partes')
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    </svg>
                                @elseif($cat->slug === 'retenes-y-sellos')
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.5"/>
                                        <circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="1.5"/>
                                    </svg>
                                @elseif($cat->slug === 'sistemas-hidraulicos')
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8M12 4v16M4 20h16"/>
                                    </svg>
                                @else
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                @endif
                            </div>
                            
                            <h3 class="text-xs font-black uppercase tracking-wider text-white px-2">
                                {{ $cat->name }}
                            </h3>
                        </div>

                        <!-- Hover Details & Button -->
                        <div class="relative z-10 w-full flex flex-col items-center space-y-4 opacity-0 group-hover:opacity-100 translate-y-4 group-hover:translate-y-0 transition-all duration-300 pb-2">
                            <p class="text-[10px] text-gray-300 font-bold text-center px-4 leading-normal">
                                {{ $desc }}
                            </p>
                            <span class="px-4 py-2 bg-[#E50914] text-white text-[9px] font-black uppercase tracking-widest rounded hover:bg-red-700 transition-colors shadow-lg">
                                VER PRODUCTOS
                            </span>
                        </div>
                    </a>
                @endforeach

                <!-- 5th Column Card: Servicio y Soporte Técnico B2B (Static) -->
                @if($store->whatsapp_phone)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp_phone) }}?text={{ urlencode('Hola, me gustaría solicitar servicio técnico o asesoría sobre repuestos.') }}"
                       target="_blank"
                       class="relative overflow-hidden group rounded-xl h-[340px] flex flex-col justify-between p-6 shadow-md hover:shadow-xl transition-all duration-300">
                        
                        <!-- Background Image -->
                        <div class="absolute inset-0 w-full h-full bg-cover bg-center transition-transform duration-500 group-hover:scale-110"
                             style="background-image: url('https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&q=80&w=600');">
                        </div>

                        <!-- Dark Overlay -->
                        <div class="absolute inset-0 bg-black/60 group-hover:bg-black/75 transition-colors duration-300 z-0"></div>

                        <!-- Central Icon & Title (Normal State) -->
                        <div class="relative z-10 flex flex-col items-center justify-center text-center my-auto space-y-4">
                            <!-- Thin white line-art SVG Icon for Wrench/Tools -->
                            <div class="w-16 h-16 rounded-full border border-white/20 flex items-center justify-center bg-white/5 group-hover:bg-white/10 transition-colors">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.42 15.17L17.25 21A1.5 1.5 0 0020 18.75l-5.83-5.83M11.42 15.17a3 3 0 11-4.24-4.24M11.42 15.17L18.4 8.2a1.5 1.5 0 012.12 0l1.28 1.28a1.5 1.5 0 010 2.12l-7.4 7.4M7.18 10.93L1.5 16.6a1.5 1.5 0 002.68 2.68l5.7-5.7M7.18 10.93a3 3 0 11-4.24-4.24"/>
                                </svg>
                            </div>
                            
                            <h3 class="text-xs font-black uppercase tracking-wider text-white px-2">
                                Soporte & Asesoría B2B
                            </h3>
                        </div>

                        <!-- Hover Details & Button -->
                        <div class="relative z-10 w-full flex flex-col items-center space-y-4 opacity-0 group-hover:opacity-100 translate-y-4 group-hover:translate-y-0 transition-all duration-300 pb-2">
                            <p class="text-[10px] text-gray-300 font-bold text-center px-4 leading-normal">
                                Asesoría técnica inmediata en campo y cotizaciones especiales.
                            </p>
                            <span class="px-4 py-2 bg-[#16A34A] text-white text-[9px] font-black uppercase tracking-widest rounded hover:bg-green-700 transition-colors shadow-lg">
                                CONSULTAR WHATSAPP
                            </span>
                        </div>
                    </a>
                @endif
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="py-16 bg-gray-50 border-b border-gray-100 overflow-hidden" 
             x-data="{
                 scrollNext() {
                     const container = this.$refs.carousel;
                     if (!container) return;
                     const cardWidth = container.firstElementChild.getBoundingClientRect().width;
                     container.scrollBy({ left: cardWidth + 24, behavior: 'smooth' });
                 },
                 scrollPrev() {
                     const container = this.$refs.carousel;
                     if (!container) return;
                     const cardWidth = container.firstElementChild.getBoundingClientRect().width;
                     container.scrollBy({ left: -(cardWidth + 24), behavior: 'smooth' });
                 }
             }">
        <style>
            .no-scrollbar::-webkit-scrollbar {
                display: none;
            }
            .no-scrollbar {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        </style>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Section Header with red vertical line and carousel controls -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-l-4 border-[#E50914] pl-4 mb-10 text-left">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-[#E50914] block mb-1">LO MÁS SOLICITADO</span>
                    <h2 class="text-xl sm:text-2xl font-black uppercase tracking-tight text-gray-900">
                        Productos Destacados & Repuestos Premium
                    </h2>
                    <p class="text-xs text-gray-500 font-semibold mt-1">
                        Componentes de alta durabilidad garantizada para tu maquinaria.
                    </p>
                </div>
                <div class="flex items-center gap-4 mt-4 sm:mt-0 self-start sm:self-auto">
                    <a href="{{ route('store.catalog', $store->slug) }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-black hover:bg-gray-800 text-white font-black text-[10px] uppercase tracking-wider rounded transition-colors shadow-sm shrink-0">
                        Ver Catálogo
                    </a>
                    <!-- Carousel Navigation Controls -->
                    <div class="flex gap-2">
                        <button @click="scrollPrev()" class="w-10 h-10 border border-gray-200 hover:border-gray-400 text-gray-700 flex items-center justify-center rounded-full bg-white hover:bg-gray-50 transition-all shadow-sm shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <button @click="scrollNext()" class="w-10 h-10 border border-gray-200 hover:border-gray-400 text-gray-700 flex items-center justify-center rounded-full bg-white hover:bg-gray-50 transition-all shadow-sm shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Products Carousel -->
            @if($featuredProducts->isEmpty())
                <div class="text-center py-12 bg-white rounded-xl border border-gray-200 shadow-sm">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-widest block">No hay productos destacados por el momento</span>
                </div>
            @else
                <div x-ref="carousel" class="flex gap-6 overflow-x-auto scroll-smooth snap-x snap-mandatory no-scrollbar pb-6 select-none">
                    @foreach($featuredProducts as $product)
                        @php
                            $detectedBrand = 'EATON';
                            if (str_contains(strtoupper($product->name), 'JOHN DEERE')) {
                                $detectedBrand = 'JOHN DEERE';
                            } elseif (str_contains(strtoupper($product->name), 'PERKINS')) {
                                $detectedBrand = 'PERKINS';
                            } elseif (str_contains(strtoupper($product->name), 'CUMMINS')) {
                                $detectedBrand = 'CUMMINS';
                            } elseif (str_contains(strtoupper($product->name), 'MASSEY FERGUSON')) {
                                $detectedBrand = 'MASSEY FERGUSON';
                            } elseif (str_contains(strtoupper($product->name), 'DONALDSON')) {
                                $detectedBrand = 'DONALDSON';
                            }
                        @endphp
                        <div class="snap-start shrink-0 w-full sm:w-[calc(50%-12px)] lg:w-[calc(25%-18px)] bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between group relative">
                            
                            <!-- Sale Discount Badge -->
                            @if($product->compare_price && $product->compare_price > $product->price)
                                @php
                                    $discount = round((($product->compare_price - $product->price) / $product->compare_price) * 100);
                                @endphp
                                <span class="absolute top-3 left-3 px-2 py-1 text-[10px] font-black text-white bg-[#E50914] rounded z-10 shadow-sm">
                                    - {{ $discount }}%
                                </span>
                            @endif

                            <div>
                                <!-- Image Box -->
                                <div class="aspect-square w-full bg-gray-50 flex items-center justify-center p-6 border-b border-gray-100 relative overflow-hidden">
                                    @if($product->image_path)
                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="max-h-full max-w-full object-contain group-hover:scale-105 transition-transform duration-300">
                                    @else
                                        <div class="text-center text-gray-300">
                                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block">Imagen no disponible</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Content Info -->
                                <div class="p-5 space-y-2">
                                    <a href="{{ route('store.product', [$store->slug, $product->slug]) }}" class="block font-black text-gray-800 hover:text-[#E50914] text-xs uppercase tracking-wide leading-snug line-clamp-2 transition-colors">
                                        {{ $product->name }}
                                    </a>
                                    <div class="text-[11px] text-gray-600 font-bold uppercase space-y-0.5">
                                        <p>SKU: <span class="text-gray-900 font-medium">{{ $product->sku ?? 'N/D' }}</span></p>
                                        <p>MARCA: <span class="text-gray-900 font-medium">{{ $detectedBrand }}</span></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Price and Add Button -->
                            <div class="p-5 pt-0">
                                <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                                    <div>
                                        @if($product->compare_price && $product->compare_price > $product->price)
                                            <span class="block text-sm font-black text-[#E50914]">S/. {{ number_format($product->price, 2) }}</span>
                                            <span class="text-[10px] text-gray-400 line-through">S/. {{ number_format($product->compare_price, 2) }}</span>
                                        @else
                                            <span class="block text-sm font-black text-gray-800">S/. {{ number_format($product->price, 2) }}</span>
                                        @endif
                                    </div>
                                    <button @click="addToCart({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->price }}, '{{ $product->image_path ? $product->image_url : '' }}')"
                                            class="px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-wider bg-[#E50914] hover:bg-red-700 text-white shadow-sm transition-colors">
                                        + AÑADIR
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- Corporate About Us Section (Clean & Minimal) -->
    <section id="nosotros" class="py-20 bg-white scroll-mt-20 border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-center animate-fade-in-up">
                <div class="lg:col-span-7 space-y-6">
                    <span class="text-[10px] font-black uppercase tracking-widest text-[#E50914] block">TFL PARTS CORPORATIVO</span>
                    <h2 class="text-3xl font-black text-gray-900 leading-tight uppercase tracking-tight">
                        Quiénes Somos & Misión
                    </h2>
                    <p class="text-gray-600 text-xs leading-relaxed font-semibold">
                        {{ $store->description }}
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 text-xs font-bold text-gray-700">
                        <div class="flex items-start gap-4 border-l-2 border-[#E50914] pl-4">
                            <div>
                                <h4 class="text-gray-900 uppercase font-black tracking-wider text-[11px]">Distribuidor Autorizado B2B</h4>
                                <p class="text-gray-500 font-medium text-[11px] mt-0.5">Distribución directa para empresas y distribuidores autorizados.</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-4 border-l-2 border-[#E50914] pl-4">
                            <div>
                                <h4 class="text-gray-900 uppercase font-black tracking-wider text-[11px]">Repuestos Certificados</h4>
                                <p class="text-gray-500 font-medium text-[11px] mt-0.5">Componentes certificados que garantizan la operatividad en el campo.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Right Side (Sleek minimalist panel) -->
                <div class="lg:col-span-5 hex-bg rounded-xl p-8 border border-gray-900 text-center min-h-[280px] flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(var(--accent) 1px, transparent 1px); background-size: 20px 20px;"></div>
                    <div class="my-auto relative z-10">
                        <svg class="w-12 h-12 mx-auto text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div class="space-y-1 relative z-10">
                        <h4 class="text-[10px] font-black uppercase text-white tracking-widest">SHOWROOM CENTRAL</h4>
                        <p class="text-[11px] text-gray-400 font-semibold">Av. Agricultura 450, Lambayeque</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Global Distributors Section -->
    @if(!empty($store->distributors))
    <section class="py-16 bg-gray-50 border-t border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="border-b border-gray-200 pb-4 mb-10">
                <h2 class="text-xs font-black uppercase tracking-widest text-gray-900">RED DE DISTRIBUIDORES GLOBALES</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-xs font-semibold text-gray-600 animate-fade-in-up">
                @foreach($store->distributors as $dist)
                    <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm space-y-4">
                        <h3 class="text-xs font-black text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">{{ $dist['region'] ?? '' }}</h3>
                        <ul class="space-y-3 font-semibold text-gray-500">
                            @if(!empty($dist['locations']))
                                @foreach($dist['locations'] as $loc)
                                    <li class="flex items-center gap-2 pl-2 border-l border-gray-200">
                                        <span>{{ $loc }}</span>
                                    </li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Photo Gallery / Showroom Section -->
    <section id="galeria" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Section Header with red vertical line -->
            <div class="border-l-4 border-[#E50914] pl-4 mb-10 text-left">
                <span class="text-[10px] font-black uppercase tracking-widest text-[#E50914] block mb-1">STOCK FOTOGRÁFICO DE REPUESTOS</span>
                <h2 class="text-xl sm:text-2xl font-black uppercase tracking-tight text-gray-900">
                    Repuestos para Todo Tipo de Tractores & Maquinaria
                </h2>
                <p class="text-xs text-gray-500 font-semibold mt-1">
                    Visualiza nuestro stock físico en almacén: lotes de repuestos agrícolas y componentes clasificados por marca.
                </p>
            </div>

            @if($galleryItems->isEmpty())
                <div class="text-center py-12 bg-white rounded border border-gray-200">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-gray-400 text-xs font-semibold">Próximamente fotos de la galería...</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 animate-fade-in-up">
                    @foreach($galleryItems as $item)
                        <div class="bg-white rounded border border-gray-200 overflow-hidden shadow-sm group hover:shadow-md transition-all">
                            <div class="aspect-square bg-gray-50 flex items-center justify-center relative overflow-hidden border-b border-gray-100">
                                @if($item->image_path)
                                    <img src="{{ $item->image_url }}" alt="{{ $item->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                @else
                                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="p-4">
                                <h4 class="font-black text-xs uppercase tracking-wider text-gray-900 leading-tight">{{ $item->title }}</h4>
                                @if($item->description)
                                    <p class="text-[11px] text-gray-500 mt-1 leading-snug">{{ $item->description }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- Dedicated Suppliers Area (Integrate Brand Images) -->
    <section class="py-16 bg-gray-50 border-t border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-8">
            <h4 class="text-[10px] font-black uppercase tracking-widest text-[#E50914]">NUESTROS PROVEEDORES CERTIFICADOS</h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-6 animate-fade-in-up">
                @php
                    $supplierBrands = [
                        ['id' => 'john_deere', 'name' => 'JOHN DEERE'],
                        ['id' => 'perkins', 'name' => 'PERKINS'],
                        ['id' => 'massey_ferguson', 'name' => 'MASSEY FERGUSON'],
                        ['id' => 'cummins', 'name' => 'CUMMINS'],
                        ['id' => 'donaldson', 'name' => 'DONALDSON'],
                    ];
                @endphp
                @foreach($supplierBrands as $brand)
                    <div class="h-16 border border-gray-200 bg-white rounded flex items-center justify-center p-4 hover:border-[#E50914] transition-colors relative group">
                        <!-- Try to load image if exists, else fall back to styling -->
                        <img src="{{ asset('storage/suppliers/' . $brand['id'] . '.svg') }}" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" 
                             alt="{{ $brand['name'] }}" 
                             class="max-h-full max-w-full object-contain grayscale opacity-50 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-300">
                        <span class="hidden font-extrabold tracking-widest text-gray-400 text-xs uppercase">{{ $brand['name'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Footer / Contact -->
    <!-- Dynamic Footer will be rendered in the section loop -->

    <!-- Floating Toast Notification -->
    <div x-show="showToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2 translate-x-2"
         x-transition:enter-end="opacity-100 translate-y-0 translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 translate-x-0"
         x-transition:leave-end="opacity-0 translate-y-2 translate-x-2"
         style="display: none;"
         class="fixed bottom-24 right-6 z-50 max-w-sm bg-gray-900 text-white px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3 border border-gray-800">
        <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="text-[10px] font-bold uppercase tracking-wider" x-text="toastMessage"></span>
    </div>

    <!-- Alpine.js App -->
    <script>
        function cartApp() {
            return {
                openCartDrawer: false,
                openCartDropdown: false,
                showToast: false,
                toastMessage: '',
                items: [],
                cartCount: 0,
                submitting: false,
                checkoutStep: 1,
                checkoutForm: {
                    customer_name: '',
                    customer_phone: '',
                    customer_address: '',
                    customer_notes: ''
                },

                initCart() {
                    const loadCart = () => {
                        if (window.TribioCart) {
                            this.items = [...window.TribioCart.items];
                            this.cartCount = window.TribioCart.count();
                        }
                    };
                    loadCart();
                    document.addEventListener('DOMContentLoaded', loadCart);
                },

                showToastNotification(message) {
                    this.toastMessage = message;
                    this.showToast = true;
                    setTimeout(() => {
                        this.showToast = false;
                    }, 3000);
                },

                addToCart(id, name, price, image) {
                    if (window.TribioCart) {
                        window.TribioCart.add(id, name, price, image);
                        this.items = [...window.TribioCart.items];
                        this.cartCount = window.TribioCart.count();
                        this.showToastNotification(name + ' añadido al carrito');
                    }
                },

                removeItem(id) {
                    if (window.TribioCart) {
                        window.TribioCart.remove(id);
                        this.items = [...window.TribioCart.items];
                        this.cartCount = window.TribioCart.count();
                    }
                },

                updateQty(id, qty) {
                    if (window.TribioCart) {
                        window.TribioCart.updateQuantity(id, qty);
                        this.items = [...window.TribioCart.items];
                        this.cartCount = window.TribioCart.count();
                    }
                },

                totalSum() {
                    if (window.TribioCart) {
                        return window.TribioCart.total();
                    }
                    return 0;
                },

                async submitOrder() {
                    if (!this.checkoutForm.customer_name || !this.checkoutForm.customer_phone || !this.checkoutForm.customer_address) {
                        alert('Por favor complete todos los campos obligatorios (*)');
                        return;
                    }

                    this.submitting = true;

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const payload = {
                            customer_name: this.checkoutForm.customer_name,
                            customer_phone: this.checkoutForm.customer_phone,
                            customer_address: this.checkoutForm.customer_address,
                            customer_notes: this.checkoutForm.customer_notes,
                            items: this.items.map(item => ({ id: item.id, quantity: item.quantity })),
                            _token: token
                        };

                        const response = await fetch('{{ route("store.checkout", $store->slug) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await response.json();

                        if (data.success) {
                            if (window.TribioCart) {
                                window.TribioCart.clear();
                            }
                            window.open(data.whatsapp_url, '_blank');
                            window.location.href = data.redirect_url;
                        } else {
                            alert(data.error || 'Ocurrió un error al procesar el pedido.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error al enviar el pedido. Por favor intente nuevamente.');
                    } finally {
                        this.submitting = false;
                    }
                }
            }
        }
    </script>

    <!-- Floating WhatsApp Button with Pulsating Effect -->
    @if($store->whatsapp_phone)
        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp_phone) }}?text={{ urlencode('Hola, me gustaría recibir más información.') }}"
           target="_blank"
           class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-14 h-14 bg-[#25D366] rounded-full text-white shadow-2xl hover:bg-[#20ba5a] transition-all duration-300 hover:scale-110 whatsapp-btn"
           aria-label="Contactar por WhatsApp">
            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.746.953 3.71 1.458 5.704 1.463h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
        </a>
    @endif

    <!-- Meta CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

</body>
</html>
