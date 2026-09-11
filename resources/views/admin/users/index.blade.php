@extends('layouts.admin')
@section('title', 'Usuarios')
@section('page_title', '👥 Usuarios')
@section('content')
<div class="glass-card p-6">
    <form method="GET" class="flex gap-3 mb-6">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar usuario..." class="input-field max-w-xs">
        <select name="role" class="input-field max-w-xs">
            <option value="">Todos los roles</option>
            <option value="store_owner" {{ request('role') === 'store_owner' ? 'selected' : '' }}>Vendedor</option>
            <option value="super_admin" {{ request('role') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
            <option value="cliente" {{ request('role') === 'cliente' ? 'selected' : '' }}>Cliente (Comprador)</option>
        </select>
        <button type="submit" class="btn-primary px-5">Filtrar</button>
    </form>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b" style="border-color: rgba(255,255,255,0.08);">
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Usuario</th>
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Rol</th>
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Tienda</th>
                    <th class="text-left py-3 px-3 text-white/50 font-medium">Registrado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr class="border-b hover:bg-white/3 transition-colors" style="border-color: rgba(255,255,255,0.04);">
                    <td class="py-3 px-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-tribio-purple to-tribio-pink flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                {{ substr($user->name, 0, 1) }}
                            </div>
                            <div>
                                <p class="text-white font-medium">{{ $user->name }}</p>
                                <p class="text-white/40 text-xs">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-3">
                        @if($user->isSuperAdmin())
                            <span class="badge badge-red">🛡 Super Admin</span>
                        @elseif($user->isStoreOwner())
                            <span class="badge badge-purple">🏪 Vendedor</span>
                        @elseif($user->isCliente())
                            <span class="badge badge-cyan">🛍 Cliente</span>
                        @else
                            <span class="badge badge-purple">{{ ucfirst($user->role) }}</span>
                        @endif
                    </td>
                    <td class="py-3 px-3">
                        @if($user->store)
                            <a href="{{ route('admin.tiendas.show', $user->store) }}" class="text-tribio-cyan hover:text-white text-sm transition-colors">
                                {{ $user->store->name }}
                            </a>
                        @else
                            <span class="text-white/30 text-sm">Sin tienda</span>
                        @endif
                    </td>
                    <td class="py-3 px-3 text-white/40 text-xs">{{ $user->created_at->format('d/m/Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $users->links() }}</div>
</div>
@endsection
