<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2176d2">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Vender') — Tribio</title>
    @php $simpleStore = Auth::user()->currentStore(); @endphp
    <link rel="icon" href="{{ $simpleStore && $simpleStore->logo_path ? $simpleStore->logo_url : asset('favicon.ico') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/css/dashboard.css', 'resources/js/app.js'])
</head>
<body class="dashboard-workspace sm-body font-outfit antialiased">
<div class="sm-app" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    <header class="sm-top">
        <button type="button" class="sm-icon-btn" @click="menu = true" aria-label="Abrir menú" aria-haspopup="dialog"><x-dashboard-icon name="menu"/></button>
        <a href="{{ route('dashboard.simple.index') }}" class="sm-store"><strong>{{ $simpleStore?->name }}</strong></a>
        @yield('top_right')
    </header>

    <main class="sm-scroll" id="dashboard-content">
        @if(session('success'))<div class="sm-flash" role="status">{{ session('success') }}</div>@endif
        @if(session('info'))<div class="sm-flash" role="status">{{ session('info') }}</div>@endif
        @yield('content')
    </main>

    @yield('bottom')

    {{-- Menú: las pocas cosas que hay fuera de vender --}}
    <div class="sm-scrim" x-show="menu" x-cloak x-transition.opacity @click="menu = false" aria-hidden="true"></div>
    <nav class="sm-menu" x-show="menu" x-cloak x-transition:enter="sm-slide-in" x-transition:leave="sm-slide-out" aria-label="Menú" role="dialog" aria-modal="true">
        <div class="sm-menu-head"><strong>{{ $simpleStore?->name }}</strong><button type="button" class="sm-icon-btn" @click="menu = false" aria-label="Cerrar menú">✕</button></div>
        @foreach(\App\Support\DashboardNav::items($simpleStore) as $item)
            <a href="{{ route($item['route']) }}" class="sm-menu-link {{ \App\Support\DashboardNav::active($item) ? 'is-active' : '' }}"><x-dashboard-icon :name="$item['icon']"/>{{ $item['label'] }}</a>
        @endforeach
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="sm-menu-link"><x-dashboard-icon name="logout"/>Cerrar sesión</button></form>
    </nav>
</div>
</body>
</html>
