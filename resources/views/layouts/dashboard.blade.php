<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — Tribio</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="dashboard-body font-outfit antialiased">
<div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: true }">

    {{-- SIDEBAR --}}
    <aside :class="sidebarOpen ? 'w-64' : 'w-20'"
           class="flex-shrink-0 transition-all duration-300 flex flex-col border-r border-slate-100 bg-white">

        {{-- Logo --}}
        <div class="flex items-center gap-3 px-5 py-5 border-b border-slate-100">
            <div class="w-9 h-9 rounded-xl bg-slate-900 flex items-center justify-center flex-shrink-0 shadow-md">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span x-show="sidebarOpen" class="text-xl font-black text-slate-900">TRI<span class="text-sky-500">BIO</span></span>
        </div>

        {{-- Store Info --}}
        @if(Auth::user()->store)
        <div x-show="sidebarOpen" class="px-4 py-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-sm flex-shrink-0">
                    🏪
                </div>
                <div class="min-w-0">
                    <p class="text-slate-800 text-xs font-semibold truncate">{{ Auth::user()->store->name }}</p>
                    <p class="text-slate-400 text-xs truncate">{{ Auth::user()->store->slug }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- Nav --}}
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @php
            $navItems = [
                ['route' => 'dashboard.index', 'icon' => '📊', 'label' => 'Dashboard'],
                ['route' => 'dashboard.productos.index', 'icon' => '📦', 'label' => 'Productos'],
                ['route' => 'dashboard.categorias.index', 'icon' => '🗂️', 'label' => 'Categorías'],
                ['route' => 'dashboard.marcas.index', 'icon' => '🏷️', 'label' => 'Marcas'],
                ['route' => 'dashboard.galeria.index', 'icon' => '🖼️', 'label' => 'Galería'],
                ['route' => 'dashboard.pedidos.index', 'icon' => '🛒', 'label' => 'Pedidos'],
                ['route' => 'dashboard.inventario.index', 'icon' => '📋', 'label' => 'Inventario'],
                ['route' => 'dashboard.store.edit', 'icon' => '⚙️', 'label' => 'Mi Tienda'],
                // ['route' => 'dashboard.store.templates', 'icon' => '🎨', 'label' => 'Plantillas'],
                // ['route' => 'dashboard.store.builder', 'icon' => '🏗️', 'label' => 'Editor Web'],
            ];
            @endphp
            @foreach($navItems as $item)
            <a href="{{ route($item['route']) }}"
               class="sidebar-link {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <span class="text-lg flex-shrink-0">{{ $item['icon'] }}</span>
                <span x-show="sidebarOpen" class="truncate">{{ $item['label'] }}</span>
            </a>
            @endforeach
        </nav>

        {{-- Bottom --}}
        <div class="px-3 py-4 border-t border-slate-100 space-y-1">
            @if(Auth::user()->store)
            <a href="{{ route('store.show', Auth::user()->store->slug) }}" target="_blank"
               class="sidebar-link">
                <span class="text-lg">🌐</span>
                <span x-show="sidebarOpen">Ver mi tienda</span>
            </a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-link w-full text-left">
                    <span class="text-lg">🚪</span>
                    <span x-show="sidebarOpen">Cerrar sesión</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- MAIN CONTENT --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- Topbar --}}
        <header class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-white/90 backdrop-blur-md flex-shrink-0">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen"
                        class="text-slate-400 hover:text-slate-900 transition-colors p-1.5 rounded-lg hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <h1 class="text-slate-900 font-extrabold text-lg">@yield('page_title', 'Dashboard')</h1>
            </div>

            <div class="flex items-center gap-3">
                {{-- Notification badge --}}
                <div class="relative">
                    <button class="text-slate-400 hover:text-slate-900 transition-colors p-2 rounded-lg hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </button>
                </div>

                {{-- User menu --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                            class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
                        <div class="w-8 h-8 rounded-xl bg-slate-900 flex items-center justify-center text-white text-sm font-bold">
                            {{ substr(Auth::user()->name, 0, 1) }}
                        </div>
                        <span class="text-slate-700 text-sm font-medium hidden md:block">{{ Auth::user()->name }}</span>
                        <svg class="w-4 h-4 text-slate-400 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-transition
                         class="absolute right-0 top-12 w-48 bg-white border border-slate-100 py-2 shadow-xl rounded-2xl z-50">
                        <a href="{{ route('dashboard.store.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition-colors">⚙️ Configurar tienda</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:text-red-650 hover:bg-slate-50 transition-colors">🚪 Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Flash --}}
        @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mx-6 mt-4 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm flex items-center gap-3">
            ✅ {{ session('success') }}
            <button @click="show = false" class="ml-auto text-green-400/50 hover:text-green-400">✕</button>
        </div>
        @endif

        {{-- Content --}}
        <main class="flex-1 overflow-y-auto p-6">
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
