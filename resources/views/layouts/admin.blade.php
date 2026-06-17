<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — Tribio Super Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="admin-body font-outfit antialiased">
<div class="flex h-screen overflow-hidden">

    {{-- ADMIN SIDEBAR --}}
    <aside class="w-64 flex-shrink-0 flex flex-col border-r" style="background: #050508; border-color: rgba(255,255,255,0.05);">

        <div class="flex items-center gap-3 px-5 py-5 border-b" style="border-color: rgba(255,255,255,0.05);">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-red-500 to-orange-500 flex items-center justify-center shadow-lg">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <div>
                <span class="text-lg font-black text-white">TRI<span class="text-tribio-cyan">BIO</span></span>
                <p class="text-red-400 text-xs font-semibold">SUPER ADMIN</p>
            </div>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            @php
            $adminNav = [
                ['route' => 'admin.dashboard', 'icon' => '📊', 'label' => 'Dashboard Global'],
                ['route' => 'admin.tiendas.index', 'icon' => '🏪', 'label' => 'Tiendas'],
                ['route' => 'admin.usuarios.index', 'icon' => '👥', 'label' => 'Usuarios'],
                ['route' => 'admin.analytics', 'icon' => '📈', 'label' => 'Analytics'],
            ];
            @endphp
            @foreach($adminNav as $item)
            <a href="{{ route($item['route']) }}"
               class="sidebar-link {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <span class="text-lg">{{ $item['icon'] }}</span>
                {{ $item['label'] }}
            </a>
            @endforeach
        </nav>

        <div class="px-3 py-4 border-t" style="border-color: rgba(255,255,255,0.05);">
            <a href="{{ route('home') }}" class="sidebar-link">🌐 Ver portal</a>
            <form method="POST" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="sidebar-link w-full text-left text-red-400">🚪 Salir</button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="flex items-center justify-between px-6 py-4 border-b flex-shrink-0"
                style="background: rgba(5,5,8,0.9); backdrop-filter: blur(12px); border-color: rgba(255,255,255,0.05);">
            <h1 class="text-white font-bold">@yield('page_title', 'Panel de Administración')</h1>
            <div class="flex items-center gap-3">
                <span class="badge badge-red">Super Admin</span>
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-red-500 to-orange-500 flex items-center justify-center text-white text-sm font-bold">
                    {{ substr(Auth::user()->name, 0, 1) }}
                </div>
            </div>
        </header>

        @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mx-6 mt-4 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm">
            ✅ {{ session('success') }}
        </div>
        @endif

        <main class="flex-1 overflow-y-auto p-6">
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
