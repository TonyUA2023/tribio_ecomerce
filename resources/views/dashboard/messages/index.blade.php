@extends('layouts.dashboard')
@section('title', 'Mensajes')
@section('page_title', '✉️ Mensajes')
@section('content')
@php
    $chipBase = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold transition whitespace-nowrap';
    $chipOn   = 'bg-tribio-cyan/10 border-tribio-cyan/40 text-tribio-cyan';
    $chipOff  = 'bg-white/5 border-white/10 text-white/60 hover:border-white/20';
    $kinds = [
        \App\Models\ContactMessage::KIND_COMPLAINT => ['Libro de Reclamaciones', 'badge-red'],
        \App\Models\ContactMessage::KIND_MESSAGE => ['Mensaje', 'badge-blue'],
        \App\Models\ContactMessage::KIND_SUBSCRIPTION => ['Suscripción', 'badge-gray'],
    ];
    $filters = [
        null => ['Todos', $counts['total']],
        'sin-leer' => ['Sin leer', $counts['sin-leer']],
        \App\Models\ContactMessage::KIND_COMPLAINT => ['Libro de Reclamaciones', $counts['libro']],
        \App\Models\ContactMessage::KIND_MESSAGE => ['Mensajes', $counts['mensaje']],
        \App\Models\ContactMessage::KIND_SUBSCRIPTION => ['Suscripciones', $counts['suscripcion']],
    ];
@endphp

<p class="text-white/50 text-xs mb-4">Lo que tus clientes te escriben desde tu tienda: el formulario de contacto, el Libro de Reclamaciones y la suscripción a novedades. También te llega un aviso a tu correo por cada uno.</p>

@if($counts['total'] > 0)
<div class="flex flex-wrap gap-2 mb-4">
    @foreach($filters as $key => [$label, $count])
        <a href="{{ route('dashboard.mensajes.index', $key ? ['filtro' => $key] : []) }}" class="{{ $chipBase }} {{ $filter === ($key ?: null) ? $chipOn : $chipOff }}">
            {{ $label }} <span class="opacity-70">{{ $count }}</span>
        </a>
    @endforeach
</div>
@endif

@if($messages->isEmpty())
    <div class="dash-empty">
        <x-dashboard-icon name="mail"/>
        <h3>{{ $counts['total'] > 0 ? 'No hay mensajes con este filtro' : 'Aún no tienes mensajes' }}</h3>
        <p class="text-white/40 text-xs mt-1">Cuando un cliente te escriba desde tu tienda, lo verás aquí.</p>
    </div>
@else
    <div class="space-y-2">
        @foreach($messages as $message)
            @php [$kindLabel, $kindBadge] = $kinds[$message->kind]; @endphp
            <a href="{{ route('dashboard.mensajes.show', $message->id) }}" class="glass-card p-4 block hover:bg-white/5 transition">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-white text-sm {{ $message->is_read ? 'font-medium' : 'font-bold' }} truncate">
                            @unless($message->is_read)<span class="badge badge-gold align-middle">Nuevo</span>@endunless
                            {{ $message->name }}
                            <span class="text-white/40 font-normal">· {{ $message->email }}</span>
                        </p>
                        <p class="text-white/60 text-xs mt-1 truncate">{{ $message->subject ?: 'Sin asunto' }} — {{ \Illuminate\Support\Str::limit($message->message, 110) }}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="badge {{ $kindBadge }}">{{ $kindLabel }}</span>
                        <p class="text-white/40 text-[11px] mt-1">{{ $message->created_at->locale('es')->diffForHumans() }}</p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
    <div class="mt-4">{{ $messages->links() }}</div>
@endif
@endsection
