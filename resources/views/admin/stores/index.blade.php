@extends('layouts.admin')
@section('title', 'Tiendas')
@section('page_title', '🏪 Gestión de Tiendas')
@section('content')
<div class="glass-card p-6">
    <div class="flex items-center gap-4 mb-6">
        <form method="GET" class="flex-1 flex gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar tienda..."
                   class="input-field max-w-xs">
            <select name="status" class="input-field max-w-xs">
                <option value="">Todos los estados</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Activas</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Borrador</option>
                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspendidas</option>
            </select>
            <button type="submit" class="btn-primary px-5">Filtrar</button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b" style="border-color: rgba(255,255,255,0.08);">
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Tienda</th>
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Propietario</th>
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Estado</th>
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Plan</th>
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Productos</th>
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Pedidos</th>
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Creada</th>
                    <th class="text-right py-3 px-3 text-white/50 font-medium">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y" style="border-color: rgba(255,255,255,0.04);">
                @foreach($stores as $store)
                <tr class="hover:bg-white/3 transition-colors">
                    <td class="py-3 px-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-tribio-purple/20 flex items-center justify-center overflow-hidden flex-shrink-0">
                                @if($store->logo_path)
                                    <img src="{{ $store->logo_url }}" class="w-full h-full object-cover">
                                @else
                                    🏪
                                @endif
                            </div>
                            <div>
                                <p class="text-white font-medium">{{ $store->name }}</p>
                                <p class="text-white/40 text-xs">/tienda/{{ $store->slug }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-3">
                        <p class="text-white/80">{{ $store->user->name }}</p>
                        <p class="text-white/40 text-xs">{{ $store->user->email }}</p>
                    </td>
                    <td class="py-3 px-3">
                        <form method="POST" action="{{ route('admin.tiendas.status', $store) }}">
                            @csrf @method('PATCH')
                            <select name="status" onchange="this.form.submit()" class="text-xs px-2 py-1 rounded-lg bg-white/5 border border-white/10 text-white">
                                <option value="active" {{ $store->status === 'active' ? 'selected' : '' }}>✅ Activa</option>
                                <option value="draft" {{ $store->status === 'draft' ? 'selected' : '' }}>📝 Borrador</option>
                                <option value="suspended" {{ $store->status === 'suspended' ? 'selected' : '' }}>⛔ Suspendida</option>
                                <option value="cancelled" {{ $store->status === 'cancelled' ? 'selected' : '' }}>❌ Cancelada</option>
                            </select>
                        </form>
                    </td>
                    <td class="py-3 px-3">
                        <span class="badge badge-{{ $store->plan === 'enterprise' ? 'gold' : ($store->plan === 'professional' ? 'purple' : 'gray') }}">
                            {{ ucfirst($store->plan) }}
                        </span>
                    </td>
                    <td class="py-3 px-3 text-white/70">{{ $store->products_count }}</td>
                    <td class="py-3 px-3 text-white/70">{{ $store->orders_count }}</td>
                    <td class="py-3 px-3 text-white/40 text-xs">{{ $store->created_at->format('d/m/Y') }}</td>
                    <td class="py-3 px-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.tiendas.show', $store) }}" class="btn-ghost py-1 px-3 text-xs">Ver</a>
                            <form method="POST" action="{{ route('admin.tiendas.featured', $store) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-xs px-3 py-1 rounded-lg border border-white/10 hover:bg-white/5 text-white/50 hover:text-white transition-colors">
                                    {{ $store->is_featured ? '⭐ Quitar' : '☆ Destacar' }}
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $stores->links() }}
    </div>
</div>
@endsection
