@extends('layouts.dashboard')
@section('title', 'Devolución')
@section('page_title', '↩️ Devolución de ' . $order->order_number)
@section('content')
@php
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $share = (float) $order->subtotal > 0 ? 1 - (float) $order->discount / (float) $order->subtotal : 1;
    $mySession = $sessions->firstWhere('opened_by', auth()->id()) ?? $sessions->first();
    $unitPrices = $order->items->mapWithKeys(fn ($item) => [$item->id => round((float) $item->subtotal / max(1, (int) $item->quantity) * $share, 4)]);
@endphp
<div class="w-full max-w-[1600px] mx-auto space-y-5"
     x-data="{ qty: {}, prices: @js($unitPrices), owed: {{ (float) $order->balance_due }},
               get total() { return Object.entries(this.qty).reduce((sum, [id, q]) => sum + Math.round((this.prices[id] || 0) * (Number(q) || 0) * 100) / 100, 0); },
               get debt() { return Math.min(this.total, this.owed); },
               get refund() { return Math.max(0, Math.round((this.total - this.debt) * 100) / 100); } }">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-xs text-white/60">{{ $order->customer_name }} · {{ $order->created_at->copy()->setTimezone('America/Lima')->format('d/m/Y H:i') }} · total {{ $money($order->total) }}{{ (float) $order->returned_total > 0 ? ' · ya devolvió ' . $money($order->returned_total) : '' }}{{ (float) $order->balance_due > 0 ? ' · debe ' . $money($order->balance_due) : '' }}</p>
        <a href="{{ route('dashboard.returns.index') }}" class="text-xs text-tribio-cyan underline">← Devoluciones</a>
    </div>

    @if(app(\App\Services\Sunat\SunatSchema::class)->ready() && ($electronicDoc = \App\Models\ElectronicDocument::where('order_id', $order->id)->whereIn('status', ['accepted', 'observed'])->first()))
        <div class="glass-card p-4 text-xs text-amber-300" role="note">
            <strong>{{ $electronicDoc->code() }} ya está en SUNAT.</strong> Esta devolución devuelve el dinero y el stock, pero <strong>no anula el comprobante ante SUNAT</strong>: para eso se necesita una nota de crédito (todavía no disponible en Tribio). Coordínalo con tu contador.
        </div>
    @endif

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    @if(in_array($order->status, ['cancelled', 'refunded'], true))
        <div class="glass-card p-6 text-center"><p class="text-sm text-white/70">Esta venta ya fue devuelta por completo o anulada.</p></div>
    @else
        <form method="POST" action="{{ route('dashboard.returns.store', $order->id) }}" class="space-y-4" x-data="{ sending: false }" @submit="sending = true">
            @csrf
            <div class="glass-card divide-y divide-white/10 overflow-hidden">
                @foreach($order->items as $i => $item)
                    @php $left = $returnable[$item->id] ?? 0; @endphp
                    <div class="p-3 sm:p-4 flex items-center justify-between gap-3 flex-wrap">
                        <input type="hidden" name="items[{{ $i }}][order_item_id]" value="{{ $item->id }}">
                        <div class="min-w-0">
                            <p class="text-sm text-white font-semibold">{{ $item->product_name }}{{ $item->variant_title ? ' · ' . $item->variant_title : '' }}</p>
                            <p class="text-[11px] text-white/50">Vendió {{ $item->unit_name ? $item->unit_quantity . ' ' . $item->unit_name . ' (' . $item->quantity . ' und.)' : $item->quantity . ' und.' }} · {{ $money($unitPrices[$item->id]) }} c/u{{ $left < $item->quantity ? ' · puede devolver ' . $left : '' }}</p>
                        </div>
                        @if($left > 0)
                            <div class="flex items-center gap-3">
                                <label class="text-xs text-white/60 flex items-center gap-1">Devuelve
                                    <input type="number" name="items[{{ $i }}][quantity]" min="0" max="{{ $left }}" value="0" class="input-field !py-1 !w-20 text-right" x-model="qty[{{ $item->id }}]"></label>
                                <label class="text-xs text-white/60 flex items-center gap-1"><input type="hidden" name="items[{{ $i }}][restock]" value="0"><input type="checkbox" name="items[{{ $i }}][restock]" value="1" checked> Vuelve al stock</label>
                            </div>
                        @else
                            <span class="badge badge-gray">Ya devuelto</span>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="glass-card p-4 sm:p-5 space-y-3">
                <div>
                    <label class="input-label" for="return-reason">Motivo</label>
                    <input id="return-reason" name="reason" maxlength="255" class="input-field" value="{{ old('reason') }}" placeholder="Llegó vencido, talla equivocada, no le gustó…" required>
                </div>
                <p class="text-sm text-white">Valor de lo devuelto: <strong x-text="'S/ ' + total.toFixed(2)"></strong></p>
                <p class="text-xs text-amber-300" x-show="debt > 0" x-text="'Se descuentan S/ ' + debt.toFixed(2) + ' de lo que debe.'"></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="refund > 0">
                    <p class="sm:col-span-2 text-sm text-emerald-300" x-text="'Le devuelves S/ ' + refund.toFixed(2)"></p>
                    <div>
                        <label class="input-label" for="return-method">Cómo le devuelves</label>
                        <select id="return-method" name="method" class="input-field">@foreach($methods as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="input-label" for="return-session">Sale de</label>
                        <select id="return-session" name="register_session_id" class="input-field">
                            @foreach($sessions as $session)<option value="{{ $session->id }}" @selected($mySession?->id === $session->id)>{{ $session->register->name }} (abierta)</option>@endforeach
                            <option value="" @selected(!$mySession)>Fuera de caja</option>
                        </select>
                    </div>
                </div>
                <x-pos-approval id="return" />
                <button type="submit" class="btn-primary" :disabled="sending || total <= 0" :class="(sending || total <= 0) && 'opacity-50 cursor-not-allowed'">Registrar devolución</button>
                <p class="text-[11px] text-white/50">Se genera una nota de devolución interna. La nota de crédito electrónica de SUNAT llegará con la facturación electrónica.</p>
            </div>
        </form>
    @endif
</div>
@endsection
