@extends('layouts.dashboard')
@section('title', 'Mensajes')
@section('page_title', '✉️ Mensaje')
@section('content')
@php
    $isComplaint = $message->kind === \App\Models\ContactMessage::KIND_COMPLAINT;
    $phoneDigits = preg_replace('/\D+/', '', (string) $message->phone);
    if ($phoneDigits !== '' && strlen($phoneDigits) === 9) {
        $phoneDigits = '51' . $phoneDigits; // números peruanos sin código de país
    }
    $replySubject = 'Re: ' . ($message->subject ?: 'Tu mensaje a ' . $store->name);
@endphp
<div class="w-full max-w-3xl mx-auto space-y-4">
    <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
        <a href="{{ route('dashboard.mensajes.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a mensajes" title="Volver a mensajes">←</a>
        <div class="min-w-0 flex-1">
            <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">{{ $message->created_at->locale('es')->translatedFormat('d M Y, H:i') }}</p>
            <p class="text-white font-bold text-sm truncate">{{ $message->subject ?: 'Sin asunto' }}</p>
        </div>
        <form method="POST" action="{{ route('dashboard.mensajes.unread', $message->id) }}" class="flex-shrink-0">
            @csrf @method('PATCH')
            <button type="submit" class="btn-ghost !text-xs">Marcar no leído</button>
        </form>
    </div>

    @if($isComplaint)
        <div class="glass-card p-4 border border-red-400/20 bg-red-500/5">
            <p class="text-red-400 font-bold text-sm">📕 Reclamo o queja del Libro de Reclamaciones</p>
            <p class="text-white/60 text-xs mt-1">Responde al cliente por escrito (a su correo) dentro del plazo legal: 15 días hábiles desde que lo recibiste. Guarda este registro; no se puede borrar.</p>
        </div>
    @endif

    <div class="glass-card p-5 sm:p-6 space-y-4">
        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div><dt class="text-white/40">Nombre</dt><dd class="text-white font-semibold mt-0.5">{{ $message->name }}</dd></div>
            <div><dt class="text-white/40">Correo</dt><dd class="text-white mt-0.5 break-all">{{ $message->email }}</dd></div>
            <div><dt class="text-white/40">Teléfono</dt><dd class="text-white mt-0.5">{{ $message->phone ?: '—' }}</dd></div>
        </dl>
        <div class="border-t pt-4" style="border-color: rgba(255,255,255,0.08);">
            <p class="text-white text-sm leading-relaxed whitespace-pre-line break-words">{{ $message->message }}</p>
        </div>
        <div class="flex flex-wrap gap-2 pt-1">
            <a href="mailto:{{ $message->email }}?subject={{ rawurlencode($replySubject) }}" class="btn-primary !text-xs">Responder por correo</a>
            @if($phoneDigits !== '')
                <a href="https://wa.me/{{ $phoneDigits }}?text={{ rawurlencode('Hola ' . $message->name . ', te escribimos de ' . $store->name . ' por tu mensaje.') }}" target="_blank" rel="noopener" class="btn-secondary !text-xs">Responder por WhatsApp</a>
            @endif
        </div>
    </div>
</div>
@endsection
