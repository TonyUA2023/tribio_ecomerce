@extends('layouts.dashboard')
@section('title', 'Comprobantes enviados')
@section('page_title', '📨 Comprobantes enviados')
@section('content')
<div class="w-full max-w-4xl mx-auto space-y-5">
    @include('dashboard.pos._documents-tabs', ['current' => 'electronic'])

    @if(session('success'))<div class="glass-card p-3 text-xs text-emerald-300" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="glass-card p-3 text-xs text-red-300" role="alert">{{ session('error') }}</div>@endif

    <div class="flex items-center gap-2 flex-wrap" aria-label="Estado">
        <a href="{{ route('dashboard.pos.documents.electronic') }}" class="pos-chip {{ !$status ? 'is-active' : '' }}">Todos</a>
        @foreach(\App\Models\ElectronicDocument::STATUSES as $key => $meta)
            <a href="{{ route('dashboard.pos.documents.electronic', ['estado' => $key]) }}" class="pos-chip {{ $status === $key ? 'is-active' : '' }}">{{ $meta['label'] }} <small>{{ $counts[$key] ?? 0 }}</small></a>
        @endforeach
    </div>
    @unless($setting->enabled)<p class="text-xs text-amber-300">La facturación electrónica está desactivada: <a href="{{ route('dashboard.pos.documents.sunat') }}" class="underline">actívala aquí</a>.</p>@endunless

    @if($docs->isEmpty())
        <div class="glass-card p-6 text-center text-sm text-white/60">Todavía no enviaste ningún comprobante a SUNAT. Al cobrar una boleta o factura, activa «Enviar a SUNAT».</div>
    @else
        <div class="glass-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead><tr class="text-left text-white/50"><th class="p-3">Comprobante</th><th class="p-3">Cliente</th><th class="p-3 text-right">Total</th><th class="p-3">Estado</th><th class="p-3">Acciones</th></tr></thead>
                    <tbody>
                    @foreach($docs as $doc)
                        <tr class="border-t border-white/10 align-top">
                            <td class="p-3"><strong class="text-white font-mono">{{ $doc->code() }}</strong><br><span class="text-white/50">{{ $doc->type === 'factura' ? 'Factura' : 'Boleta' }} · {{ $doc->created_at->copy()->setTimezone('America/Lima')->format('d/m/Y H:i') }}@if($doc->environment === 'beta') · prueba @endif</span></td>
                            <td class="p-3 text-white/80">{{ $doc->order?->customer_name }}</td>
                            <td class="p-3 text-right text-white">S/ {{ number_format((float) $doc->order?->total, 2) }}</td>
                            <td class="p-3"><span class="badge {{ $doc->badge() }}">{{ $doc->label() }}</span>@if($doc->sunat_message)<p class="text-white/50 mt-1 max-w-xs break-words">{{ \Illuminate\Support\Str::limit($doc->sunat_message, 140) }}</p>@endif @if($doc->canRetry() && $doc->attempts)<p class="text-white/40 mt-1">Intentos: {{ $doc->attempts }}</p>@endif</td>
                            <td class="p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('dashboard.pos.documents.electronic.download', [$doc->id, 'pdf']) }}" target="_blank" class="underline text-white/80 min-h-[36px] inline-flex items-center">PDF</a>
                                    @if($doc->isDelivered() || $doc->status === 'rejected')<a href="{{ route('dashboard.pos.documents.electronic.download', [$doc->id, 'xml']) }}" class="underline text-white/80 min-h-[36px] inline-flex items-center">XML</a>@endif
                                    @if($doc->isDelivered())<a href="{{ route('dashboard.pos.documents.electronic.download', [$doc->id, 'cdr']) }}" class="underline text-white/80 min-h-[36px] inline-flex items-center">CDR</a>@endif
                                    @if($doc->canRetry())<form method="POST" action="{{ route('dashboard.pos.documents.electronic.retry', $doc->id) }}">@csrf<button type="submit" class="btn-secondary !py-1.5 text-xs">Reenviar</button></form>@endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div>{{ $docs->links() }}</div>
    @endif
</div>
@endsection
