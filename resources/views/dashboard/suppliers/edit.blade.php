@extends('layouts.dashboard')
@section('title', $supplier->name . ' — Proveedores')
@section('page_title', '🧾 Proveedores')
@section('content')
<div class="w-full max-w-[1600px] mx-auto space-y-5">

    <form method="POST" action="{{ route('dashboard.proveedores.update', $supplier->id) }}" class="space-y-5">
        @csrf @method('PUT')
        <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
            <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
                <a href="{{ route('dashboard.proveedores.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a Proveedores" title="Volver a Proveedores">←</a>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Proveedor</p>
                    <p class="text-white font-bold text-sm truncate">{{ $supplier->name }}</p>
                </div>
                @if($supplier->whatsapp_url)
                    <a href="{{ $supplier->whatsapp_url }}" target="_blank" rel="noopener" class="btn-secondary flex-shrink-0 text-xs sm:text-sm">WhatsApp</a>
                @endif
                <button type="submit" class="btn-primary flex-shrink-0 text-xs sm:text-sm">Guardar</button>
            </div>
        </div>
        <div class="glass-card p-4 sm:p-6">
            @include('dashboard.suppliers._fields')
        </div>
    </form>

    <div class="glass-card p-4 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-white font-bold text-base">Compras a este proveedor</h3>
            <a href="{{ route('dashboard.compras.create', ['proveedor' => $supplier->id]) }}" class="btn-ghost text-xs">+ Nueva compra</a>
        </div>
        <div class="space-y-2 mt-3">
            @forelse($orders as $order)
            <a href="{{ route('dashboard.compras.show', $order->id) }}" class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3 hover:border-tribio-cyan/40 transition">
                <span class="text-sm text-white">{{ $order->number }} <span class="text-white/40 text-xs">· {{ $order->created_at->locale('es')->isoFormat('D MMM YYYY') }}</span></span>
                <span class="flex items-center gap-2 flex-shrink-0">
                    <span class="text-sm text-white font-semibold whitespace-nowrap">S/ {{ number_format((float) $order->total, 2) }}</span>
                    <span class="badge {{ $order->status_badge }}">{{ $order->status_label }}</span>
                </span>
            </a>
            @empty
                <p class="text-sm text-white/60">Aún no le compras nada.</p>
            @endforelse
        </div>
    </div>

    @if($orders->isEmpty())
    <form method="POST" action="{{ route('dashboard.proveedores.destroy', $supplier->id) }}" onsubmit="return confirm('¿Eliminar este proveedor?')">
        @csrf @method('DELETE')
        <button type="submit" class="text-xs text-white/40 hover:text-red-300">Eliminar proveedor</button>
    </form>
    @endif
</div>
@endsection
