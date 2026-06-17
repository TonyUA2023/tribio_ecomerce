@extends('layouts.public')
@section('title', '¡Pedido confirmado! — ' . $store->name)
@section('content')
<div class="min-h-screen hero-bg pt-24 pb-16 flex items-center">
    <div class="container-tribio">
        <div class="glass-card p-10 max-w-lg mx-auto text-center">
            <div class="text-6xl mb-6">🎉</div>
            <h1 class="text-2xl font-black text-white mb-3">¡Pedido Confirmado!</h1>
            <p class="text-white/60 mb-2">Pedido <span class="text-tribio-cyan font-bold">#{{ $order->order_number }}</span></p>
            <p class="text-white/60 mb-8">Pronto nos comunicaremos contigo por WhatsApp.</p>
            @if($store->whatsapp_phone)
            <a href="{{ $store->whatsapp_link }}" target="_blank" class="btn-whatsapp">
                Contactar por WhatsApp
            </a>
            @endif
            <a href="{{ route('store.show', $store->slug) }}" class="btn-secondary mt-3">Seguir comprando</a>
        </div>
    </div>
</div>
@endsection
