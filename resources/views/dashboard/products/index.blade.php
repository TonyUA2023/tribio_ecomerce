@extends('layouts.dashboard')
@section('title', 'Productos')
@section('page_title', '📦 Productos')
@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-white font-bold text-lg">{{ $products->total() }} productos</h2>
    <a href="{{ route('dashboard.productos.create') }}" class="btn-primary">+ Nuevo producto</a>
</div>

{{-- Widget de Videos Destacados en el Home (Máximo 3) --}}
@if(isset($homeVideoProducts) && $homeVideoProducts->count() > 0)
<div class="mb-6 p-4 rounded-2xl bg-slate-50 border border-slate-200">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
            <span class="text-base">🎬</span>
            <h3 class="text-white font-bold text-sm">Videos Destacados en el Home</h3>
            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full {{ $homeVideoProducts->count() >= 3 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' }}">
                {{ $homeVideoProducts->count() }}/3 Cupos Asignados
            </span>
        </div>
        <p class="text-xs text-slate-400">Se muestran en una sola fila después del Hero.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        @foreach($homeVideoProducts as $idx => $hProd)
        <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/10">
            <div class="flex items-center gap-2.5 overflow-hidden">
                <div class="relative w-10 h-10 rounded-lg overflow-hidden bg-black flex-shrink-0">
                    <video src="{{ $hProd->video_url }}" class="w-full h-full object-cover" muted playsinline></video>
                </div>
                <div class="truncate">
                    <p class="text-xs font-bold text-white truncate">{{ $hProd->name }}</p>
                    <p class="text-[10px] text-[#C8A68B] font-semibold">Slot {{ $idx + 1 }} • S/. {{ number_format($hProd->price, 2) }}</p>
                </div>
            </div>
            <form action="{{ route('dashboard.productos.toggle-home-video', $hProd->id) }}" method="POST" class="ml-2 flex-shrink-0">
                @csrf
                <button type="submit" class="text-[10px] px-2 py-1 rounded bg-white/10 hover:bg-red-500/20 text-slate-300 hover:text-red-300 font-bold transition cursor-pointer" title="Quitar de la portada">
                    ✕ Quitar
                </button>
            </form>
        </div>
        @endforeach

        @for($i = $homeVideoProducts->count(); $i < 3; $i++)
        <div class="p-2.5 rounded-xl border border-dashed border-white/15 bg-white/[0.02] flex items-center justify-center text-center">
            <span class="text-xs text-white/40 italic">Slot {{ $i + 1 }} disponible</span>
        </div>
        @endfor
    </div>
</div>
@endif

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
                                @if($product->categories && $product->categories->count() > 0)
                                    <div class="flex items-center gap-1 mt-0.5 flex-wrap">
                                        <span class="text-white/60 text-xs">{{ $product->categories->first()->icon ?? '' }} {{ $product->categories->first()->name }}</span>
                                        @if($product->categories->count() > 1)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-white/10 text-sky-400 font-bold cursor-default" title="{{ $product->categories->pluck('name')->implode(', ') }}">
                                                +{{ $product->categories->count() - 1 }}
                                            </span>
                                        @endif
                                    </div>
                                @elseif($product->category)
                                    <p class="text-white/40 text-xs">{{ $product->category->name }}</p>
                                @else
                                    <p class="text-white/20 text-xs italic">Sin categoría</p>
                                @endif
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
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="badge {{ $product->is_active ? 'badge-green' : 'badge-gray' }}">{{ $product->is_active ? 'Activo' : 'Inactivo' }}</span>
                            @if($product->is_featured) <span class="badge badge-gold">⭐</span> @endif
                            @if($product->video_path)
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded {{ $product->show_video_on_home ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-purple-500/20 text-purple-300 border border-purple-500/30' }}" title="{{ $product->show_video_on_home ? 'Video activo en el Home' : 'Tiene video' }}">
                                    <span>🎬</span>
                                    <span>{{ $product->show_video_on_home ? 'Home' : 'Video' }}</span>
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="py-3 px-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if($product->video_path)
                            <form action="{{ route('dashboard.productos.toggle-home-video', $product->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn-ghost py-1 px-2 text-xs font-bold transition {{ $product->show_video_on_home ? 'text-amber-400 border-amber-400/30 bg-amber-400/10' : 'text-slate-400 hover:text-white' }}" title="{{ $product->show_video_on_home ? 'Quitar del Home' : 'Destacar video en el Home' }}">
                                    <span>🎬</span>
                                    <span class="hidden sm:inline text-[11px]">{{ $product->show_video_on_home ? 'En Home' : '+ Home' }}</span>
                                </button>
                            </form>
                            @endif
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
