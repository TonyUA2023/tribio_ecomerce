@extends('layouts.dashboard')
@section('title', 'Listas de precios')
@section('page_title', '🏷️ Listas de precios')
@section('content')
<div class="w-full max-w-[1600px] mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Precio por menor, por mayor y para distribuidores. En la caja se usa la lista del cliente según su tipo (en su ficha); si un precio por cantidad es más bajo, gana ese.</p>
        <a href="{{ route('dashboard.pos.index', ['cajas' => 1]) }}" class="text-xs text-tribio-cyan underline">← Caja</a>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('dashboard.price-lists.percent') }}" class="glass-card p-4 flex items-end gap-3 flex-wrap">
        @csrf
        <div>
            <label class="input-label" for="pct-list">Llenar la lista</label>
            <select id="pct-list" name="list" class="input-field">@foreach($lists as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
        </div>
        <div>
            <label class="input-label" for="pct-value">con el precio por menor −</label>
            <div class="flex items-center gap-1"><input id="pct-value" name="percent" type="number" step="0.5" min="0" max="90" class="input-field !w-24" value="15" required><span class="text-white/60 text-sm">%</span></div>
        </div>
        <label class="flex items-center gap-2 text-xs text-white/70 pb-2"><input type="checkbox" name="overwrite" value="1"> Reemplazar también los que ya tienen precio</label>
        <button type="submit" class="btn-secondary" onclick="return confirm('¿Calcular los precios de esa lista?')">Calcular</button>
    </form>

    <form method="GET" action="{{ route('dashboard.price-lists.index') }}" role="search" class="flex justify-end">
        <label for="pl-search" class="sr-only">Buscar producto</label>
        <input id="pl-search" name="q" value="{{ request('q') }}" class="input-field !py-1 !text-xs w-52" placeholder="Buscar producto o SKU">
    </form>

    <form method="POST" action="{{ route('dashboard.price-lists.update') }}" class="glass-card overflow-hidden">
        @csrf @method('PUT')
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-white/50 border-b border-white/10">
                        <th class="p-3 font-normal">Producto</th>
                        <th class="p-3 font-normal text-right whitespace-nowrap">Menor</th>
                        @foreach($lists as $label)<th class="p-3 font-normal text-right whitespace-nowrap">{{ $label }}</th>@endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse($products as $product)
                        <tr>
                            <td class="p-3 text-white min-w-[10rem]">{{ $product->name }}@if($product->sku)<span class="block text-[11px] text-white/40">{{ $product->sku }}</span>@endif</td>
                            <td class="p-3 text-right text-white/70 whitespace-nowrap">S/ {{ number_format((float) $product->price, 2) }}</td>
                            @foreach($lists as $key => $label)
                                <td class="p-2 text-right">
                                    <label class="sr-only" for="p-{{ $product->id }}-{{ $key }}">{{ $label }} de {{ $product->name }}</label>
                                    <input id="p-{{ $product->id }}-{{ $key }}" name="rows[{{ $product->id }}][{{ $key }}]" type="number" step="0.01" min="0.01"
                                           class="input-field !py-1 !w-28 text-right ml-auto" value="{{ isset($prices[$product->id][$key]) ? number_format($prices[$product->id][$key], 2, '.', '') : '' }}" placeholder="—">
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ 2 + count($lists) }}" class="p-6 text-center text-white/60">No hay productos con ese nombre.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->isNotEmpty())
            <div class="p-3 flex items-center justify-between gap-3 flex-wrap border-t border-white/10">
                <p class="text-[11px] text-white/50">Deja vacío para vender a ese cliente con el precio por menor.</p>
                <button type="submit" class="btn-primary">Guardar precios</button>
            </div>
        @endif
    </form>
    @if($products->hasPages())<div>{{ $products->links() }}</div>@endif
</div>
@endsection
