@extends('layouts.public')
@section('title', 'Galería — ' . $store->name)
@section('content')
<div class="min-h-screen hero-bg pt-24 pb-16">
    <div class="container-tribio">
        <h1 class="text-3xl font-bold text-white mb-8">Galería de {{ $store->name }}</h1>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($galleryItems as $item)
            <div class="aspect-square rounded-2xl overflow-hidden">
                <img src="{{ $item->image_url }}" alt="{{ $item->title }}" class="w-full h-full object-cover hover:scale-105 transition-transform">
            </div>
            @endforeach
        </div>
    </div>
</div>

<button onclick="window.dispatchEvent(new CustomEvent('open-cart-drawer'))"
        class="fixed bottom-6 left-6 z-40 w-14 h-14 rounded-full flex items-center justify-center text-white shadow-2xl hover:scale-105 transition-all"
        style="background: {{ $store->accent_color }};" aria-label="Carrito">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
    <span data-cart-count class="absolute -top-1 -right-1 bg-white text-gray-900 text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow">0</span>
</button>
@include('components.checkout.gateway')
@endsection
