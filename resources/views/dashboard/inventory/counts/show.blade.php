@extends('layouts.dashboard')
@section('title', $count->number . ' — Conteo físico')
@section('page_title', '🧮 Conteo físico')
@section('content')
@php
    $rows = $items->map(fn ($item) => [
        'id'             => $item->id,
        'product_id'     => $item->product_id,
        'name'           => $item->product?->name ?? 'Producto eliminado',
        'sku'            => $item->product?->sku,
        'expected'       => (int) $item->expected_qty,
        'counted'        => $item->counted_qty,
        'variance'       => $item->variance,
        'variance_value' => $item->variance_value,
        'state'          => null,
    ])->values();
@endphp
<div class="w-full max-w-3xl mx-auto space-y-4"
     x-data="countSheet(@js($rows), @js(route('dashboard.inventario.conteos.record', $count->id)), {{ $count->blind ? 'true' : 'false' }}, {{ $count->isOpen() ? 'true' : 'false' }})">

    {{-- Barra fija: progreso --}}
    <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
        <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
            <a href="{{ route('dashboard.inventario.conteos.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a Conteos" title="Volver a Conteos">←</a>
            <div class="min-w-0 flex-1">
                <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">{{ $count->number }} · {{ $count->category?->name ?? 'Todos los productos' }}</p>
                <p class="text-white font-bold text-sm"><span x-text="counted()"></span> de {{ $items->count() }} contados</p>
            </div>
            <span class="badge {{ $count->status_badge }} flex-shrink-0">{{ $count->status_label }}</span>
        </div>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    {{-- Diferencias --}}
    <div class="glass-card p-4 sm:p-5" x-show="!open || showReview" x-cloak>
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-white font-bold text-base">Diferencias</h3>
            <p class="text-sm font-bold whitespace-nowrap" :class="totalValue() < 0 ? 'text-red-400' : 'text-white'" x-text="money(totalValue())"></p>
        </div>
        <p class="text-xs text-white/50 mt-1" x-show="differences().length === 0">Lo que contaste coincide con el sistema.</p>
        @if($count->isOpen())
            <p class="text-[11px] text-white/40 mt-1" x-show="differences().length > 0">Valor aproximado al costo actual. Al aplicar, lo que falta se valora al costo de los lotes que salen (los que vencen primero).</p>
        @endif
        <div class="space-y-2 mt-3">
            <template x-for="row in differences()" :key="row.id">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-white font-semibold truncate" x-text="row.name"></p>
                        <p class="text-[11px] text-white/50">Sistema <span x-text="row.expected"></span> · contado <span x-text="row.counted"></span></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-bold" :class="row.variance < 0 ? 'text-red-400' : 'text-white'" x-text="(row.variance > 0 ? '+' : '') + row.variance"></p>
                        <p class="text-[11px] text-white/50" x-text="row.variance_value === null ? 'sin costo' : money(row.variance_value)"></p>
                    </div>
                </div>
            </template>
        </div>
        @if($count->isOpen())
        <div class="flex items-center gap-3 flex-wrap mt-4">
            <form method="POST" action="{{ route('dashboard.inventario.conteos.apply', $count->id) }}" onsubmit="return confirm('¿Corregir el stock con lo que contaste? Los productos no contados no cambian.')">
                @csrf
                <button type="submit" class="btn-primary text-xs sm:text-sm">Aplicar al stock</button>
            </form>
            <button type="button" class="btn-ghost text-xs" @click="showReview = false">Seguir contando</button>
        </div>
        @endif
    </div>

    @if($count->isOpen())
    {{-- Hoja de conteo --}}
    <div class="glass-card p-4 sm:p-5 space-y-3" x-show="!showReview">
        <div class="flex gap-2">
            <input type="search" class="input-field flex-1 min-w-0" x-model="query" placeholder="Buscar…" aria-label="Buscar producto por nombre o código">
            <button type="button" class="btn-secondary flex-shrink-0 text-xs" @click="pending = !pending" x-text="pending ? 'Ver todos' : 'Solo pendientes'"></button>
        </div>
        <div class="space-y-2">
            <template x-for="row in visible()" :key="row.id">
                <div class="rounded-xl border border-white/10 bg-white/5 p-3 flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-white font-semibold truncate" x-text="row.name"></p>
                        <p class="text-[11px] text-white/40">
                            <span x-text="row.sku || 'Sin código'"></span>
                            <template x-if="!blind"><span> · sistema <span x-text="row.expected"></span></span></template>
                        </p>
                    </div>
                    <input type="number" min="0" inputmode="numeric" class="input-field !w-24 text-center" :aria-label="'Cantidad contada de ' + row.name"
                           :value="row.counted" @change="save(row, $event.target.value)">
                    <span class="w-5 text-center text-sm flex-shrink-0" aria-live="polite">
                        <span x-show="row.state === 'saving'" class="text-white/40">…</span>
                        <span x-show="row.state === 'saved' || (row.state === null && row.counted !== null)" class="text-tribio-cyan">✓</span>
                        <span x-show="row.state === 'error'" class="text-red-300" title="No se guardó: revisa tu conexión">!</span>
                    </span>
                </div>
            </template>
        </div>
        <div class="flex items-center gap-3 flex-wrap pt-2">
            <button type="button" class="btn-primary text-xs sm:text-sm" @click="showReview = true; window.scrollTo({ top: 0, behavior: 'smooth' })" :disabled="counted() === 0">Revisar diferencias</button>
            <form method="POST" action="{{ route('dashboard.inventario.conteos.cancel', $count->id) }}" onsubmit="return confirm('¿Cancelar este conteo? El stock no cambia.')">
                @csrf
                <button type="submit" class="text-xs text-white/40 hover:text-red-300">Cancelar conteo</button>
            </form>
        </div>
    </div>
    @endif
</div>

<script>
function countSheet(rows, url, blind, open) {
    return {
        rows, blind, open, query: '', pending: false, showReview: false,
        counted() { return this.rows.filter(r => r.counted !== null).length; },
        differences() { return this.rows.filter(r => r.counted !== null && r.variance !== 0); },
        totalValue() { return this.differences().reduce((sum, r) => sum + (r.variance_value || 0), 0); },
        visible() {
            const q = this.query.trim().toLowerCase();
            return this.rows.filter(r => (!this.pending || r.counted === null)
                && (!q || r.name.toLowerCase().includes(q) || (r.sku || '').toLowerCase().includes(q)));
        },
        money(value) {
            const sign = value < 0 ? '-' : '';
            return sign + 'S/ ' + Math.abs(Number(value || 0)).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        async save(row, value) {
            const counted = value === '' ? null : Math.max(0, parseInt(value, 10));
            row.state = 'saving';
            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ item_id: row.id, counted_qty: counted }),
                });
                if (!res.ok) throw new Error();
                const data = await res.json();
                Object.assign(row, { counted: data.counted_qty, expected: data.expected_qty, variance: data.variance, variance_value: data.variance_value, state: 'saved' });
            } catch (e) {
                row.state = 'error';
            }
        },
    };
}
</script>
@endsection
