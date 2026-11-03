@extends('layouts.dashboard')
@section('title', $order->number . ' — Compras')
@section('page_title', '🧾 Compras')
@section('content')
@php $money = fn ($value) => 'S/ ' . number_format((float) $value, 2); @endphp
<div class="w-full max-w-4xl mx-auto space-y-5">

    <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
        <a href="{{ route('dashboard.compras.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a Compras" title="Volver a Compras">←</a>
        <div class="min-w-0 flex-1">
            <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Compra</p>
            <p class="text-white font-bold text-sm truncate">{{ $order->number }} · {{ $order->supplier?->name ?? 'Sin proveedor' }}</p>
        </div>
        <span class="badge {{ $order->status_badge }} flex-shrink-0">{{ $order->status_label }}</span>
        @if($order->isEditable())
            <a href="{{ route('dashboard.compras.edit', $order->id) }}" class="btn-secondary flex-shrink-0 text-xs sm:text-sm">Editar</a>
        @endif
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">
            @foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="glass-card p-3"><p class="text-[11px] text-white/40 uppercase tracking-wider">Productos</p><p class="text-sm text-white font-bold mt-1 whitespace-nowrap">{{ $money($order->subtotal) }}</p></div>
        <div class="glass-card p-3"><p class="text-[11px] text-white/40 uppercase tracking-wider">Flete y gastos</p><p class="text-sm text-white font-bold mt-1 whitespace-nowrap">{{ $money($order->extra_costs) }}</p></div>
        <div class="glass-card p-3"><p class="text-[11px] text-white/40 uppercase tracking-wider">Total</p><p class="text-sm text-white font-bold mt-1 whitespace-nowrap">{{ $money($order->total) }}</p></div>
        <div class="glass-card p-3">
            <p class="text-[11px] text-white/40 uppercase tracking-wider">{{ $order->received_at ? 'Recibida' : ($order->expected_at ? 'Llega' : 'Creada') }}</p>
            <p class="text-sm text-white font-bold mt-1">{{ ($order->received_at ?? $order->expected_at ?? $order->created_at)->locale('es')->isoFormat('D MMM YYYY') }}</p>
        </div>
    </div>

    @if($order->isEditable())
    {{-- Recibir --}}
    <form method="POST" action="{{ route('dashboard.compras.receive', $order->id) }}" class="glass-card p-4 sm:p-6 space-y-3">
        @csrf
        <div>
            <h3 class="text-white font-bold text-base">¿Llegó la mercadería?</h3>
            <p class="text-xs text-white/50 mt-1">
                Anota cuánto llegó de cada producto (0 si no llegó) y, si tiene, su lote y vencimiento. Al confirmar, sube el stock y cada producto queda con su costo real{{ (float) $order->extra_costs > 0 ? ', con su parte de los ' . $money($order->extra_costs) . ' de gastos' : '' }}.
            </p>
        </div>
        @foreach($order->items as $item)
        <div class="rounded-xl border border-white/10 bg-white/5 p-3">
            <p class="text-sm text-white font-semibold">{{ $item->product?->name ?? 'Producto eliminado' }} <span class="text-white/50 font-normal text-xs">· pediste {{ $item->quantity_label }} a {{ $money($item->unit_cost) }}</span></p>
            <div class="grid grid-cols-3 gap-2 mt-2">
                <div>
                    <label class="input-label" for="r-q-{{ $item->id }}">Llegaron{{ (int) ($item->unit_factor ?? 1) > 1 ? ' (' . $item->unit_name . ')' : '' }}</label>
                    <input id="r-q-{{ $item->id }}" type="number" min="0" name="lines[{{ $item->id }}][quantity]" class="input-field" value="{{ old("lines.{$item->id}.quantity", $item->quantity_ordered) }}" required>
                </div>
                <div>
                    <label class="input-label" for="r-l-{{ $item->id }}">Lote</label>
                    <input id="r-l-{{ $item->id }}" type="text" maxlength="40" name="lines[{{ $item->id }}][lot_code]" class="input-field" value="{{ old("lines.{$item->id}.lot_code") }}" placeholder="Opcional">
                </div>
                <div>
                    <label class="input-label" for="r-e-{{ $item->id }}">Vence</label>
                    <input id="r-e-{{ $item->id }}" type="date" name="lines[{{ $item->id }}][expires_at]" class="input-field" value="{{ old("lines.{$item->id}.expires_at") }}">
                </div>
            </div>
        </div>
        @endforeach
        <button type="submit" class="btn-primary text-xs sm:text-sm" onclick="return confirm('¿Confirmas que recibiste esta mercadería? El stock subirá ahora.')">Confirmar recepción</button>
    </form>

    <form method="POST" action="{{ route('dashboard.compras.cancel', $order->id) }}" onsubmit="return confirm('¿Cancelar esta compra?')">
        @csrf
        <button type="submit" class="text-xs text-white/40 hover:text-red-300">Cancelar esta compra</button>
    </form>
    @else
    {{-- Detalle --}}
    <div class="glass-card p-4 sm:p-6">
        <h3 class="text-white font-bold text-base mb-3">Productos</h3>
        <div class="space-y-2">
            @foreach($order->items as $item)
            <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    @if($item->product && !$item->product->trashed())
                        <a href="{{ route('dashboard.inventario.product', $item->product_id) }}" class="text-sm text-white font-semibold hover:text-tribio-cyan">{{ $item->product->name }}</a>
                    @else
                        <p class="text-sm text-white font-semibold">{{ $item->product?->name ?? 'Producto eliminado' }}</p>
                    @endif
                    <p class="text-[11px] text-white/50 mt-0.5">
                        Pediste {{ $item->quantity_label }} a {{ $money($item->unit_cost) }}
                        @if($item->quantity_received !== null) · llegaron {{ $item->quantity_received }} @endif
                        @if($item->lot_code) · lote {{ $item->lot_code }} @endif
                        @if($item->expires_at) · vence {{ $item->expires_at->locale('es')->isoFormat('D MMM YYYY') }} @endif
                    </p>
                </div>
                @if($item->landed_unit_cost !== null)
                <div class="text-right flex-shrink-0">
                    <p class="text-sm text-white font-semibold whitespace-nowrap">{{ $money((float) $item->landed_unit_cost / max(1, (int) ($item->unit_factor ?? 1))) }} c/u</p>
                    <p class="text-[11px] text-white/40">costo real por unidad</p>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    @if($order->notes)
        <div class="glass-card p-4"><p class="text-[11px] text-white/40 uppercase tracking-wider">Notas</p><p class="text-sm text-white/80 mt-1 whitespace-pre-line">{{ $order->notes }}</p></div>
    @endif
</div>
@endsection
