@extends('layouts.dashboard')
@section('title', 'Módulos del negocio')
@section('page_title', '⚙️ Módulos del negocio')
@section('content')
@php
    $modules = [
        'store' => [
            'icon' => 'store', 'title' => 'Tienda virtual', 'ask' => 'Abrir tienda virtual',
            'text' => 'Tu catálogo en internet, con carrito, pedidos y una dirección para compartir.',
            'gives' => ['Pedidos', 'Reseñas', 'Mensajes', 'Zonas de envío', 'Pasarela de pago', 'Marketing', 'Galería', 'Plantillas', 'Mi tienda'],
        ],
        'pos' => [
            'icon' => 'pos', 'title' => 'Punto de venta (POS)', 'ask' => 'Usar el punto de venta',
            'text' => 'Cobra en el mostrador: caja, ventas, comprobantes y fiado. Funciona solo, sin tienda virtual ni CRM.',
            'gives' => ['Caja / Vender', 'Ventas', 'Por cobrar', 'Comprobantes y SUNAT', 'Sedes y cajas'],
        ],
        'crm' => [
            'icon' => 'users', 'title' => 'CRM · Clientes', 'ask' => 'Usar el CRM',
            'text' => 'Una ficha por cliente con su historial, seguimientos y oportunidades, y la bandeja de WhatsApp.',
            'gives' => ['Clientes', 'Seguimientos', 'Oportunidades', 'WhatsApp'],
        ],
    ];
@endphp
<div class="mod-app w-full max-w-[1600px] mx-auto"
     x-data="{ store: @js($state['store']), pos: @js($state['pos']), crm: @js($state['crm']), simple: @js($state['simple']), was: @js($state),
               get none() { return !this.store && !this.pos; },
               turnsOff() { return ['store', 'pos', 'crm'].filter(k => this.was[k] && !this[k]); },
               labels: { store: 'la tienda virtual', pos: 'el punto de venta', crm: 'el CRM' },
               confirmOff() { const off = this.turnsOff(); return !off.length || confirm('Vas a ocultar ' + off.map(k => this.labels[k]).join(' y ') + '. No se borra nada: si lo vuelves a encender, todo sigue donde estaba.'); } }">

    <p class="mod-lead">Elige qué usas en tu negocio. El menú se adapta: <strong>solo ves lo que enciendes</strong>. Cada módulo funciona por separado.</p>

    @if($errors->any())<div class="mod-flash is-bad" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @unless($ready)<div class="mod-flash" role="note">Falta una actualización en el servidor de Tribio para cambiar los módulos.</div>@endunless

    <form method="POST" action="{{ route('dashboard.modules.update') }}" @submit="if (none || !confirmOff()) $event.preventDefault()">
        @csrf @method('PUT')

        @foreach($modules as $key => $module)
            @php $locked = !$ready || ($key === 'crm' && !$crmReady); @endphp
            <section class="mod-card" :class="{ 'is-on': {{ $key }} }" aria-labelledby="mod-{{ $key }}">
                <div class="mod-head">
                    <span class="mod-icon" aria-hidden="true"><x-dashboard-icon name="{{ $module['icon'] }}"/></span>
                    <div class="mod-title">
                        <h2 id="mod-{{ $key }}">{{ $module['title'] }}</h2>
                        <p>{{ $module['text'] }}</p>
                    </div>
                    <label class="mod-switch" title="{{ $module['ask'] }}">
                        <input type="checkbox" name="{{ $key }}" value="1" x-model="{{ $key }}" @disabled($locked) role="switch" aria-label="{{ $module['ask'] }}">
                        <span aria-hidden="true"></span>
                    </label>
                </div>
                <div class="mod-gives" x-show="{{ $key }}" x-cloak>
                    <small>Se habilita en tu menú:</small>
                    @foreach($module['gives'] as $item)<span class="mod-chip">{{ $item }}</span>@endforeach
                </div>
                <p class="mod-off" x-show="!{{ $key }}" x-cloak>
                    @if($key === 'store')No tendrás página pública ni pedidos en línea.@elseif($key === 'pos')Sin caja ni ventas de mostrador.@else Sin fichas de clientes ni WhatsApp; el punto de venta sigue funcionando igual.@endif
                </p>

                @if($key === 'pos')
                    <label class="mod-sub" x-show="pos && !store" x-cloak>
                        <input type="checkbox" name="simple" value="1" x-model="simple" @disabled($locked)>
                        <span><strong>Pantalla de venta rápida</strong> <small>(modo simple): botones grandes por producto, cobro en un toque con Efectivo, Yape o Plin. Ideal para celular.</small></span>
                    </label>
                @endif
                @if($key === 'crm' && !$crmReady)<p class="mod-off">Se activa con una actualización del servidor.</p>@endif
            </section>
        @endforeach

        <section class="mod-base" aria-label="Siempre incluido">
            <strong>Siempre incluido</strong>
            <p>Productos, Categorías, Inventario, Compras y Ganancias y pérdidas: los usan todos los módulos.</p>
        </section>

        <p class="mod-warn" x-show="none" x-cloak role="alert">Deja encendido al menos la tienda virtual o el punto de venta.</p>

        <div class="mod-actions">
            <button type="submit" class="btn-primary" :disabled="none || {{ $ready ? 'false' : 'true' }}">Guardar módulos</button>
        </div>
    </form>
</div>
@endsection
