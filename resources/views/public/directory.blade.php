@extends('layouts.public')
@section('title','Directorio de Negocios — Tribio')
@section('content')
<div class="min-h-screen hero-bg pt-24 pb-16">
    <div class="container-tribio">
        <h1 class="text-3xl font-bold text-white mb-8">Todos los negocios</h1>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($stores as $store)
            <a href="{{ route('store.show', $store->slug) }}" class="store-card group">
                <div class="h-32 bg-gradient-to-br from-tribio-purple/20 to-tribio-pink/10 flex items-center justify-center text-4xl">
                    {{ config('tribio.business_categories')[$store->category]['icon'] ?? '🏪' }}
                </div>
                <div class="p-4">
                    <p class="text-white font-bold">{{ $store->name }}</p>
                    <p class="text-white/40 text-xs mt-1">{{ $store->active_products_count }} productos</p>
                </div>
            </a>@endforeach
        </div>
        <div class="mt-8">{{ $stores->links() }}</div>
    </div>
</div>
@endsection
