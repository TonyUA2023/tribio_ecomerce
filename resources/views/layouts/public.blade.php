<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tribio - Tu Negocio Vendiendo las 24 Horas')</title>
    <meta name="description" content="@yield('meta_description', 'Tribio te crea una tienda virtual para vender tus productos o servicios por internet sin complicaciones.')">

    {{-- Google Fonts: Outfit --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Alpine.js v3 (CDN) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('head')
</head>
<body class="font-outfit antialiased">

    {{-- Navbar --}}
    <nav x-data="{ open: false, scrolled: false }"
         x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 20)"
         :class="scrolled ? 'bg-tribio-dark/95 backdrop-blur-md shadow-lg shadow-black/20' : 'bg-transparent'"
         class="fixed top-0 left-0 right-0 z-50 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">

                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-tribio-purple to-tribio-cyan flex items-center justify-center shadow-lg shadow-tribio-purple/40 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <span class="text-2xl font-black text-white tracking-tight">TRI<span class="text-tribio-cyan">BIO</span></span>
                </a>

                {{-- Links escritorio --}}
                <div class="hidden md:flex items-center gap-8">
                    <a href="{{ route('directory') }}" class="text-white/70 hover:text-white text-sm font-medium transition-colors">Negocios</a>
                    <a href="#como-funciona" class="text-white/70 hover:text-white text-sm font-medium transition-colors">¿Cómo funciona?</a>
                    <a href="#precios" class="text-white/70 hover:text-white text-sm font-medium transition-colors">Precios</a>
                </div>

                {{-- CTA Buttons --}}
                <div class="hidden md:flex items-center gap-3">
                    @auth
                        @if(Auth::user()->isSuperAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="btn-ghost">Panel Admin</a>
                        @else
                            <a href="{{ route('dashboard.index') }}" class="btn-ghost">Mi Dashboard</a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="text-white/80 hover:text-white text-sm font-medium transition-colors">Ingresar</a>
                        <a href="{{ route('register') }}" class="btn-primary">Crear mi tienda</a>
                    @endauth
                </div>

                {{-- Menu mobile --}}
                <button @click="open = !open" class="md:hidden text-white/80 hover:text-white p-2">
                    <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Menu mobile desplegable --}}
            <div x-show="open" x-transition class="md:hidden pb-4 border-t border-white/10 mt-2 pt-4">
                <div class="flex flex-col gap-3">
                    <a href="{{ route('directory') }}" class="text-white/70 hover:text-white text-sm font-medium py-2">Negocios</a>
                    <a href="#como-funciona" class="text-white/70 hover:text-white text-sm font-medium py-2">¿Cómo funciona?</a>
                    <a href="#precios" class="text-white/70 hover:text-white text-sm font-medium py-2">Precios</a>
                    <div class="border-t border-white/10 pt-3 flex flex-col gap-2">
                        <a href="{{ route('login') }}" class="btn-ghost text-center">Ingresar</a>
                        <a href="{{ route('register') }}" class="btn-primary text-center">Crear mi tienda</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-transition
             x-init="setTimeout(() => show = false, 5000)"
             class="fixed top-20 right-4 z-50 max-w-sm bg-green-500/20 border border-green-500/40 backdrop-blur-md text-white rounded-2xl p-4 shadow-xl">
            <div class="flex items-start gap-3">
                <span class="text-green-400 text-lg">✅</span>
                <div>
                    <p class="text-sm font-medium text-green-300">{{ session('success') }}</p>
                </div>
                <button @click="show = false" class="ml-auto text-white/50 hover:text-white">✕</button>
            </div>
        </div>
    @endif

    {{-- Contenido principal --}}
    @yield('content')

    {{-- Footer --}}
    <footer class="bg-tribio-darker border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                {{-- Marca --}}
                <div class="md:col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-tribio-purple to-tribio-cyan flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <span class="text-2xl font-black text-white">TRI<span class="text-tribio-cyan">BIO</span></span>
                    </div>
                    <p class="text-white/50 text-sm leading-relaxed max-w-sm">
                        Tribio te crea una tienda virtual para vender tus productos o servicios por internet sin complicaciones. Tu negocio vendiendo las 24 horas.
                    </p>
                    <div class="flex items-center gap-3 mt-6">
                        <a href="https://wa.me/51902699916" target="_blank"
                           class="w-10 h-10 rounded-xl bg-green-500/20 border border-green-500/30 flex items-center justify-center text-green-400 hover:bg-green-500/30 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Links --}}
                <div>
                    <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Plataforma</h4>
                    <ul class="space-y-2">
                        <li><a href="{{ route('directory') }}" class="text-white/50 hover:text-white text-sm transition-colors">Directorio de tiendas</a></li>
                        <li><a href="{{ route('register') }}" class="text-white/50 hover:text-white text-sm transition-colors">Crear mi tienda</a></li>
                        <li><a href="#precios" class="text-white/50 hover:text-white text-sm transition-colors">Precios</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Contacto</h4>
                    <ul class="space-y-2">
                        <li><a href="https://wa.me/51902699916" class="text-white/50 hover:text-white text-sm transition-colors">+51 902 699 916</a></li>
                        <li><span class="text-white/30 text-sm">Tribio © {{ date('Y') }}</span></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-white/5 mt-12 pt-8 text-center">
                <p class="text-white/30 text-xs">© {{ date('Y') }} Tribio. Todos los derechos reservados. Hecho con ❤️ para emprendedores.</p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
