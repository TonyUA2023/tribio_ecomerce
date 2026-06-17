@extends('layouts.admin')
@section('title','Usuario') @section('page_title','👤 Usuario')
@section('content')
<div class="glass-card p-6 max-w-lg">
    <div class="flex items-center gap-4 mb-6">
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-tribio-purple to-tribio-pink flex items-center justify-center text-white text-2xl font-black">{{ substr($user->name,0,1) }}</div>
        <div><p class="text-white font-bold text-xl">{{ $user->name }}</p><p class="text-white/50">{{ $user->email }}</p><span class="badge badge-{{ $user->isSuperAdmin() ? 'red' : 'purple' }} mt-1">{{ $user->role }}</span></div>
    </div>
    @if($user->store)
    <div class="border-t border-white/10 pt-4">
        <p class="text-white/50 text-sm mb-2">Tienda asociada:</p>
        <a href="{{ route('admin.tiendas.show', $user->store) }}" class="text-tribio-cyan hover:text-white transition-colors font-medium">{{ $user->store->name }}</a>
    </div>@endif
    <a href="{{ route('admin.usuarios.index') }}" class="btn-ghost mt-4 inline-flex">← Volver</a>
</div>
@endsection
