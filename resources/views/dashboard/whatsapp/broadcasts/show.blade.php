@extends('layouts.dashboard')
@section('title', 'Difusión')
@section('page_title', '📣 ' . $broadcast->name)
@section('content')
@php
    $money = fn ($value) => 'S/ ' . number_format((float) $value, 2);
    $tz = \App\Services\Whatsapp\WhatsappBudget::TIMEZONE;
    $date = fn ($at) => $at?->copy()->setTimezone($tz)->locale('es')->isoFormat('D [de] MMMM, H:mm');
    $percent = fn ($part) => $stats['sent'] > 0 ? round($part * 100 / $stats['sent']) . '%' : '—';
@endphp
<div class="w-full max-w-5xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="badge {{ $broadcast->status_badge }}">{{ $broadcast->status_label }}</span>
            <span class="text-xs text-white/60 break-all">{{ $broadcast->template_name }} · {{ \App\Models\WhatsappTemplate::CATEGORIES[$broadcast->category] ?? $broadcast->category }}</span>
        </div>
        <a href="{{ route('dashboard.whatsapp.broadcasts.index') }}" class="text-xs text-tribio-cyan underline">← Difusiones</a>
    </div>

    <div class="glass-card p-4 sm:p-5 flex items-start justify-between gap-3 flex-wrap">
        <div class="text-xs text-white/60 space-y-0.5 min-w-0">
            @if($broadcast->status === 'scheduled')
                <p>Sale el <strong class="text-white">{{ $date($broadcast->scheduled_at) }}</strong> a {{ $broadcast->recipients_count === 1 ? '1 cliente' : 'unos ' . number_format($broadcast->recipients_count) . ' clientes' }}, por ~{{ $money($broadcast->estimated_cost) }}. Al salir revisamos de nuevo el público, el tope y el límite de Meta.</p>
            @else
                <p>Creada el {{ $date($broadcast->created_at) }}{{ $broadcast->user ? ' por ' . $broadcast->user->name : '' }}.</p>
                @if($broadcast->started_at)<p>{{ $broadcast->recipients_count === 1 ? '1 mensaje preparado' : number_format($broadcast->recipients_count) . ' mensajes preparados' }} el {{ $date($broadcast->started_at) }}{{ $broadcast->skipped_count ? ' · ' . number_format($broadcast->skipped_count) . ' clientes sin algún dato' : '' }}.</p>@endif
                @if($broadcast->finished_at)<p>Terminó el {{ $date($broadcast->finished_at) }}.</p>@endif
            @endif
            @if($broadcast->status === 'cancelled' && !$broadcast->started_at)<p>Se canceló antes de salir: no se envió ningún mensaje.</p>@endif
            @if($broadcast->error)<p class="text-red-300">{{ $broadcast->error }}</p>@endif
        </div>
        @if($broadcast->isOpen())
            <form method="POST" action="{{ route('dashboard.whatsapp.broadcasts.cancel', $broadcast->id) }}" onsubmit="return confirm('¿Cancelar la difusión? Los mensajes que ya salieron no se pueden recuperar.')">
                @csrf
                <button type="submit" class="btn-secondary !py-1 text-xs">Cancelar difusión</button>
            </form>
        @endif
    </div>

    @if($broadcast->started_at)
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="stat-card"><p class="text-white/50 text-xs">Entregados</p><p class="text-lg sm:text-xl font-black text-white mt-1">{{ number_format($stats['delivered']) }}</p><p class="text-[11px] text-white/40">{{ $percent($stats['delivered']) }} de {{ number_format($stats['sent']) }} enviados{{ $stats['queued'] ? ' · ' . number_format($stats['queued']) . ' en cola' : '' }}</p></div>
            <div class="stat-card"><p class="text-white/50 text-xs">Leídos</p><p class="text-lg sm:text-xl font-black text-white mt-1">{{ number_format($stats['read']) }}</p><p class="text-[11px] text-white/40">{{ $percent($stats['read']) }} · algunos clientes ocultan la lectura</p></div>
            <div class="stat-card"><p class="text-white/50 text-xs">Respondieron</p><p class="text-lg sm:text-xl font-black text-white mt-1">{{ number_format($stats['replied']) }}</p><p class="text-[11px] text-white/40">{{ $stats['failed'] ? number_format($stats['failed']) . ' no se enviaron' : 'ningún envío falló' }}</p></div>
            <div class="stat-card"><p class="text-white/50 text-xs">Pedidos en {{ \App\Services\Whatsapp\BroadcastService::RESULT_DAYS }} días</p><p class="text-lg sm:text-xl font-black text-white mt-1 whitespace-nowrap">{{ $money($stats['revenue']) }}</p><p class="text-[11px] text-white/40">{{ $stats['orders'] }} {{ $stats['orders'] === 1 ? 'pedido' : 'pedidos' }} · costó {{ $money($stats['cost']) }}</p></div>
        </div>

        @if($stats['errors']->isNotEmpty())
            <div class="glass-card p-4 sm:p-5">
                <h3 class="text-white font-bold text-sm mb-2">Por qué no salieron algunos</h3>
                <ul class="space-y-1">
                    @foreach($stats['errors'] as $error => $total)<li class="text-xs text-white/70"><span class="text-white font-semibold">{{ $total }}</span> · {{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="text-white font-bold text-base">Clientes</h3>
            <div class="flex items-center gap-2 text-xs">
                <a href="{{ route('dashboard.whatsapp.broadcasts.show', $broadcast->id) }}" class="{{ $filter ? 'btn-ghost' : 'btn-secondary' }} !py-1">Todos</a>
                <a href="{{ route('dashboard.whatsapp.broadcasts.show', [$broadcast->id, 'ver' => 'fallidos']) }}" class="{{ $filter ? 'btn-secondary' : 'btn-ghost' }} !py-1">No recibieron</a>
            </div>
        </div>
        <div class="glass-card divide-y divide-white/10 overflow-hidden">
            @forelse($recipients as $recipient)
                <div class="flex items-center justify-between gap-3 p-3">
                    <div class="min-w-0">
                        <a href="{{ route('dashboard.clientes.show', $recipient->contact_id) }}" class="text-sm text-white font-semibold hover:underline break-words">{{ $recipient->contact?->name ?? 'Cliente eliminado' }}</a>
                        <p class="text-[11px] text-white/50">{{ $recipient->contact?->phone_display }}</p>
                        @if($recipient->reason)<p class="text-[11px] text-white/50">{{ $recipient->reason }}</p>@endif
                        @if($recipient->message?->error)<p class="text-[11px] text-red-300">{{ $recipient->message->error }}</p>@endif
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($recipient->status === 'skipped')
                            <span class="badge badge-gray">No se envió</span>
                        @elseif($recipient->message)
                            <span class="badge {{ match ($recipient->message->status) { 'read', 'delivered' => 'badge-green', 'failed' => 'badge-red', 'queued' => 'badge-yellow', default => 'badge-blue' } }}">{{ $recipient->message->status_label }}</span>
                            <a href="{{ route('dashboard.whatsapp.show', $recipient->message->conversation_id) }}" class="text-xs text-tribio-cyan underline">Chat</a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="p-6 text-center text-sm text-white/60">{{ $filter ? 'Todos los mensajes salieron.' : 'Aún no hay mensajes en cola.' }}</p>
            @endforelse
        </div>
        @if($recipients->hasPages())<div>{{ $recipients->links() }}</div>@endif
    @endif
</div>
@endsection
