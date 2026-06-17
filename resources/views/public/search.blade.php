@extends('layouts.public')
@section('title','Buscar — Tribio')
@section('content')
<div class="min-h-screen hero-bg pt-24 pb-16">
    <div class="container-tribio">
        <h1 class="text-3xl font-bold text-white mb-8">Resultados para: "{{ $query }}"</h1>
        @if($stores->isEmpty())
        <div class="glass-card p-12 text-center"><p class="text-5xl mb-4">🔍</p><p class="text-white/60">Sin resultados. Intenta con otro término.</p></div>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($stores as $store)
            <a href="{{ route('store.show', $store->slug) }}" class="store-card p-5">
                <p class="text-white font-bold">{{ $store->name }}</p>
                <p class="text-white/40 text-sm mt-1">{{ $store->active_products_count }} productos</p>
            </a>@endforeach
        </div>
        <div class="mt-8">{{ $stores->links() }}</div>
        @endif
    </div>
</div>
@endsection
