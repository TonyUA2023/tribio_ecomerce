@extends('layouts.dashboard')
@section('title', 'Mi Dashboard')
@section('page_title', 'Hola, ' . Auth::user()->name)
@section('content')
<div class="dash-overview">
    <div class="dash-overview-main">
        <div class="dash-welcome"><p>Así va tu tienda. Todo lo que necesitas, en un solo lugar.</p><a href="{{ route('dashboard.productos.create') }}" class="btn-primary"><x-dashboard-icon name="plus"/> Nuevo producto</a></div>
        <div class="dash-metrics">
            @php
                $statCards = [
                    ['label' => 'Productos activos', 'value' => $stats['active_products'], 'icon' => 'box', 'sub' => $stats['total_products'] . ' productos en tu catálogo', 'route' => route('dashboard.productos.index', ['status' => 'active'])],
                    ['label' => 'Pedidos pendientes', 'value' => $stats['pending_orders'], 'icon' => 'cart', 'sub' => $stats['total_orders'] . ' pedidos en total', 'route' => route('dashboard.pedidos.index', ['status' => 'pending'])],
                    ['label' => 'Stock bajo', 'value' => $stats['low_stock_products'], 'icon' => 'inventory', 'sub' => $stats['out_of_stock'] . ' productos sin stock', 'route' => route('dashboard.inventario.index', ['filter' => 'low'])],
                ];
            @endphp
            @foreach($statCards as $card)
            <a href="{{ $card['route'] }}" class="dash-metric"><span class="dash-metric-top"><span>{{ $card['label'] }}</span><x-dashboard-icon :name="$card['icon']"/></span><strong>{{ $card['value'] }}</strong><small>{{ $card['sub'] }}</small></a>
            @endforeach
        </div>
        <section class="dash-section" aria-labelledby="top-products-heading">
            <div class="dash-section-heading"><div><h2 id="top-products-heading">Tus productos más vendidos</h2><p>Un vistazo a tu catálogo</p></div><a href="{{ route('dashboard.productos.index') }}" class="dash-text-link">Ver todos <x-dashboard-icon name="chevron"/></a></div>
            @if($topProducts->isEmpty())
            <div class="dash-empty"><x-dashboard-icon name="box"/><h3>Tu próximo gran producto empieza aquí</h3><p>Agrega tu primer producto para mostrarlo en tu tienda.</p><a href="{{ route('dashboard.productos.create') }}" class="btn-primary">Agregar producto</a></div>
            @else
            <div class="dash-product-strip" tabindex="0" aria-label="Productos más vendidos; desplaza para ver más">
                @foreach($topProducts as $product)
                <a href="{{ route('dashboard.productos.edit', $product) }}" class="dash-product">
                    <div class="dash-product-image">@if($product->image_path)<img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">@else<x-dashboard-icon name="box"/>@endif<span>{{ $product->sold_count }} ventas</span></div>
                    <h3>{{ $product->name }}</h3><div class="dash-product-price"><strong>S/. {{ number_format($product->price, 2) }}</strong><span>Editar <x-dashboard-icon name="chevron"/></span></div>
                </a>
                @endforeach
            </div>
            @endif
        </section>
        <section class="dash-section dash-orders" aria-labelledby="recent-orders-heading">
            <div class="dash-section-heading"><div><h2 id="recent-orders-heading">Pedidos recientes</h2><p>Sigue las últimas compras de tu tienda</p></div><a href="{{ route('dashboard.pedidos.index') }}" class="dash-text-link">Ver todos <x-dashboard-icon name="chevron"/></a></div>
            @if($recentOrders->isEmpty())
                <div class="dash-empty"><x-dashboard-icon name="cart"/><h3>Todo listo para tu primera venta</h3><p>Comparte el enlace de tu tienda con tus clientes para empezar.</p>@if($store)<a href="{{ route('store.show', $store->slug) }}" target="_blank" rel="noopener" class="btn-secondary">Ver mi tienda <x-dashboard-icon name="external"/></a>@endif</div>
            @else
            <div class="overflow-x-auto"><table class="dash-orders-table"><thead><tr><th>Cliente / Pedido</th><th>Fecha</th><th>Estado</th><th class="dash-amount">Importe</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody>
                @foreach($recentOrders as $order)
                <tr><td><a href="{{ route('dashboard.pedidos.show', $order) }}" class="dash-order-customer"><span class="dash-order-icon"><x-dashboard-icon name="cart"/></span><span><strong>{{ $order->customer_name }}</strong><small>{{ $order->order_number }}</small></span></a></td><td><time datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('d/m/Y') }}</time></td><td><span class="badge badge-{{ $order->status_color }}">{{ $order->status_label }}</span></td><td class="dash-amount">S/. {{ number_format($order->total, 2) }}</td><td><a href="{{ route('dashboard.pedidos.show', $order) }}" class="dash-icon-button" aria-label="Ver pedido {{ $order->order_number }}"><x-dashboard-icon name="chevron"/></a></td></tr>
                @endforeach
            </tbody></table></div>
            @endif
        </section>
    </div>
    <aside class="dash-summary" aria-label="Resumen de mi tienda">
        <section><h2>Balance de tu tienda</h2><div class="dash-balance"><div><span>Ingresos del mes</span><x-dashboard-icon name="wallet"/></div><strong>S/. {{ number_format($stats['revenue_month'], 2) }}</strong><p>{{ now()->locale('es')->translatedFormat('F Y') }}</p><footer><span>Ingresos acumulados</span><b>S/. {{ number_format($stats['revenue_total'], 2) }}</b></footer></div><p class="dash-balance-note">Importes de pedidos no cancelados.</p></section>
        <section><h2>Acciones rápidas</h2><div class="dash-shortcuts">
            @foreach([['dashboard.productos.create', 'box', 'Nuevo producto', 'Amplía tu catálogo'], ['dashboard.pedidos.index', 'cart', 'Ver pedidos', 'Consulta tus ventas'], ['dashboard.galeria.index', 'image', 'Subir fotos', 'Da vida a tu tienda'], ['dashboard.store.edit', 'store', 'Personalizar tienda', 'Hazla a tu estilo']] as [$route, $icon, $label, $description])
            <a href="{{ route($route) }}"><span class="dash-shortcut-icon"><x-dashboard-icon :name="$icon"/></span><span><strong>{{ $label }}</strong><small>{{ $description }}</small></span><x-dashboard-icon name="chevron"/></a>
            @endforeach
        </div></section>
        <section class="dash-attention"><h2>Para tener en cuenta</h2><a href="{{ route('dashboard.inventario.index', ['filter' => 'out']) }}"><span>Productos sin stock</span><strong>{{ $stats['out_of_stock'] }}</strong></a><a href="{{ route('dashboard.pedidos.index', ['status' => 'pending']) }}"><span>Pedidos pendientes</span><strong>{{ $stats['pending_orders'] }}</strong></a></section>
        @if($store)
        <section class="dash-store-status"><div><span class="dash-status-dot {{ $store->isActive() ? 'is-active' : '' }}"></span><strong>{{ $store->isActive() ? 'Tu tienda está activa' : 'Tu tienda no está activa' }}</strong></div><p>{{ $store->isActive() ? 'Abre tu tienda y revisa cómo la ven tus clientes.' : 'Estado actual: ' . $store->status . '. Contacta soporte si crees que esto es un error.' }}</p><a href="{{ $store->isActive() ? route('store.show', $store->slug) : route('dashboard.store.edit') }}" @if($store->isActive()) target="_blank" rel="noopener" @endif class="dash-text-link">{{ $store->isActive() ? 'Ver mi tienda' : 'Configurar tienda' }}<x-dashboard-icon name="external"/></a></section>
        @endif
    </aside>
</div>
@endsection
