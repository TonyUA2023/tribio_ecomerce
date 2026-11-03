@extends('layouts.dashboard')
@section('title', 'Clientes')
@section('page_title', '👥 Clientes')
@section('content')
@php
    use App\Http\Controllers\Dashboard\ContactController;
    use App\Models\Contact;
    $chipBase = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-semibold transition whitespace-nowrap';
    $chipOn   = 'bg-tribio-cyan/10 border-tribio-cyan/40 text-tribio-cyan';
    $chipOff  = 'bg-white/5 border-white/10 text-white/60 hover:border-white/20';
    $keep = fn (array $extra = []) => array_filter($extra + ['buscar' => $search ?? null, 'orden' => ($sort ?? null) === 'gasto' ? 'gasto' : null, 'etiqueta' => ($tag ?? '') ?: null]);
@endphp
<div class="w-full max-w-5xl mx-auto">

@unless($ready)
    <div class="glass-card p-4 sm:p-5">
        <p class="text-white font-bold text-sm">⏳ Estamos activando este módulo</p>
        <p class="text-xs text-white/50 mt-1">Falta una actualización en el servidor de Tribio. Apenas esté lista verás aquí a todos tus clientes; no tienes que hacer nada.</p>
    </div>
@else
    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <p class="text-sm text-white/60 max-w-2xl">Cada persona que te compra queda aquí con todos sus pedidos, aunque haya comprado por la tienda o por WhatsApp. {{ $total }} {{ $total === 1 ? 'cliente' : 'clientes' }} en total.</p>
        @if($profile)
        <div class="flex gap-2">
            <a href="{{ route('dashboard.clientes.templates.index') }}" class="btn-ghost text-xs sm:text-sm">💬 Plantillas</a>
            <a href="{{ route('dashboard.clientes.deals.index') }}" class="btn-ghost text-xs sm:text-sm">💼 Oportunidades</a>
            <a href="{{ route('dashboard.clientes.tasks.index') }}" class="btn-secondary text-xs sm:text-sm">
                Seguimientos @if($dueTasks > 0)<span class="badge badge-gold ml-1">{{ $dueTasks }}</span>@endif
            </a>
            <a href="{{ route('dashboard.clientes.create') }}" class="btn-primary text-xs sm:text-sm">+ Nuevo cliente</a>
        </div>
        @endif
    </div>

    {{-- Buscador --}}
    <form method="GET" action="{{ route('dashboard.clientes.index') }}" class="flex gap-2 mb-4">
        @if($segment)<input type="hidden" name="segmento" value="{{ $segment }}">@endif
        @if($stage)<input type="hidden" name="etapa" value="{{ $stage }}">@endif
        @if($sort === 'gasto')<input type="hidden" name="orden" value="gasto">@endif
        <input type="search" name="buscar" value="{{ $search }}" class="input-field flex-1 min-w-0" placeholder="Nombre, celular, correo o DNI">
        <button type="submit" class="btn-primary flex-shrink-0">Buscar</button>
    </form>

    {{-- Filtros: etapas (CRM) o grupos simples si el CRM aún no está activo --}}
    <div class="flex flex-wrap gap-2 mb-5">
        <a href="{{ route('dashboard.clientes.index', $keep()) }}" class="{{ $chipBase }} {{ !$segment && !$stage ? $chipOn : $chipOff }}">Todos</a>
        @if($profile)
            @foreach(Contact::STAGES as $key => $info)
                <a href="{{ route('dashboard.clientes.index', $keep(['etapa' => $key])) }}" class="{{ $chipBase }} {{ $stage === $key ? $chipOn : $chipOff }}">
                    {{ $info['label'] }} <span class="opacity-70">{{ $stageCounts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        @else
            @foreach(ContactController::SEGMENTS as $key => $label)
                <a href="{{ route('dashboard.clientes.index', $keep(['segmento' => $key])) }}" class="{{ $chipBase }} {{ $segment === $key ? $chipOn : $chipOff }}">{{ $label }}</a>
            @endforeach
        @endif
        <a href="{{ route('dashboard.clientes.index', array_filter(['segmento' => $segment, 'etapa' => $stage, 'buscar' => $search, 'etiqueta' => $tag ?: null, 'orden' => $sort === 'gasto' ? null : 'gasto'])) }}"
           class="{{ $chipBase }} {{ $chipOff }} sm:ml-auto">↕ {{ $sort === 'gasto' ? 'Ordenado por: más gasto' : 'Ordenado por: compra más reciente' }}</a>
    </div>
    @if($tag)
        <p class="text-xs text-white/60 -mt-3 mb-4">Con la etiqueta <span class="badge badge-blue">{{ $tag }}</span> · <a href="{{ route('dashboard.clientes.index', array_filter(['etapa' => $stage, 'buscar' => $search])) }}" class="underline">quitar</a></p>
    @endif

    @if($contacts->isEmpty())
        <div class="glass-card p-6 text-center">
            <p class="text-white font-semibold">{{ $search || $segment || $stage || $tag ? 'Ningún cliente coincide con tu búsqueda.' : 'Aún no tienes clientes.' }}</p>
            <p class="text-xs text-white/50 mt-1">{{ $search || $segment || $stage || $tag ? 'Prueba con otro nombre o quita el filtro.' : 'Aparecerán aquí solos cuando recibas tu primer pedido, o agrégalos tú.' }}</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($contacts as $contact)
            <a href="{{ route('dashboard.clientes.show', $contact->id) }}" class="glass-card p-3.5 sm:p-4 flex items-center gap-3 hover:border-tribio-cyan/40 transition">
                <span class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-white font-bold flex-shrink-0">{{ mb_strtoupper(mb_substr($contact->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-white font-semibold truncate">
                        {{ $contact->name }}
                        @if($profile && $contact->stage_label)<span class="badge {{ $contact->stage_badge }} ml-1">{{ $contact->stage_label }}</span>@endif
                    </p>
                    <p class="text-xs text-white/50 truncate">
                        {{ $contact->phone_display ?? $contact->email ?? ($contact->document_number ? 'DNI ' . $contact->document_number : '') }}
                        @if($profile && $contact->tags) · {{ implode(', ', $contact->tags) }} @endif
                    </p>
                </div>
                <div class="text-right flex-shrink-0">
                    @if((float) $contact->total_spent > 0)
                        <p class="text-sm text-white font-semibold">S/ {{ number_format((float) $contact->total_spent, 2) }}</p>
                    @else
                        <p class="text-xs text-white/50 font-semibold">{{ $contact->orders_count ? 'Sin pagos aún' : 'Sin compras' }}</p>
                    @endif
                    <p class="text-[11px] text-white/40">{{ $contact->orders_count }} {{ $contact->orders_count === 1 ? 'pedido' : 'pedidos' }}@if($contact->last_order_at) · {{ $contact->last_order_at->locale('es')->diffForHumans() }}@endif</p>
                </div>
            </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $contacts->links() }}</div>
    @endif
@endunless
</div>
@endsection
