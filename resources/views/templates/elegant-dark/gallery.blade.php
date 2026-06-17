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
@endsection
