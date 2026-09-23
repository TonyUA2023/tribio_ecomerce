@extends('layouts.dashboard')
@section('title', 'Marketing')
@section('page_title', '📣 Marketing')
@section('content')
@php
    $pixelOn = $integration?->hasPixel();
    $capiOn = $integration?->hasConversionsApi();
@endphp
<div class="w-full max-w-5xl mx-auto space-y-5 sm:space-y-6">

    <p class="text-sm text-white/60">Conecta tu tienda con las plataformas de anuncios: tus productos aparecen solos en tus campañas y ves cuántas ventas te traen.</p>

    @unless($available)
    <div class="glass-card p-4 sm:p-5">
        <p class="text-white font-bold text-sm">⏳ Estamos activando este módulo</p>
        <p class="text-xs text-white/50 mt-1">Falta una actualización en el servidor de Tribio. Apenas esté lista podrás conectar tu tienda aquí; no tienes que hacer nada.</p>
    </div>
    @endunless

    {{-- Meta --}}
    <div class="glass-card p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
                <h3 class="text-white font-bold text-base">Facebook e Instagram <span class="text-white/40 font-normal text-sm">· Meta</span></h3>
                <p class="text-xs text-white/50 mt-1">Muestra tus productos en anuncios de Facebook e Instagram y mide qué anuncios venden.</p>
            </div>
            @if(!$integration)
                <span class="badge badge-gray">Sin conectar</span>
            @elseif(!$integration->is_active)
                <span class="badge badge-gold">En pausa</span>
            @else
                <span class="badge badge-green">Conectado</span>
            @endif
        </div>

        @if($integration)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <p class="text-[11px] text-white/40 uppercase tracking-wider">Píxel</p>
                <p class="text-sm text-white font-semibold mt-1">{{ $pixelOn ? 'Activo' : 'Falta tu ID de Píxel' }}</p>
                <p class="text-[11px] text-white/40 mt-0.5">Mide visitas y carritos en tu tienda</p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <p class="text-[11px] text-white/40 uppercase tracking-wider">API de conversiones</p>
                <p class="text-sm text-white font-semibold mt-1">
                    @if(!$capiOn) Sin token
                    @elseif($integration->last_error_at && (!$integration->last_event_at || $integration->last_error_at->gt($integration->last_event_at))) <span class="badge badge-red">Con error</span>
                    @else Activa
                    @endif
                </p>
                <p class="text-[11px] text-white/40 mt-0.5">
                    {{ $integration->last_event_at ? 'Último envío ' . $integration->last_event_at->locale('es')->diffForHumans() : 'Envía tus ventas a Meta' }}
                </p>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <p class="text-[11px] text-white/40 uppercase tracking-wider">Catálogo</p>
                <p class="text-sm text-white font-semibold mt-1">{{ $catalog['products_included'] }} {{ $catalog['products_included'] === 1 ? 'producto listo' : 'productos listos' }}</p>
                <p class="text-[11px] text-white/40 mt-0.5">
                    @if(count($catalog['excluded'])) {{ count($catalog['excluded']) }} fuera del catálogo @else Todo tu catálogo está incluido @endif
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-white/10 bg-white/5 p-3 mt-3">
            <p class="text-[11px] text-white/40 uppercase tracking-wider">Ventas que llegaron desde Facebook e Instagram · últimos 30 días</p>
            @forelse($metaSales as $row)
                <p class="text-sm text-white font-semibold mt-1">
                    {{ $row->orders_count }} {{ (int) $row->orders_count === 1 ? 'pedido pagado' : 'pedidos pagados' }}
                    · {{ \App\Helpers\CurrencyHelper::symbol($row->currency ?: 'PEN') }} {{ number_format((float) $row->revenue, 2) }}
                </p>
            @empty
                <p class="text-sm text-white/60 mt-1">Aún ninguna. Aparecerán aquí cuando alguien compre después de hacer clic en uno de tus anuncios.</p>
            @endforelse
        </div>
        @endif

        <div class="mt-4">
            <a href="{{ route('dashboard.marketing.meta.edit') }}" class="btn-primary inline-flex text-xs sm:text-sm">
                {{ $integration ? 'Ver configuración' : 'Conectar con Meta' }}
            </a>
        </div>
    </div>

    {{-- Próximamente --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="glass-card p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-white font-bold text-sm">Google Shopping</p>
                <span class="badge badge-gray">Próximamente</span>
            </div>
            <p class="text-xs text-white/50 mt-1">Tus productos gratis en los resultados de Google.</p>
        </div>
        <div class="glass-card p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
                <p class="text-white font-bold text-sm">TikTok</p>
                <span class="badge badge-gray">Próximamente</span>
            </div>
            <p class="text-xs text-white/50 mt-1">Anuncios con tu catálogo en TikTok.</p>
        </div>
    </div>
</div>
@endsection
