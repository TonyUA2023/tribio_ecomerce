@extends('layouts.public')
@section('title', $product->name . ' — ' . $store->name)
@section('content')
<div class="min-h-screen hero-bg pt-24 pb-16">
    <div class="container-tribio">
        <div class="glass-card p-8 max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold text-white">{{ $product->name }}</h1>
            <p class="text-tribio-gold text-3xl font-black mt-4">S/. {{ number_format($product->price, 2) }}</p>
            @if($product->description)
            <p class="text-white/60 mt-4">{{ $product->description }}</p>
            @endif
            @if($store->whatsapp_phone)
            <a href="{{ $store->whatsapp_link }}" target="_blank" class="btn-whatsapp mt-6">Comprar por WhatsApp</a>
            @endif
            <a href="{{ route('store.show', $store->slug) }}" class="btn-ghost mt-3">← Volver a la tienda</a>
        </div>
    </div>
</div>
@endsection
