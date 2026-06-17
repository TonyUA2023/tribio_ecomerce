@extends('layouts.dashboard')
@section('title', 'Productos')
@section('page_title', '📦 Productos')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-white font-bold text-lg">{{ $products->total() }} productos</h2>
    <a href="{{ route('dashboard.productos.create') }}" class="btn-primary">+ Nuevo producto</a>
</div>
<div class="glass-card overflow-hidden">
    @if($products->isEmpty())
    <div class="text-center py-16">
        <p class="text-5xl mb-4">📦</p>
        <p class="text-white font-bold">Sin productos aún</p>
        <a href="{{ route('dashboard.productos.create') }}" class="btn-primary mt-4">+ Agregar producto</a>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b" style="border-color: rgba(255,255,255,0.08);">
                <th class="text-left py-3 px-4 text-white/50">Producto</th>
                <th class="text-left py-3 px-4 text-white/50">Precio</th>
                <th class="text-left py-3 px-4 text-white/50">Stock</th>
                <th class="text-left py-3 px-4 text-white/50">Estado</th>
                <th class="text-right py-3 px-4 text-white/50">Acciones</th>
            </tr></thead>
            <tbody>
                @foreach($products as $product)
                <tr class="border-b hover:bg-white/3 transition-colors" style="border-color: rgba(255,255,255,0.04);">
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-tribio-purple/20 flex items-center justify-center overflow-hidden flex-shrink-0">
                                @if($product->image_path) <img src="{{ $product->image_url }}" class="w-full h-full object-cover">
                                @else <span class="text-lg">📦</span> @endif
                            </div>
                            <div>
                                <p class="text-white font-medium">{{ $product->name }}</p>
                                <p class="text-white/40 text-xs">{{ $product->category?->name }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-4">
                        <p class="text-tribio-gold font-bold">S/. {{ number_format($product->price, 2) }}</p>
                        @if($product->compare_price) <p class="text-white/30 text-xs line-through">S/. {{ number_format($product->compare_price, 2) }}</p> @endif
                    </td>
                    <td class="py-3 px-4">
                        @if($product->track_stock)
                            <span class="{{ $product->stock === 0 ? 'badge badge-red' : ($product->isLowStock() ? 'badge badge-gold' : 'badge badge-green') }}">{{ $product->stock }}</span>
                        @else
                            <span class="text-white/40 text-xs">Sin control</span>
                        @endif
                    </td>
                    <td class="py-3 px-4">
                        <span class="badge {{ $product->is_active ? 'badge-green' : 'badge-gray' }}">{{ $product->is_active ? 'Activo' : 'Inactivo' }}</span>
                        @if($product->is_featured) <span class="badge badge-gold ml-1">⭐</span> @endif
                    </td>
                    <td class="py-3 px-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('dashboard.productos.edit', $product) }}" class="btn-ghost py-1 px-3 text-xs">Editar</a>
                            <form method="POST" action="{{ route('dashboard.productos.destroy', $product) }}" onsubmit="return confirm('¿Eliminar este producto?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-ghost py-1 px-3 text-xs text-red-400 border-red-400/20 hover:bg-red-400/10">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $products->links() }}</div>
    @endif
</div>
@endsection
