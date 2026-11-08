@extends('layouts.dashboard')
@section('title', 'Por cobrar')
@section('page_title', '💰 Por cobrar')
@section('content')
@php $money = fn ($value) => 'S/ ' . number_format((float) $value, 2); @endphp
<div class="w-full max-w-[1600px] mx-auto space-y-5">
    <p class="text-sm text-white/60 max-w-2xl">Lo que tus clientes te deben por ventas al crédito (fiado). Cobra desde aquí o desde la caja, y recuérdales por WhatsApp.</p>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="stat-card"><p class="text-white/50 text-xs">Te deben</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($summary['owed']) }}</p><p class="text-[11px] text-white/40">{{ $summary['customers'] }} {{ $summary['customers'] === 1 ? 'cliente' : 'clientes' }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Vencido</p><p class="text-lg sm:text-xl font-black {{ $summary['overdue'] > 0 ? 'text-red-300' : 'text-white' }} mt-1 whitespace-nowrap">{{ $money($summary['overdue']) }}</p><p class="text-[11px] text-white/40">{{ $summary['late'] }} {{ $summary['late'] === 1 ? 'cliente atrasado' : 'clientes atrasados' }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Cobrado este mes</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($summary['collected']) }}</p></div>
        <div class="stat-card"><p class="text-white/50 text-xs">Al día</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($summary['aging']['al_dia']) }}</p><p class="text-[11px] text-white/40">aún no vence</p></div>
    </div>

    @if($summary['owed'] > 0)
        <div class="glass-card p-4 sm:p-5">
            <h3 class="text-white font-bold text-sm mb-3">Antigüedad de la deuda</h3>
            <div class="flex h-3 rounded-full overflow-hidden bg-white/10" role="img" aria-label="Deuda por antigüedad">
                @foreach($aging as $key => $label)
                    @if($summary['aging'][$key] > 0)
                        <span class="h-full {{ ['al_dia' => 'bg-emerald-400', '1_30' => 'bg-amber-300', '31_60' => 'bg-orange-400', '61_90' => 'bg-red-400', '90_mas' => 'bg-red-600'][$key] }}" style="width: {{ round($summary['aging'][$key] * 100 / $summary['owed'], 2) }}%"></span>
                    @endif
                @endforeach
            </div>
            <dl class="grid grid-cols-2 sm:grid-cols-5 gap-2 mt-3">
                @foreach($aging as $key => $label)
                    <div><dt class="text-[11px] text-white/50">{{ $label }}</dt><dd class="text-sm text-white font-semibold">{{ $money($summary['aging'][$key]) }}</dd></div>
                @endforeach
            </dl>
        </div>
    @endif

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 text-xs">
            <a href="{{ route('dashboard.receivables.index') }}" class="{{ $filter ? 'btn-ghost' : 'btn-secondary' }} !py-1">Todos</a>
            <a href="{{ route('dashboard.receivables.index', ['ver' => 'vencidos']) }}" class="{{ $filter === 'vencidos' ? 'btn-secondary' : 'btn-ghost' }} !py-1">Vencidos</a>
            <a href="{{ route('dashboard.receivables.index', ['ver' => 'semana']) }}" class="{{ $filter === 'semana' ? 'btn-secondary' : 'btn-ghost' }} !py-1">Vencen en 7 días</a>
        </div>
        <form method="GET" action="{{ route('dashboard.receivables.index') }}" role="search" class="flex items-center gap-2">
            @if($filter)<input type="hidden" name="ver" value="{{ $filter }}">@endif
            <label for="debt-search" class="sr-only">Buscar cliente</label>
            <input id="debt-search" name="q" value="{{ request('q') }}" class="input-field !py-1 !text-xs w-44" placeholder="Nombre o celular">
        </form>
    </div>

    <div class="glass-card divide-y divide-white/10 overflow-hidden">
        @forelse($customers as $row)
            <a href="{{ route('dashboard.receivables.show', $row['contact']->id) }}" class="flex items-center justify-between gap-3 p-3 sm:p-4 hover:bg-white/5 flex-wrap">
                <span class="min-w-0">
                    <span class="block text-sm text-white font-semibold break-words">{{ $row['contact']->name }}</span>
                    <span class="block text-xs text-white/50">
                        {{ $row['orders'] }} {{ $row['orders'] === 1 ? 'compra' : 'compras' }} al crédito
                        @if($row['next_due']) · próximo pago {{ $row['next_due']->locale('es')->isoFormat('D MMM') }}@endif
                    </span>
                </span>
                <span class="text-right">
                    <span class="block text-sm text-white font-bold whitespace-nowrap">{{ $money($row['owed']) }}</span>
                    @if($row['overdue'] > 0)
                        <span class="badge badge-red">Vencido {{ $money($row['overdue']) }} · {{ $row['days_late'] }} {{ $row['days_late'] === 1 ? 'día' : 'días' }}</span>
                    @else
                        <span class="badge badge-green">Al día</span>
                    @endif
                </span>
            </a>
        @empty
            <p class="p-6 text-center text-sm text-white/60">{{ $filter || request('q') ? 'Nadie con ese filtro.' : 'Nadie te debe. Para fiar, en la caja cobra menos del total y elige «Dejar a crédito».' }}</p>
        @endforelse
    </div>
</div>
@endsection
