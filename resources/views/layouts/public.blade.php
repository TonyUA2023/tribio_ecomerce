<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tribio - Tu Negocio Vendiendo las 24 Horas')</title>
    <meta name="description" content="@yield('meta_description', 'Tribio te crea una tienda virtual para vender tus productos o servicios por internet sin complicaciones.')">

    {{-- Google Fonts: Plus Jakarta Sans --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Alpine.js v3 (CDN) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="public-body font-outfit antialiased">

    {{-- Navbar --}}
    <nav x-data="{ scrolled: false }"
         x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 20)"
         :class="scrolled ? 'bg-white/90 backdrop-blur-md shadow-sm border-b border-slate-100' : 'bg-transparent'"
         class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">

                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                    <img src="{{ asset('images/logo/logo.png') }}" alt="Tribio Logo" class="w-9 h-9 object-contain rounded-xl shadow-md shadow-sky-500/10 group-hover:scale-110 transition-transform">
                    <span class="text-2xl font-black text-slate-900 tracking-tight">TRI<span class="text-sky-500">BIO</span></span>
                </a>

                {{-- CTA Button --}}
                <div class="flex items-center gap-4">
                    @auth
                        @if(Auth::user()->isSuperAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="btn-primary">Panel Admin</a>
                        @else
                            <a href="{{ route('dashboard.index') }}" class="btn-primary">Mi Dashboard</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900 text-sm font-semibold transition-colors">Ingresar</a>
                        <a href="{{ route('register') }}" class="btn-primary">Crear mi tienda</a>
                    @endauth
                </div>

            </div>
        </div>
    </nav>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-transition
             x-init="setTimeout(() => show = false, 5000)"
             class="fixed top-20 right-4 z-50 max-w-sm bg-green-50 border border-green-100 text-slate-800 rounded-2xl p-4 shadow-xl shadow-slate-100">
            <div class="flex items-start gap-3">
                <span class="text-green-500 text-lg">✅</span>
                <div>
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="ml-auto text-slate-400 hover:text-slate-600">✕</button>
            </div>
        </div>
    @endif

    {{-- Contenido principal --}}
    @yield('content')

    {{-- Footer --}}
    <footer class="bg-slate-50 border-t border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                {{-- Marca --}}
                <div class="md:col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <img src="{{ asset('images/logo/logo.png') }}" alt="Tribio Logo" class="w-9 h-9 object-contain rounded-xl">
                        <span class="text-2xl font-black text-slate-900">TRI<span class="text-sky-500">BIO</span></span>
                    </div>
                    <p class="text-slate-500 text-sm leading-relaxed max-w-sm">
                        Tribio te crea una tienda virtual para vender tus productos o servicios por internet sin complicaciones. Tu negocio vendiendo las 24 horas.
                    </p>
                    <div class="flex items-center gap-3 mt-6">
                        <a href="https://wa.me/51902699916" target="_blank"
                           class="w-10 h-10 rounded-xl bg-green-50 border border-green-100 flex items-center justify-center text-green-600 hover:bg-green-100 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Links --}}
                <div>
                    <h4 class="text-slate-900 font-semibold mb-4 text-sm uppercase tracking-wider">Plataforma</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('directory') }}" class="text-slate-500 hover:text-slate-900 text-sm transition-colors">Directorio de tiendas</a></li>
                        <li><a href="{{ route('register') }}" class="text-slate-500 hover:text-slate-900 text-sm transition-colors">Crear mi tienda</a></li>
                        <li><a href="#precios" class="text-slate-500 hover:text-slate-900 text-sm transition-colors">Precios</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-slate-900 font-semibold mb-4 text-sm uppercase tracking-wider">Contacto</h4>
                    <ul class="space-y-2">
                        <li><a href="https://wa.me/51902699916" class="text-slate-500 hover:text-slate-900 text-sm transition-colors">+51 902 699 916</a></li>
                        <li><span class="text-slate-400 text-sm">Tribio © {{ date('Y') }}</span></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-slate-200 mt-12 pt-8 text-center">
                <p class="text-slate-400 text-xs">© {{ date('Y') }} Tribio. Todos los derechos reservados. Hecho con ❤️ para emprendedores.</p>
            </div>
        </div>
    </footer>

    {{-- Floating WhatsApp Button --}}
    <style>
        @keyframes wa-pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.6), 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            }
            70% {
                box-shadow: 0 0 0 12px rgba(37, 211, 102, 0), 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0), 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            }
        }
        .animate-wa-pulse {
            animation: wa-pulse 2.2s infinite ease-in-out;
        }
    </style>
    <a href="https://wa.me/51902699916?text=Hola!%20Me%20gustaría%20saber%20más%20sobre%20Tribio." 
       target="_blank" 
       rel="noopener noreferrer" 
       class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-14 h-14 bg-[#25D366] hover:bg-[#20BA56] text-white rounded-full hover:scale-110 active:scale-95 transition-all duration-300 animate-wa-pulse group"
       title="Escríbenos por WhatsApp">
        
        {{-- WhatsApp Official Icon (FontAwesome Standard) --}}
        <svg class="w-7 h-7" fill="currentColor" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg">
            <path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L3.2 496.8 162.5 455c32.4 17.7 68.8 27 106.1 27 122.4 0 221.4-99.6 221.4-222 0-59.3-23.2-115-65-117.9zM223.9 445.2c-33.2 0-65.8-8.9-94.2-25.7l-6.7-4-70.2 18.4 18.8-68.5-4.4-7.1c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/>
        </svg>
    </a>

    @stack('scripts')
</body>
</html>
