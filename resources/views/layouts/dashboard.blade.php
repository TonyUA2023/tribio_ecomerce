<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Tribio</title>
    @php $dashboardStore = Auth::user()->store; @endphp
    <link rel="icon" href="{{ $dashboardStore && $dashboardStore->logo_path ? $dashboardStore->logo_url : asset('favicon.ico') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/css/dashboard.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="dashboard-body dashboard-workspace font-outfit antialiased">
<div class="dash-shell" x-data="{
    mobileOpen: false, collapsed: false, desktop: window.matchMedia('(min-width: 1024px)').matches,
    closeMenu() { this.mobileOpen = false; this.$nextTick(() => this.$refs.menuToggle.focus()); },
    toggleMenu() {
        if (window.matchMedia('(min-width: 1024px)').matches) { this.collapsed = !this.collapsed; }
        else { this.mobileOpen = !this.mobileOpen; if (this.mobileOpen) this.$nextTick(() => this.$refs.closeNavigation.focus()); }
    },
    trapNavigation(event) {
        if (!this.mobileOpen || window.matchMedia('(min-width: 1024px)').matches) return;
        const items = [...this.$refs.navigation.querySelectorAll('a[href], button')].filter(el => el.getClientRects().length);
        const first = items[0], last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
}" :class="{ 'is-collapsed': collapsed, 'is-mobile-open': mobileOpen }"
     @keydown.escape.window="if (mobileOpen) closeMenu()"
     @resize.window.debounce.150ms="desktop = window.matchMedia('(min-width: 1024px)').matches; if (desktop) mobileOpen = false">
    <a href="#dashboard-content" class="dash-skip">Saltar al contenido</a>
    <div class="dash-scrim" @click="closeMenu()" aria-hidden="true"></div>
    <aside id="dashboard-navigation" class="dash-sidebar" x-ref="navigation" @keydown.tab="trapNavigation($event)" aria-label="Navegación de la tienda">
        <div class="dash-brand-row">
            <a href="{{ route('dashboard.index') }}" class="dash-brand" aria-label="Tribio, inicio">
                <span class="dash-brand-mark">t<span>.</span></span><span class="dash-nav-label">tribio<span class="dash-brand-dot">.</span></span>
            </a>
            <button type="button" class="dash-icon-button dash-close" x-ref="closeNavigation" @click="closeMenu()" aria-label="Cerrar navegación"><x-dashboard-icon name="close"/></button>
        </div>
        @if($dashboardStore)
        <a href="{{ route('dashboard.store.edit') }}" class="dash-store-switch" title="Configurar {{ $dashboardStore->name }}">
            <span class="dash-store-avatar">
                @if($dashboardStore->logo_path)<img src="{{ $dashboardStore->logo_url }}" alt="">@else<x-dashboard-icon name="store"/>@endif
            </span>
            <span class="dash-nav-label min-w-0"><strong>{{ $dashboardStore->name }}</strong><small>Tu espacio de gestión</small></span>
        </a>
        @endif
        <nav class="dash-nav" aria-label="Secciones">
            @php
                $navItems = [
                    ['dashboard.index', 'grid', 'Dashboard', 'Resumen'],
                    ['dashboard.productos.index', 'box', 'Productos', 'Catálogo'],
                    ['dashboard.categorias.index', 'folder', 'Categorías', null],
                    ['dashboard.marcas.index', 'tag', 'Marcas', null],
                    ['dashboard.galeria.index', 'image', 'Galería', null],
                    ['dashboard.pedidos.index', 'cart', 'Pedidos', 'Operación'],
                    ['dashboard.inventario.index', 'inventory', 'Inventario', null],
                    ['dashboard.shipping.index', 'truck', 'Zonas de envío', null],
                    ['dashboard.store.edit', 'store', 'Mi tienda', 'Personalización'],
                ];
            @endphp
            @foreach($navItems as [$route, $icon, $label, $group])
                @if($group)<p class="dash-nav-group dash-nav-label">{{ $group }}</p>@endif
                @php $active = request()->routeIs($route === 'dashboard.index' ? $route : substr($route, 0, strrpos($route, '.')) . '.*'); @endphp
                <a href="{{ route($route) }}" class="sidebar-link {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif title="{{ $label }}">
                    <x-dashboard-icon :name="$icon"/><span class="dash-nav-label">{{ $label }}</span>
                    @if($active)<span class="dash-active-dot dash-nav-label"></span>@endif
                </a>
            @endforeach
        </nav>
        <div class="dash-sidebar-footer">
            @if($dashboardStore)
            <a href="{{ route('store.show', $dashboardStore->slug) }}" target="_blank" rel="noopener" class="sidebar-link" title="Ver mi tienda (nueva pestaña)"><x-dashboard-icon name="external"/><span class="dash-nav-label">Ver mi tienda</span></a>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button type="submit" class="sidebar-link w-full" title="Cerrar sesión"><x-dashboard-icon name="logout"/><span class="dash-nav-label">Cerrar sesión</span></button>
            </form>
        </div>
    </aside>
    <div class="dash-main" :inert="mobileOpen">
        <header class="dash-topbar">
            <button type="button" class="dash-icon-button" x-ref="menuToggle" @click="toggleMenu()" aria-label="Alternar navegación" aria-controls="dashboard-navigation" :aria-expanded="desktop ? !collapsed : mobileOpen"><x-dashboard-icon name="menu"/></button>
            <form method="GET" action="{{ route('dashboard.productos.index') }}" class="dash-search" role="search">
                <label for="dashboard-search" class="sr-only">Buscar productos en mi tienda</label>
                <x-dashboard-icon name="search"/>
                <input id="dashboard-search" name="search" type="search" placeholder="Buscar en tus productos…" value="{{ request()->routeIs('dashboard.productos.index') ? request('search') : '' }}">
                <button type="submit" aria-label="Buscar productos"><x-dashboard-icon name="chevron"/></button>
            </form>
            <div class="dash-account" x-data="{ open: false }" @keydown.escape.stop="open = false; $refs.accountToggle.focus()">
                <button type="button" x-ref="accountToggle" @click="open = !open" :aria-expanded="open" aria-controls="dashboard-account" class="dash-account-button" aria-label="Menú de mi cuenta">
                    <span class="dash-avatar">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
                    <span class="dash-account-name"><strong>{{ Auth::user()->name }}</strong><small>Mi cuenta</small></span>
                    <x-dashboard-icon name="chevron" class="dash-account-chevron"/>
                </button>
                <div id="dashboard-account" x-cloak x-show="open" @click.outside="open = false" class="dash-account-menu">
                    <a href="{{ route('dashboard.store.edit') }}">Configurar tienda</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Cerrar sesión</button></form>
                </div>
            </div>
        </header>
        <main id="dashboard-content" class="dash-content" tabindex="-1">
            <div class="dash-page-heading"><div><p>Mi espacio / @yield('title', 'Dashboard')</p><h1>@yield('page_title', 'Dashboard')</h1></div><span class="dash-date">{{ now()->locale('es')->translatedFormat('d M, Y') }}</span></div>
            @foreach(['success' => 'status', 'info' => 'status', 'error' => 'alert'] as $messageType => $messageRole)
                @if(session($messageType))
                <div class="dash-flash dash-flash-{{ $messageType }}" role="{{ $messageRole }}" x-data="{ show: true }" x-show="show">
                    <span>{{ session($messageType) }}</span><button type="button" @click="show = false" aria-label="Cerrar mensaje"><x-dashboard-icon name="close"/></button>
                </div>
                @endif
            @endforeach
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
