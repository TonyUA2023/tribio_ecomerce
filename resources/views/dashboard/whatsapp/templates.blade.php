@extends('layouts.dashboard')
@section('title', 'WhatsApp')
@section('page_title', '💬 Plantillas de Meta')
@section('content')
<div class="w-full max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Para escribir primero a un cliente, o cuando pasaron más de 24 h desde su último mensaje, WhatsApp solo deja enviar plantillas aprobadas por Meta. Cada mensaje de plantilla lo cobra Meta según su categoría.</p>
        <a href="{{ route('dashboard.whatsapp.index') }}" class="text-xs text-tribio-cyan underline">← Chats</a>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    @if(!$connection?->isConnected())
        <div class="glass-card p-5 flex items-center justify-between gap-3 flex-wrap" role="status">
            <p class="text-sm text-white">Conecta tu número para gestionar sus plantillas.</p>
            <a href="{{ route('dashboard.whatsapp.connection.edit') }}" class="btn-primary text-xs sm:text-sm">Conectar</a>
        </div>
    @else
        <div class="glass-card p-4 sm:p-5">
            <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                <h3 class="text-white font-bold text-base">En tu cuenta de Meta</h3>
                <form method="POST" action="{{ route('dashboard.whatsapp.templates.sync') }}">@csrf<button type="submit" class="btn-secondary !py-1 text-xs">↻ Sincronizar con Meta</button></form>
            </div>
            <div class="space-y-2">
                @forelse($remote as $template)
                    <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                        <div class="flex items-start justify-between gap-2 flex-wrap">
                            <p class="text-sm text-white font-semibold break-all">{{ $template->name }} <span class="text-white/40 font-normal">· {{ $template->language }} · {{ $template->category_label }}</span></p>
                            <span class="badge {{ $template->status_badge }}">{{ $template->status_label }}</span>
                        </div>
                        <p class="text-xs text-white/70 mt-1 whitespace-pre-line">{{ $template->body }}</p>
                        @if($template->rejected_reason)<p class="text-xs text-red-300 mt-1">Motivo: {{ $template->rejected_reason }}</p>@endif
                        @if($template->status === 'APPROVED' && ($reason = $template->unsupportedReason()))<p class="text-[11px] text-white/40 mt-1">No se puede enviar desde Tribio: {{ $reason }}</p>@endif
                        <form method="POST" action="{{ route('dashboard.whatsapp.templates.destroy', $template->id) }}" class="mt-2" onsubmit="return confirm('¿Eliminar la plantilla en Meta? Meta no deja reutilizar el nombre por un tiempo.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-[11px] text-white/40 hover:text-red-300">Eliminar</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-white/60">Aún no hay plantillas. Envía las tuyas a revisión aquí abajo, o pulsa «Sincronizar» si ya las creaste en Meta.</p>
                @endforelse
            </div>
        </div>

        <div class="glass-card p-4 sm:p-5">
            <h3 class="text-white font-bold text-base">Envía tus plantillas a revisión</h3>
            <p class="text-xs text-white/60 mt-1 mb-3">Son tus <a href="{{ route('dashboard.clientes.templates.index') }}" class="text-tribio-cyan underline">plantillas de Clientes</a>. El nombre y enlace de tu tienda van escritos; los datos del cliente se completan solos al enviar. Usa <strong>Marketing</strong> para ofertas y novedades, <strong>Utilidad</strong> para pedidos, pagos y seguimiento.</p>
            <div class="space-y-2">
                @foreach($local as $template)
                    <form method="POST" action="{{ route('dashboard.whatsapp.templates.submit') }}" class="rounded-xl border border-white/10 bg-white/5 p-3">
                        @csrf
                        <input type="hidden" name="key" value="{{ $template['key'] }}">
                        <div class="flex items-start justify-between gap-2 flex-wrap">
                            <p class="text-sm text-white font-semibold">{{ $template['name'] }}</p>
                            @if($template['submitted'])<span class="badge {{ $template['submitted']->status_badge }}">{{ $template['submitted']->status_label }}</span>@endif
                        </div>
                        <p class="text-xs text-white/70 mt-1 whitespace-pre-line">{{ $template['meta_text'] }}</p>
                        @if($template['problem'])
                            <p class="text-xs text-red-300 mt-2">{{ $template['problem'] }}</p>
                        @elseif(!$template['submitted'] || in_array($template['submitted']->status, ['REJECTED', 'PAUSED'], true) || ($template['submitted']->status === 'APPROVED' && $template['submitted']->body !== $template['meta_text']))
                            <div class="flex items-center gap-2 flex-wrap mt-2">
                                <label class="sr-only" for="cat-{{ $template['key'] }}">Categoría de {{ $template['name'] }}</label>
                                <select id="cat-{{ $template['key'] }}" name="category" class="input-field !py-1 !text-xs !w-auto">
                                    <option value="UTILITY" @selected(!$template['promo'])>Utilidad</option>
                                    <option value="MARKETING" @selected($template['promo'])>Marketing</option>
                                </select>
                                <button type="submit" class="btn-primary !py-1 text-xs">
                                    {{ !$template['submitted'] ? 'Enviar a Meta' : ($template['submitted']->status === 'APPROVED' ? 'Enviar texto nuevo a revisión' : 'Corregir y reenviar') }}
                                </button>
                            </div>
                        @endif
                    </form>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
