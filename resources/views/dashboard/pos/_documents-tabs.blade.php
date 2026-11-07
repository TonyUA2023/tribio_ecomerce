@php
    $tabs = [
        'data'      => ['Datos y series', route('dashboard.pos.documents.edit')],
        'branches'  => ['Sedes y cajas', route('dashboard.pos.documents.branches')],
        'sunat'     => ['SUNAT', route('dashboard.pos.documents.sunat')],
        'electronic' => ['Envíos a SUNAT', route('dashboard.pos.documents.electronic')],
    ];
@endphp
@if(app(\App\Services\Sunat\SunatSchema::class)->ready())
<nav class="flex items-center gap-2 flex-wrap" aria-label="Comprobantes">
    @foreach($tabs as $key => [$label, $url])
        <a href="{{ $url }}" class="pos-chip {{ $current === $key ? 'is-active' : '' }}" @if($current === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@endif
