@extends('layouts.dashboard')
@section('title', 'Difusiones de WhatsApp')
@section('page_title', '📣 Difusiones')
@section('content')
@php
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $tz = \App\Services\Whatsapp\WhatsappBudget::TIMEZONE;
@endphp
<div class="w-full max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Envía una plantilla aprobada a un grupo de clientes. Antes de enviar ves a cuántos llega y cuánto costará; Meta cobra cada mensaje entregado.</p>
        <a href="{{ route('dashboard.whatsapp.index') }}" class="text-xs text-tribio-cyan underline">← Chats</a>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    @if(!$connection?->isConnected())
        <div class="glass-card p-5 flex items-center justify-between gap-3 flex-wrap" role="status">
            <p class="text-sm text-white">Conecta tu número de WhatsApp para enviar difusiones.</p>
            <a href="{{ route('dashboard.whatsapp.connection.edit') }}" class="btn-primary text-xs sm:text-sm">Conectar</a>
        </div>
    @else
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="stat-card"><p class="text-white/50 text-xs">Tope mensual</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $budget['budget'] !== null ? $money($budget['budget']) : 'Sin definir' }}</p></div>
            <div class="stat-card"><p class="text-white/50 text-xs">Gastado este mes</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($budget['spent']) }}</p><p class="text-[11px] text-white/40">según lo que Meta entregó</p></div>
            <div class="stat-card"><p class="text-white/50 text-xs">Disponible</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $budget['available'] !== null ? $money($budget['available']) : '—' }}</p>@if($budget['reserved'] > 0)<p class="text-[11px] text-white/40">{{ $money($budget['reserved']) }} reservado en envíos</p>@endif</div>
            <div class="stat-card"><p class="text-white/50 text-xs">Límite de Meta · 24 h</p><p class="text-lg sm:text-xl font-black text-white mt-1">{{ $limit === null ? 'Sin límite' : number_format($limitUsed) . ' / ' . number_format($limit) }}</p><p class="text-[11px] text-white/40">clientes a los que escribiste primero</p></div>
        </div>

        <form method="POST" action="{{ route('dashboard.whatsapp.broadcasts.budget') }}" class="glass-card p-4 sm:p-5" x-data="{ open: {{ $budget['budget'] === null || $errors->has('monthly_budget') || $errors->has('rates.*') ? 'true' : 'false' }} }">
            @csrf @method('PUT')
            <button type="button" class="w-full flex items-center justify-between text-left" @click="open = !open" :aria-expanded="open">
                <span class="text-white font-bold text-base">Tope mensual y precios</span>
                <span class="text-white/40 text-sm" x-text="open ? 'Cerrar' : 'Editar'"></span>
            </button>
            <div x-show="open" x-cloak class="mt-3 space-y-3">
                <p class="text-xs text-white/60">Ninguna difusión sale si supera lo que te queda del mes, y los envíos se detienen al llegar al tope. Los precios por mensaje son referenciales para Perú: si tu factura de Meta dice otra cosa, escríbelos aquí.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                    <div>
                        <label class="input-label" for="monthly-budget">Tope al mes (S/)</label>
                        <input id="monthly-budget" name="monthly_budget" type="number" step="0.01" min="1" class="input-field" value="{{ old('monthly_budget', $connection->monthly_budget) }}" placeholder="50.00" required>
                    </div>
                    <div>
                        <label class="input-label" for="rate-marketing">Marketing, por mensaje (S/)</label>
                        <input id="rate-marketing" name="rates[marketing]" type="number" step="0.0001" min="0" class="input-field" value="{{ old('rates.marketing', $connection->rates['marketing'] ?? '') }}" placeholder="{{ number_format(config('services.whatsapp.rates.marketing'), 4) }}">
                    </div>
                    <div>
                        <label class="input-label" for="rate-utility">Utilidad, por mensaje (S/)</label>
                        <input id="rate-utility" name="rates[utility]" type="number" step="0.0001" min="0" class="input-field" value="{{ old('rates.utility', $connection->rates['utility'] ?? '') }}" placeholder="{{ number_format(config('services.whatsapp.rates.utility'), 4) }}">
                    </div>
                </div>
                <button type="submit" class="btn-primary text-xs sm:text-sm">Guardar</button>
            </div>
        </form>

        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-white font-bold text-base">Tus difusiones</h3>
            <a href="{{ route('dashboard.whatsapp.broadcasts.create') }}" class="btn-primary text-xs sm:text-sm">+ Nueva difusión</a>
        </div>

        <div class="glass-card divide-y divide-white/10 overflow-hidden">
            @forelse($broadcasts as $broadcast)
                <a href="{{ route('dashboard.whatsapp.broadcasts.show', $broadcast->id) }}" class="block p-3 sm:p-4 hover:bg-white/5">
                    <span class="flex items-start justify-between gap-2 flex-wrap">
                        <span class="text-sm text-white font-semibold min-w-0 break-words">{{ $broadcast->name }}</span>
                        <span class="badge {{ $broadcast->status_badge }}">{{ $broadcast->status_label }}</span>
                    </span>
                    <span class="block text-xs text-white/50 mt-1 break-all">{{ $broadcast->template_name }} · {{ \App\Models\WhatsappTemplate::CATEGORIES[$broadcast->category] ?? $broadcast->category }}</span>
                    <span class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-white/60 mt-1">
                        <span>{{ $broadcast->status === 'scheduled' ? 'Sale el ' . $broadcast->scheduled_at->copy()->setTimezone($tz)->locale('es')->isoFormat('D MMM, H:mm') : $broadcast->created_at->copy()->setTimezone($tz)->locale('es')->isoFormat('D MMM, H:mm') }}</span>
                        <span>{{ $broadcast->recipients_count === 1 ? '1 cliente' : number_format($broadcast->recipients_count) . ' clientes' }}</span>
                        @if($broadcast->started_at)<span>{{ number_format($broadcast->delivered_count) }} entregados · {{ number_format($broadcast->read_count) }} leídos</span>@endif
                        <span>{{ $broadcast->started_at ? $money($broadcast->cost_sum) . ' gastado' : '~' . $money($broadcast->estimated_cost) }}</span>
                    </span>
                </a>
            @empty
                <div class="p-6 text-center">
                    <p class="text-sm text-white/60">Aún no enviaste difusiones. Prueba con un grupo pequeño: por ejemplo, tus clientes VIP.</p>
                </div>
            @endforelse
        </div>
        @if($broadcasts->hasPages())<div>{{ $broadcasts->links() }}</div>@endif
    @endif
</div>
@endsection
