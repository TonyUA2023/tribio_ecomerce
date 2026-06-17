@extends('layouts.dashboard')
@section('title','Pedido') @section('page_title','Pedido #' . $order->order_number)
@section('content')
<div class="glass-card p-6">
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div><p class="text-white/40 text-xs">Cliente</p><p class="text-white font-bold">{{ $order->customer_name }}</p></div>
        <div><p class="text-white/40 text-xs">Teléfono</p><p class="text-white">{{ $order->customer_phone ?? '—' }}</p></div>
        <div><p class="text-white/40 text-xs">Total</p><p class="text-tribio-gold font-black text-xl">S/. {{ number_format($order->total,2) }}</p></div>
        <div><p class="text-white/40 text-xs">Estado</p><span class="badge badge-{{ $order->status_color }}">{{ $order->status_label }}</span></div>
    </div>
    <form method="POST" action="{{ route('dashboard.pedidos.status', $order) }}" class="flex gap-3">@csrf @method('PATCH')
        <select name="status" class="input-field max-w-xs">
            @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
            <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
        </select>
        <button type="submit" class="btn-primary">Actualizar</button>
    </form>
    @if($store->whatsapp_phone)
    <a href="{{ route('dashboard.pedidos.whatsapp', $order) }}" target="_blank" class="btn-whatsapp mt-4 inline-flex">Enviar WhatsApp</a>
    @endif
    <a href="{{ route('dashboard.pedidos.index') }}" class="btn-ghost mt-3 inline-flex">← Volver</a>
</div>
@endsection
