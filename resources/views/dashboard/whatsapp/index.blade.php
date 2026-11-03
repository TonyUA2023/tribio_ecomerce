@extends('layouts.dashboard')
@section('title', 'WhatsApp')
@section('page_title', '💬 WhatsApp')
@section('content')
<div class="w-full max-w-4xl mx-auto space-y-5">

    @if(!$ready)
        <div class="glass-card p-6 text-center">
            <p class="text-white font-bold">⏳ Estamos activando WhatsApp en tu cuenta</p>
            <p class="text-sm text-white/60 mt-1">Vuelve a intentarlo en unos minutos.</p>
        </div>
    @else
        @if(!$connection || !$connection->isConnected())
            <div class="glass-card p-4 sm:p-5 flex items-center justify-between gap-3 flex-wrap" role="status">
                <div class="min-w-0">
                    <p class="text-white font-bold text-sm">{{ $connection ? 'Tu número está ' . mb_strtolower($connection->status_label) : 'Conecta tu número de WhatsApp Business' }}</p>
                    <p class="text-xs text-white/60 mt-0.5">Recibe y responde los chats de tus clientes aquí, con todo su historial de compras al lado.</p>
                </div>
                <a href="{{ route('dashboard.whatsapp.connection.edit') }}" class="btn-primary text-xs sm:text-sm">{{ $connection ? 'Revisar conexión' : 'Conectar' }}</a>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2 text-xs">
                <a href="{{ route('dashboard.whatsapp.index') }}" class="{{ $filter ? 'btn-ghost' : 'btn-secondary' }} !py-1">Todos</a>
                <a href="{{ route('dashboard.whatsapp.index', ['ver' => 'sin-leer']) }}" class="{{ $filter ? 'btn-secondary' : 'btn-ghost' }} !py-1">Sin leer</a>
            </div>
            <form method="GET" action="{{ route('dashboard.whatsapp.index') }}" class="flex items-center gap-2" role="search">
                @if($filter)<input type="hidden" name="ver" value="{{ $filter }}">@endif
                <label for="chat-search" class="sr-only">Buscar chat</label>
                <input id="chat-search" name="q" value="{{ request('q') }}" class="input-field !py-1 !text-xs w-44" placeholder="Nombre o celular">
                @if($connection?->isConnected() && app(\App\Services\Whatsapp\WhatsappSchema::class)->templatesReady())<a href="{{ route('dashboard.whatsapp.templates.index') }}" class="text-xs text-tribio-cyan underline whitespace-nowrap">Plantillas</a>@endif
                @if($connection?->isConnected() && app(\App\Services\Whatsapp\WhatsappSchema::class)->broadcastsReady())<a href="{{ route('dashboard.whatsapp.broadcasts.index') }}" class="text-xs text-tribio-cyan underline whitespace-nowrap">Difusiones</a>@endif
                @if($connection)<a href="{{ route('dashboard.whatsapp.connection.edit') }}" class="text-xs text-tribio-cyan underline whitespace-nowrap">Conexión</a>@endif
            </form>
        </div>

        <div class="glass-card divide-y divide-white/10 overflow-hidden">
            @forelse($conversations as $chat)
                <a href="{{ route('dashboard.whatsapp.show', $chat->id) }}" class="flex items-center gap-3 p-3 sm:p-4 hover:bg-white/5">
                    <span class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-sm font-bold text-white flex-shrink-0" aria-hidden="true">{{ mb_strtoupper(mb_substr($chat->display_name, 0, 1)) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="text-sm text-white {{ $chat->unread_count ? 'font-bold' : 'font-semibold' }} truncate">{{ $chat->display_name }}</span>
                            <span class="text-[11px] text-white/40 flex-shrink-0">{{ $chat->last_message_at?->locale('es')->isToday() ? $chat->last_message_at->format('H:i') : $chat->last_message_at?->locale('es')->isoFormat('D MMM') }}</span>
                        </span>
                        <span class="flex items-center justify-between gap-2 mt-0.5">
                            <span class="text-xs text-white/60 truncate">{{ $chat->last_message_preview }}</span>
                            @if($chat->unread_count)<span class="badge badge-green flex-shrink-0" aria-label="{{ $chat->unread_count }} sin leer">{{ $chat->unread_count }}</span>@endif
                        </span>
                        @unless($chat->windowOpen())<span class="text-[10px] text-white/40">Ventana de 24 h cerrada</span>@endunless
                    </span>
                </a>
            @empty
                <div class="p-6 text-center">
                    <p class="text-sm text-white/60">{{ request('q') || $filter ? 'No hay chats con ese filtro.' : 'Aún no hay chats. Cuando un cliente escriba a tu número, aparecerá aquí.' }}</p>
                </div>
            @endforelse
        </div>

        @if($conversations->hasPages())<div>{{ $conversations->links() }}</div>@endif
    @endif
</div>
@endsection
