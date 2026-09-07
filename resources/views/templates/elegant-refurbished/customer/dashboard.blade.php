@extends('templates.elegant-refurbished.layout')

@section('title', 'Mi Cuenta')

@section('content')
<div class="er-container er-section" style="padding-top: var(--space-md);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg);">
        <h1 class="er-title-lg">Mi Cuenta</h1>
        <form action="{{ route('store.logout', ['tenant' => $tenant_domain ?? 'default']) }}" method="POST">
            @csrf
            <button type="submit" class="er-btn er-btn-secondary">Cerrar Sesión</button>
        </form>
    </div>

    <div class="er-grid er-grid-2">
        <!-- Personal Info Card -->
        <div style="background-color: var(--bg-secondary); border-radius: var(--radius-lg); padding: var(--space-md);">
            <h2 class="er-title-md" style="margin-bottom: var(--space-sm);">Datos Personales</h2>
            <div style="margin-bottom: 1rem;">
                <p style="color: var(--text-muted); font-size: 0.9rem;">Nombre</p>
                <p style="font-weight: 500;">{{ $customer->name ?? 'Usuario' }}</p>
            </div>
            <div style="margin-bottom: 1rem;">
                <p style="color: var(--text-muted); font-size: 0.9rem;">Correo Electrónico</p>
                <p style="font-weight: 500;">{{ $customer->email ?? 'correo@ejemplo.com' }}</p>
            </div>
            
            <button class="er-btn er-btn-secondary" style="margin-top: 1rem;">Editar Datos</button>
        </div>

        <!-- Orders Card -->
        <div style="background-color: var(--bg-secondary); border-radius: var(--radius-lg); padding: var(--space-md);">
            <h2 class="er-title-md" style="margin-bottom: var(--space-sm);">Mis Pedidos</h2>
            
            @if(isset($orders) && count($orders) > 0)
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 1rem;">
                    @foreach($orders as $order)
                        <li style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 600;">Pedido #{{ $order->id }}</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">{{ $order->created_at->format('d/m/Y') }}</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 600;">{{ $order->total_formatted }}</div>
                                <span class="er-product-badge" style="margin-top: 0.2rem; background-color: var(--er-success); color: white;">{{ $order->status }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div style="text-align: center; padding: 2rem 0; color: var(--text-muted);">
                    <p style="margin-bottom: 1rem;">Aún no has realizado ninguna compra.</p>
                    <a href="{{ route('store.home', ['tenant' => $tenant_domain ?? 'default']) }}" class="er-btn er-btn-primary">Ver Catálogo</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
