@extends('layouts.dashboard')
@section('title', 'Pedidos')
@section('page_title', '🛒 Pedidos')
@section('content')
@php
    $statusLabels = [
        'pending'    => 'Pendientes',
        'confirmed'  => 'Confirmados',
        'processing' => 'En proceso',
        'shipped'    => 'Enviados',
        'delivered'  => 'Entregados',
        'cancelled'  => 'Cancelados',
    ];
    $activeStatus = request('status');
    $chipBase = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold transition whitespace-nowrap';
    $chipOn   = 'bg-tribio-cyan/10 border-tribio-cyan/40 text-tribio-cyan';
    $chipOff  = 'bg-white/5 border-white/10 text-white/60 hover:border-white/20';
@endphp

{{-- Buscador --}}
<form method="GET" action="{{ route('dashboard.pedidos.index') }}" class="flex gap-2 mb-4">
    @if($activeStatus)<input type="hidden" name="status" value="{{ $activeStatus }}">@endif
    <input type="search" name="search" value="{{ $search }}" class="input-field flex-1 min-w-0"
           placeholder="N° de pedido, cliente, celular o DNI">
    <button type="submit" class="btn-primary flex-shrink-0">Buscar</button>
</form>

{{-- Filtros por estado --}}
<div class="flex flex-wrap gap-2 mb-5">
    <a href="{{ route('dashboard.pedidos.index', array_filter(['search' => $search])) }}"
       class="{{ $chipBase }} {{ !$activeStatus ? $chipOn : $chipOff }}">
        Todos <span class="opacity-70">{{ $totalCount }}</span>
    </a>
    @foreach($statusLabels as $key => $label)
    <a href="{{ route('dashboard.pedidos.index', array_filter(['status' => $key, 'search' => $search])) }}"
       class="{{ $chipBase }} {{ $activeStatus === $key ? $chipOn : $chipOff }}">
        {{ $label }} <span class="opacity-70">{{ $statusCounts[$key] ?? 0 }}</span>
    </a>
    @endforeach
</div>

<div class="glass-card overflow-hidden">
    @if($orders->isEmpty())
    <div class="text-center py-16 px-6">
        <p class="text-5xl mb-4">🛒</p>
        @if($search !== '' || $activeStatus)
            <p class="text-white font-bold">No hay pedidos que coincidan</p>
            <p class="text-white/40 text-sm mt-1">Prueba con otro filtro o término de búsqueda.</p>
            <a href="{{ route('dashboard.pedidos.index') }}" class="btn-ghost mt-4 inline-flex">Ver todos los pedidos</a>
        @else
            <p class="text-white font-bold">Aún no tienes pedidos</p>
            <p class="text-white/40 text-sm mt-1">Cuando un cliente compre en tu tienda, su pedido aparecerá aquí con todos sus datos de envío.</p>
        @endif
    </div>
    @else

    {{-- Móvil: tarjetas apiladas --}}
    <div class="lg:hidden divide-y divide-white/5">
        @foreach($orders as $order)
        <a href="{{ route('dashboard.pedidos.show', $order) }}" class="block p-4 hover:bg-white/5 transition">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-white font-bold text-sm">#{{ $order->order_number }}</p>
                    <p class="text-white/40 text-xs">{{ $order->created_at->locale('es')->translatedFormat('d M Y · H:i') }}</p>
                </div>
                <span class="badge {{ $order->status_badge }} flex-shrink-0">{{ $order->status_label }}</span>
            </div>
            <p class="text-white text-sm font-semibold mt-2 truncate">{{ $order->customer_name }}</p>
            <p class="text-white/50 text-xs truncate">
                {{ collect([$order->customer_city, $order->customer_state])->filter()->join(', ') ?: 'Sin ciudad' }}
                · {{ (int) $order->items_sum_quantity }} {{ (int) $order->items_sum_quantity === 1 ? 'producto' : 'productos' }}
            </p>
            <div class="flex items-center justify-between mt-3">
                <span class="badge {{ $order->payment_status_badge }}">{{ $order->payment_status_label }}</span>
                <span class="text-white font-black">{{ $order->money($order->total) }}</span>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Escritorio: tabla --}}
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b" style="border-color: rgba(255,255,255,0.08);">
                <th class="text-left py-3 px-4 text-white/50">Pedido</th>
                <th class="text-left py-3 px-4 text-white/50">Cliente</th>
                <th class="text-left py-3 px-4 text-white/50">Destino</th>
                <th class="text-left py-3 px-4 text-white/50">Pago</th>
                <th class="text-left py-3 px-4 text-white/50">Estado</th>
                <th class="text-right py-3 px-4 text-white/50">Total</th>
                <th class="py-3 px-4"></th>
            </tr></thead>
            <tbody>
                @foreach($orders as $order)
                <tr class="border-b hover:bg-white/3 transition-colors cursor-pointer" style="border-color: rgba(255,255,255,0.04);"
                    onclick="window.location='{{ route('dashboard.pedidos.show', $order) }}'">
                    <td class="py-3 px-4">
                        <p class="text-white font-bold whitespace-nowrap">#{{ $order->order_number }}</p>
                        <p class="text-white/40 text-xs">{{ $order->created_at->locale('es')->translatedFormat('d M Y · H:i') }}</p>
                    </td>
                    <td class="py-3 px-4">
                        <p class="text-white font-semibold">{{ $order->customer_name }}</p>
                        <p class="text-white/40 text-xs">{{ $order->customer_phone ?: $order->customer_email }}</p>
                    </td>
                    <td class="py-3 px-4">
                        <p class="text-white/80">{{ collect([$order->customer_city, $order->customer_state])->filter()->join(', ') ?: '—' }}</p>
                        <p class="text-white/40 text-xs">{{ (int) $order->items_sum_quantity }} {{ (int) $order->items_sum_quantity === 1 ? 'producto' : 'productos' }}</p>
                    </td>
                    <td class="py-3 px-4">
                        <p class="text-white/80 text-xs mb-1">{{ $order->payment_method_label }}</p>
                        <span class="badge {{ $order->payment_status_badge }}">{{ $order->payment_status_label }}</span>
                    </td>
                    <td class="py-3 px-4"><span class="badge {{ $order->status_badge }}">{{ $order->status_label }}</span></td>
                    <td class="py-3 px-4 text-right text-white font-black whitespace-nowrap">{{ $order->money($order->total) }}</td>
                    <td class="py-3 px-4 text-right">
                        <a href="{{ route('dashboard.pedidos.show', $order) }}" class="text-tribio-cyan text-xs font-bold whitespace-nowrap">Ver detalle →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($orders->hasPages())
    <div class="p-4">{{ $orders->links() }}</div>
    @endif
    @endif
</div>
@endsection
