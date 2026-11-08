@extends('layouts.dashboard')
@section('title', 'Envíos a SUNAT')
@section('page_title', '📨 Envíos a SUNAT')
@section('content')
@php
    $tabs = ['por_enviar' => 'Por enviar', 'problemas' => 'Con problemas', 'enviados' => 'Enviados', 'internos' => 'Internos'];
    $lima = fn ($date) => $date->copy()->setTimezone('America/Lima');
@endphp
<div class="w-full max-w-[1600px] mx-auto space-y-5">
    @include('dashboard.pos._documents-tabs', ['current' => 'electronic'])

    @if(session('success'))<div class="glass-card p-3 text-xs text-emerald-300" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="glass-card p-3 text-xs text-red-300" role="alert">{{ session('error') }}</div>@endif

    <p class="text-sm text-white/60 max-w-3xl">Cada boleta y factura sale numerada al cobrar, y ese número no cambia. Aquí decides cuáles informas a SUNAT: al pulsar <strong>Enviar a SUNAT</strong> se genera el comprobante electrónico con ese mismo número.</p>

    @unless($setting->enabled)
        <div class="glass-card p-3 text-xs text-amber-300" role="note">La facturación electrónica está desactivada: <a href="{{ route('dashboard.pos.documents.sunat') }}" class="underline">actívala en SUNAT</a> para poder enviar.</div>
    @endunless

    {{-- Control de numeración --}}
    @if($numbering)
    <section class="glass-card p-4 sm:p-5" aria-labelledby="num-title">
        <h2 id="num-title" class="text-white font-bold text-base">Control de numeración</h2>
        <div class="overflow-x-auto mt-3">
            <table class="w-full text-xs">
                <thead><tr class="text-left text-white/50"><th class="py-2 pr-3">Serie</th><th class="pr-3">Emitidos</th><th class="pr-3">Último número</th><th class="pr-3">En SUNAT</th><th class="pr-3">Sin enviar</th></tr></thead>
                <tbody>
                @foreach($numbering as $row)
                    <tr class="border-t border-white/10">
                        <td class="py-2 pr-3 text-white"><span class="font-mono font-semibold">{{ $row['series'] }}</span> <span class="text-white/50">{{ $row['type'] === 'factura' ? 'Factura' : 'Boleta' }}</span></td>
                        <td class="pr-3 text-white/80">{{ $row['emitted'] }}</td>
                        <td class="pr-3 font-mono text-white/80">{{ \App\Services\Pos\SalesDocuments::code($row['series'], $row['last']) }}</td>
                        @if($row['internal'])
                            <td class="pr-3 text-white/50" colspan="2">Serie interna: no se informa a SUNAT</td>
                        @else
                            <td class="pr-3 text-emerald-300">{{ $row['delivered'] }}</td>
                            <td class="pr-3 {{ $row['waiting'] ? 'text-amber-300' : 'text-white/50' }}">{{ $row['waiting'] ?: '—' }}@if($row['waiting'] && $row['oldest']) <span class="text-white/40">· desde {{ \App\Services\Pos\SalesDocuments::code($row['series'], $row['oldest']) }}</span>@endif</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-[11px] text-white/50 mt-3">Si algunas boletas no se informan a SUNAT, lo ideal es que salgan de una <strong>serie propia marcada como «solo interna»</strong> (en Sedes y cajas): así las series que sí se informan no quedan con números saltados. Consúltalo con tu contador.</p>
    </section>
    @endif

    <div class="flex items-center gap-2 flex-wrap" aria-label="Filtro">
        @foreach($tabs as $key => $label)
            <a href="{{ route('dashboard.pos.documents.electronic', ['estado' => $key]) }}" class="pos-chip {{ $filter === $key ? 'is-active' : '' }}" @if($filter === $key) aria-current="page" @endif>{{ $label }} <small>{{ $counts[$key] }}</small></a>
        @endforeach
        @if($filter === 'por_enviar' && $counts['por_enviar'] > 0 && $setting->enabled)
            <form method="POST" action="{{ route('dashboard.pos.documents.submit-pending') }}" class="ml-auto" onsubmit="return confirm('¿Enviar a SUNAT los comprobantes pendientes (hasta 20 a la vez)?')">@csrf<button type="submit" class="btn-primary text-xs">Enviar todo lo pendiente</button></form>
        @endif
    </div>

    @if($orders->isEmpty())
        <div class="glass-card p-6 text-center text-sm text-white/60">
            @switch($filter)
                @case('por_enviar') No tienes comprobantes por enviar. Las boletas y facturas nuevas aparecerán aquí al cobrarlas. @break
                @case('problemas') Ningún comprobante tiene problemas. @break
                @case('enviados') Todavía no enviaste ningún comprobante a SUNAT. @break
                @default No hay comprobantes internos.
            @endswitch
        </div>
    @else
        <div class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-left text-white/50"><th class="p-3">Comprobante</th><th class="p-3">Cliente</th><th class="p-3 text-right">Total</th><th class="p-3">Estado</th><th class="p-3">Acciones</th></tr></thead>
                    <tbody>
                    @foreach($orders as $order)
                        @php $st = $states[$order->id]; $doc = $st['doc']; @endphp
                        <tr class="border-t border-white/10 align-top">
                            <td class="p-3"><strong class="text-white font-mono">{{ $order->document_code }}</strong><br><span class="text-white/50">{{ $order->document_type === 'factura' ? 'Factura' : 'Boleta' }} · {{ $lima($order->created_at)->format('d/m/Y H:i') }}@if($doc && $doc->environment === 'beta') · prueba @endif</span></td>
                            <td class="p-3 text-white/80">{{ $order->customer_name }}</td>
                            <td class="p-3 text-right text-white">S/ {{ number_format((float) $order->total, 2) }}</td>
                            <td class="p-3">
                                @if($doc)
                                    <span class="badge {{ $doc->badge() }}">{{ $doc->label() }}</span>
                                    @if($doc->sunat_message)<p class="text-white/50 mt-1 max-w-xs break-words">{{ \Illuminate\Support\Str::limit($doc->sunat_message, 140) }}</p>@endif
                                @elseif($st['state'] === 'internal')
                                    <span class="badge badge-blue">Interno</span><p class="text-white/50 mt-1">No se informa a SUNAT</p>
                                @else
                                    <span class="badge badge-gold">Sin enviar</span>
                                    @if($st['deadline'])<p class="text-white/50 mt-1">Enviar antes del {{ $st['deadline']->format('d/m/Y') }}</p>@endif
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($st['can'])
                                        <form method="POST" action="{{ route('dashboard.pos.documents.submit', $order->id) }}">@csrf<button type="submit" class="btn-primary !py-1.5 text-xs">{{ $doc ? 'Reenviar' : 'Enviar a SUNAT' }}</button></form>
                                    @elseif($st['why'] && $st['state'] !== 'internal' && $setting->enabled)
                                        <span class="text-amber-300 max-w-[200px]">{{ $st['why'] }}</span>
                                    @endif
                                    <a href="{{ route('dashboard.pos.receipt', $order->id) }}" target="_blank" rel="noopener" class="underline text-white/80 min-h-[36px] inline-flex items-center">Ticket</a>
                                    @if($doc)
                                        <a href="{{ route('dashboard.pos.documents.electronic.download', [$doc->id, 'pdf']) }}" target="_blank" class="underline text-white/80 min-h-[36px] inline-flex items-center">PDF</a>
                                        @if($doc->isDelivered() || $doc->status === 'rejected')<a href="{{ route('dashboard.pos.documents.electronic.download', [$doc->id, 'xml']) }}" class="underline text-white/80 min-h-[36px] inline-flex items-center">XML</a>@endif
                                        @if($doc->isDelivered())<a href="{{ route('dashboard.pos.documents.electronic.download', [$doc->id, 'cdr']) }}" class="underline text-white/80 min-h-[36px] inline-flex items-center">CDR</a>@endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div>{{ $orders->links() }}</div>
    @endif
</div>
@endsection
