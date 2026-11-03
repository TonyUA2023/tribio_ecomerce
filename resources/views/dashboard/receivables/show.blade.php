@extends('layouts.dashboard')
@section('title', 'Por cobrar')
@section('page_title', '💰 ' . $contact->name)
@section('content')
@php
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $states = ['paid' => ['Pagada', 'badge-green'], 'overdue' => ['Vencida', 'badge-red'], 'due' => ['Vence hoy', 'badge-gold'], 'upcoming' => ['Por vencer', 'badge-gray']];
    $mySession = $sessions->firstWhere('opened_by', auth()->id()) ?? $sessions->first();
@endphp
<div class="w-full max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3 text-xs flex-wrap">
            <a href="{{ route('dashboard.receivables.index') }}" class="text-tribio-cyan underline">← Por cobrar</a>
            <a href="{{ route('dashboard.clientes.show', $contact->id) }}" class="text-tribio-cyan underline">Ficha del cliente</a>
        </div>
        @if($reminder)<a href="{{ $reminder }}" target="_blank" rel="noopener" class="btn-secondary !py-1 text-xs">💬 Recordar por WhatsApp</a>@endif
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
        <div class="stat-card"><p class="text-white/50 text-xs">Debe</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($owed) }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Vencido</p><p class="text-lg sm:text-xl font-black {{ $overdue > 0 ? 'text-red-300' : 'text-white' }} mt-1 whitespace-nowrap">{{ $money($overdue) }}</p></div>
        <form method="POST" action="{{ route('dashboard.receivables.limit', $contact->id) }}" class="stat-card col-span-2 lg:col-span-1">
            @csrf @method('PUT')
            <label class="text-white/50 text-xs" for="credit-limit">Límite de crédito (S/)</label>
            <div class="flex items-center gap-2 mt-1">
                <input id="credit-limit" name="credit_limit" type="number" step="1" min="0" class="input-field !py-1" value="{{ $contact->credit_limit !== null ? (float) $contact->credit_limit : '' }}" placeholder="Sin límite">
                <button type="submit" class="btn-ghost !py-1 text-xs">Guardar</button>
            </div>
            <x-pos-approval id="limit" class="mt-2" />
        </form>
    </div>

    @if($owed > 0)
        <form method="POST" action="{{ route('dashboard.receivables.collect', $contact->id) }}" class="glass-card p-4 sm:p-5 space-y-3"
              x-data="{ sending: false }" @submit="sending = true">
            @csrf
            <input type="hidden" name="uuid" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <h3 class="text-white font-bold text-base">Registrar un pago</h3>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="input-label" for="collect-amount">Monto (S/)</label>
                    <input id="collect-amount" name="amount" type="number" step="0.10" min="0.10" max="{{ $owed }}" class="input-field" value="{{ old('amount', $overdue > 0 ? $overdue : '') }}" required>
                </div>
                <div>
                    <label class="input-label" for="collect-method">Cómo pagó</label>
                    <select id="collect-method" name="method" class="input-field">@foreach($methods as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="input-label" for="collect-session">¿A qué caja entra?</label>
                    <select id="collect-session" name="register_session_id" class="input-field">
                        @foreach($sessions as $session)<option value="{{ $session->id }}" @selected($mySession?->id === $session->id)>{{ $session->register->name }} (abierta)</option>@endforeach
                        <option value="" @selected(!$mySession)>Fuera de caja</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" :disabled="sending">Registrar pago</button>
            </div>
            <p class="text-[11px] text-white/50">El pago se aplica a la deuda más antigua. Si entra a una caja abierta, el efectivo se suma a lo esperado al cerrarla.</p>
        </form>
    @endif

    @foreach($open as $row)
        <div class="glass-card p-4 sm:p-5 space-y-3">
            <div class="flex items-start justify-between gap-2 flex-wrap">
                <div>
                    <a href="{{ route('dashboard.pedidos.show', $row['order']->id) }}" class="text-sm text-white font-semibold hover:underline">{{ $row['order']->order_number }}</a>
                    <p class="text-[11px] text-white/50">{{ $row['order']->created_at->copy()->setTimezone('America/Lima')->locale('es')->isoFormat('D MMM YYYY') }} · total {{ $money($row['order']->total) }} · pagado {{ $money($row['order']->amount_paid) }}</p>
                    <p class="text-[11px] text-white/50">{{ $row['order']->items->map(fn ($i) => ($i->unit_name ? $i->unit_quantity . ' ' . $i->unit_name : $i->quantity) . ' × ' . $i->product_name)->implode(', ') }}</p>
                </div>
                <span class="text-sm text-white font-bold">Debe {{ $money($row['order']->balance_due) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-white/50 text-left"><th class="py-1 pr-2 font-normal">Cuota</th><th class="py-1 pr-2 font-normal">Vence</th><th class="py-1 pr-2 font-normal text-right">Monto</th><th class="py-1 pr-2 font-normal text-right">Falta</th><th class="py-1 font-normal">Estado</th></tr></thead>
                    <tbody class="divide-y divide-white/10">
                        @foreach($row['schedule'] as $installment)
                            <tr class="text-white/80">
                                <td class="py-1 pr-2">{{ $installment['number'] }}</td>
                                <td class="py-1 pr-2 whitespace-nowrap">{{ $installment['due_date']->format('d/m/Y') }}</td>
                                <td class="py-1 pr-2 text-right whitespace-nowrap">{{ $money($installment['amount']) }}</td>
                                <td class="py-1 pr-2 text-right whitespace-nowrap">{{ $money($installment['pending']) }}</td>
                                <td class="py-1"><span class="badge {{ $states[$installment['status']][1] }}">{{ $states[$installment['status']][0] }}{{ $installment['days_late'] ? ' · ' . $installment['days_late'] . ' d' : '' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    @if($open->isEmpty())
        <div class="glass-card p-6 text-center"><p class="text-sm text-white/60">{{ $contact->name }} no debe nada. 🎉</p></div>
    @endif

    @if($payments->isNotEmpty())
        <div class="glass-card p-4 sm:p-5">
            <h3 class="text-white font-bold text-sm mb-2">Pagos de sus compras al crédito</h3>
            <ul class="divide-y divide-white/10">
                @foreach($payments as $payment)
                    <li class="flex items-center justify-between gap-3 py-2 text-xs">
                        <span class="text-white/70">{{ $payment->paid_at->copy()->setTimezone('America/Lima')->locale('es')->isoFormat('D MMM YYYY, H:mm') }} · {{ $methods[$payment->method] ?? 'Pago' }} · {{ $payment->order?->order_number }}{{ $payment->kind === 'balance' ? '' : ' (al comprar)' }}</span>
                        <span class="text-white font-semibold whitespace-nowrap">{{ $money($payment->amount) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
