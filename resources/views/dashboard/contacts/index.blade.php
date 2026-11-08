@extends('layouts.dashboard')
@section('title', 'Clientes')
@section('page_title', '👥 Clientes')
@section('content')
@php
    use App\Http\Controllers\Dashboard\ContactController;
    use App\Models\Contact;
    $keep = fn (array $extra = []) => array_filter($extra + ['buscar' => $search ?? null, 'orden' => ($sort ?? null) === 'gasto' ? 'gasto' : null, 'etiqueta' => ($tag ?? '') ?: null]);
    $crm = (bool) auth()->user()->currentStore()?->hasCrm();
@endphp
<div class="cl-app">

@unless($ready)
    <div class="cl-notice">
        <strong>⏳ Estamos activando este módulo</strong>
        <p>Falta una actualización en el servidor de Tribio. Apenas esté lista verás aquí a todos tus clientes; no tienes que hacer nada.</p>
    </div>
@else
    <header class="cl-head">
        <div>
            <h2>{{ $total }} {{ $total === 1 ? 'cliente' : 'clientes' }}</h2>
            <p>Quien te compra, en caja o en tu tienda, queda aquí con su historial.</p>
        </div>
        @if($profile)
        <div class="cl-head-actions">
            <a href="{{ route('dashboard.clientes.create') }}" class="btn-primary">+ Nuevo</a>
            @if($crm)
            <div class="cl-menu" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button type="button" class="pos-chip" @click="open = !open" :aria-expanded="open" aria-haspopup="menu">Más<x-dashboard-icon name="chevron" class="cl-caret"/></button>
                <div class="cl-menu-list" x-show="open" x-cloak role="menu">
                    <a href="{{ route('dashboard.clientes.templates.index') }}" role="menuitem">💬 Plantillas</a>
                    <a href="{{ route('dashboard.clientes.deals.index') }}" role="menuitem">💼 Oportunidades</a>
                    <a href="{{ route('dashboard.clientes.tasks.index') }}" role="menuitem">Seguimientos @if($dueTasks > 0)<span class="badge badge-gold">{{ $dueTasks }}</span>@endif</a>
                </div>
            </div>
            @endif
        </div>
        @endif
    </header>

    @if($profile && $crm && $dueTasks > 0)
        <a href="{{ route('dashboard.clientes.tasks.index') }}" class="cl-alert">
            <span><strong>{{ $dueTasks }}</strong> {{ $dueTasks === 1 ? 'seguimiento para hoy' : 'seguimientos para hoy' }}</span><x-dashboard-icon name="chevron"/>
        </a>
    @endif

    <form method="GET" action="{{ route('dashboard.clientes.index') }}" class="cl-search" role="search">
        @if($segment)<input type="hidden" name="segmento" value="{{ $segment }}">@endif
        @if($stage)<input type="hidden" name="etapa" value="{{ $stage }}">@endif
        @if($sort === 'gasto')<input type="hidden" name="orden" value="gasto">@endif
        <x-dashboard-icon name="search" class="cl-search-icon"/>
        <label for="cl-q" class="sr-only">Buscar cliente</label>
        <input id="cl-q" type="search" name="buscar" value="{{ $search }}" class="input-field" placeholder="Nombre, celular, correo o DNI" autocomplete="off" data-1p-ignore="true" data-lpignore="true">
    </form>

    <div class="cl-chips" aria-label="Filtros">
        <a href="{{ route('dashboard.clientes.index', $keep()) }}" class="pos-chip {{ !$segment && !$stage ? 'is-active' : '' }}">Todos</a>
        @if($profile)
            @foreach(Contact::STAGES as $key => $info)
                <a href="{{ route('dashboard.clientes.index', $keep(['etapa' => $key])) }}" class="pos-chip {{ $stage === $key ? 'is-active' : '' }}">{{ $info['label'] }} <small>{{ $stageCounts[$key] ?? 0 }}</small></a>
            @endforeach
        @else
            @foreach(ContactController::SEGMENTS as $key => $label)
                <a href="{{ route('dashboard.clientes.index', $keep(['segmento' => $key])) }}" class="pos-chip {{ $segment === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        @endif
        <a href="{{ route('dashboard.clientes.index', array_filter(['segmento' => $segment, 'etapa' => $stage, 'buscar' => $search, 'etiqueta' => $tag ?: null, 'orden' => $sort === 'gasto' ? null : 'gasto'])) }}" class="pos-chip">↕ {{ $sort === 'gasto' ? 'Más gasto' : 'Más reciente' }}</a>
    </div>
    @if($tag)
        <p class="cl-tagnote">Con la etiqueta <span class="badge badge-blue">{{ $tag }}</span> · <a href="{{ route('dashboard.clientes.index', array_filter(['etapa' => $stage, 'buscar' => $search])) }}">quitar</a></p>
    @endif

    @if($contacts->isEmpty())
        <div class="cl-empty">
            <strong>{{ $search || $segment || $stage || $tag ? 'Ningún cliente coincide con tu búsqueda.' : 'Aún no tienes clientes.' }}</strong>
            <p>{{ $search || $segment || $stage || $tag ? 'Prueba con otro nombre o quita el filtro.' : 'Aparecerán aquí solos con tu primera venta o pedido, o agrégalos tú.' }}</p>
        </div>
    @else
        <div class="cl-list">
            @foreach($contacts as $contact)
            <a href="{{ route('dashboard.clientes.show', $contact->id) }}" class="cl-row">
                <span class="cl-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($contact->name, 0, 1)) }}</span>
                <span class="cl-row-main">
                    <strong>{{ $contact->name }}@if($profile && $contact->stage_label)<span class="badge {{ $contact->stage_badge }}">{{ $contact->stage_label }}</span>@endif</strong>
                    <small>{{ $contact->phone_display ?? $contact->email ?? ($contact->document_number ? 'DNI ' . $contact->document_number : 'Sin datos de contacto') }}@if($profile && $contact->tags) · {{ implode(', ', $contact->tags) }}@endif</small>
                </span>
                <span class="cl-row-side">
                    @if((float) $contact->total_spent > 0)<strong>S/ {{ number_format((float) $contact->total_spent, 2) }}</strong>
                    @else<strong class="is-muted">{{ $contact->orders_count ? 'Sin pagos aún' : 'Sin compras' }}</strong>@endif
                    <small>{{ $contact->orders_count }} {{ $contact->orders_count === 1 ? 'compra' : 'compras' }}@if($contact->last_order_at) · {{ $contact->last_order_at->locale('es')->diffForHumans(null, true) }}@endif</small>
                </span>
                <x-dashboard-icon name="chevron" class="cl-row-go"/>
            </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $contacts->links() }}</div>
    @endif
@endunless
</div>
@endsection
