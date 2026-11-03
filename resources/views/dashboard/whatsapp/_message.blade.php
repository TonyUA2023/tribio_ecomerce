{{-- One chat bubble. Needs $message. --}}
@php
    $media = $message->payload ?? [];
    $file = !empty($media['path']) ? route('dashboard.whatsapp.media', $message->id) : null;
    $mime = (string) ($media['mime_type'] ?? '');
@endphp
<div class="flex {{ $message->isInbound() ? 'justify-start' : 'justify-end' }}" data-message-id="{{ $message->id }}">
    <div class="max-w-[85%] sm:max-w-[70%] rounded-2xl px-3 py-2 {{ $message->isInbound() ? 'bg-white/10 rounded-bl-sm' : 'bg-tribio-cyan/20 rounded-br-sm' }}">
        @if($message->type === 'template')
            <p class="text-[10px] text-white/40 mb-1">Plantilla · {{ $media['template'] ?? '' }}</p>
        @endif
        @if($file && in_array($message->type, ['image', 'sticker'], true))
            <a href="{{ $file }}" target="_blank" rel="noopener"><img src="{{ $file }}" alt="{{ $message->type === 'sticker' ? 'Sticker' : 'Foto del cliente' }}" loading="lazy" class="rounded-xl max-h-64 w-auto mb-1"></a>
        @elseif($file && $message->type === 'audio')
            <audio controls preload="none" src="{{ $file }}" class="max-w-full mb-1"></audio>
        @elseif($file && $message->type === 'video' && $mime === 'video/mp4')
            <video controls preload="none" src="{{ $file }}" class="rounded-xl max-h-64 max-w-full mb-1"></video>
        @elseif($file)
            <a href="{{ $file }}" class="text-xs text-tribio-cyan underline">⬇ Descargar {{ $media['filename'] ?? 'archivo' }}</a>
        @endif
        <p class="text-sm text-white whitespace-pre-line break-words">{{ $message->body }}</p>
        @if(!empty($media['too_large']))<p class="text-[11px] text-white/40">Archivo muy grande: ábrelo en el WhatsApp de tu celular.</p>@endif
        @if(!empty($media['unavailable']))<p class="text-[11px] text-white/40">El archivo ya no está disponible en Meta.</p>@endif
        <p class="text-[10px] text-white/40 mt-1 text-right">
            {{ ($message->wa_timestamp ?? $message->created_at)->locale('es')->isoFormat('D MMM, HH:mm') }}
            @unless($message->isInbound())
                · <span data-status-id="{{ $message->id }}" class="{{ $message->status === 'failed' ? 'text-red-300' : '' }}">{{ $message->status_label }}</span>
            @endunless
        </p>
        @if($message->status === 'failed' && $message->error)
            <p class="text-[11px] text-red-300 mt-1" data-error-id="{{ $message->id }}">{{ $message->error }}</p>
        @endif
    </div>
</div>
