@extends('layouts.dashboard')
@section('title', 'Compras')
@section('page_title', '🧾 Compras')
@section('content')
@php
    use App\Models\PurchaseOrder;
    $chipBase = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold transition whitespace-nowrap';
    $chipOn   = 'bg-tribio-cyan/10 border-tribio-cyan/40 text-tribio-cyan';
    $chipOff  = 'bg-white/5 border-white/10 text-white/60 hover:border-white/20';
@endphp
<div class="w-full max-w-[1600px] mx-auto">

@unless($ready)
    <div class="glass-card p-4 sm:p-5">
        <p class="text-white font-bold text-sm">⏳ Estamos activando este módulo</p>
        <p class="text-xs text-white/50 mt-1">Falta una actualización en el servidor de Tribio. Apenas esté lista podrás registrar tus compras aquí; no tienes que hacer nada.</p>
    </div>
@else
    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <p class="text-sm text-white/60 max-w-2xl">Lo que le compras a tus proveedores. Al recibir una compra, el stock sube solo y cada producto queda con su costo real, flete incluido.</p>
        <div class="flex gap-2">
            <a href="{{ route('dashboard.proveedores.index') }}" class="btn-secondary text-xs sm:text-sm">Proveedores</a>
            <a href="{{ route('dashboard.compras.create') }}" class="btn-primary text-xs sm:text-sm">+ Nueva compra</a>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-5">
        <a href="{{ route('dashboard.compras.index') }}" class="{{ $chipBase }} {{ !$status ? $chipOn : $chipOff }}">Todas <span class="opacity-70">{{ $counts->sum() }}</span></a>
        @foreach(PurchaseOrder::STATUSES as $key => $label)
            <a href="{{ route('dashboard.compras.index', ['estado' => $key]) }}" class="{{ $chipBase }} {{ $status === $key ? $chipOn : $chipOff }}">{{ $label }} <span class="opacity-70">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </div>

    @if($orders->isEmpty())
        <div class="glass-card p-6 text-center">
            <p class="text-white font-semibold">{{ $status ? 'No hay compras en este estado.' : 'Aún no registras compras.' }}</p>
            <p class="text-xs text-white/50 mt-1">Registra lo que pides a tus proveedores para saber cuánto te cuesta cada producto.</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($orders as $order)
            <a href="{{ route('dashboard.compras.show', $order->id) }}" class="glass-card p-3.5 sm:p-4 flex items-center justify-between gap-3 hover:border-tribio-cyan/40 transition">
                <div class="min-w-0">
                    <p class="text-sm text-white font-semibold">{{ $order->number }} <span class="text-white/50 font-normal">· {{ $order->supplier?->name ?? 'Sin proveedor' }}</span></p>
                    <p class="text-[11px] text-white/40">{{ $order->items_count }} {{ $order->items_count === 1 ? 'producto' : 'productos' }} · {{ $order->created_at->locale('es')->isoFormat('D MMM YYYY') }}@if($order->expected_at && $order->status === 'ordered') · llega {{ $order->expected_at->locale('es')->isoFormat('D MMM') }}@endif</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm text-white font-semibold whitespace-nowrap">S/ {{ number_format((float) $order->total, 2) }}</p>
                    <span class="badge {{ $order->status_badge }}">{{ $order->status_label }}</span>
                </div>
            </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
@endunless
</div>
@endsection
