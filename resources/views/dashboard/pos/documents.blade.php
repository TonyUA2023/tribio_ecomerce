@extends('layouts.dashboard')
@section('title', 'Datos y comprobantes')
@section('page_title', '🧾 Datos y comprobantes')
@section('content')
<div class="w-full max-w-3xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Elige en la caja si vendes con <strong>nota de venta</strong>, <strong>boleta de venta</strong> o <strong>factura</strong>. Cada una lleva su serie y su número correlativo.</p>
        <a href="{{ route('dashboard.pos.index', ['cajas' => 1]) }}" class="pos-chip"><x-dashboard-icon name="back"/>Caja</a>
    </div>

    <div class="glass-card p-4 text-xs text-white/70" role="note">
        <strong>Importante:</strong> la numeración y los datos de tu negocio ya salen en el comprobante, pero aún <strong>no se envían a SUNAT</strong>.
        Para que la boleta y la factura tengan validez tributaria hay que conectar un proveedor de facturación electrónica (OSE/PSE). La nota de venta es un documento interno y no necesita envío.
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('dashboard.pos.documents.update') }}" class="space-y-5">
        @csrf
        <section class="glass-card p-4 sm:p-5 space-y-4" aria-labelledby="issuer-title">
            <h2 id="issuer-title" class="text-white font-bold text-base">Datos de tu negocio</h2>
            <p class="text-xs text-white/60">Salen en el encabezado de tus tickets. El RUC y la razón social, en boletas y facturas.</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="input-label" for="biz-name">Nombre del negocio</label>
                    <input id="biz-name" name="name" class="input-field" maxlength="120" value="{{ old('name', $store->name) }}" required>
                </div>
                <div>
                    <label class="input-label" for="biz-phone">Teléfono</label>
                    <input id="biz-phone" name="phone" class="input-field" inputmode="tel" maxlength="30" value="{{ old('phone', $store->phone) }}" placeholder="900 000 000">
                </div>
                <div class="sm:col-span-2">
                    <label class="input-label" for="biz-address">Dirección</label>
                    <input id="biz-address" name="address" class="input-field" maxlength="255" value="{{ old('address', $store->address) }}" placeholder="Av. Los Constructores 123">
                </div>
                <div>
                    <label class="input-label" for="biz-city">Ciudad</label>
                    <input id="biz-city" name="city" class="input-field" maxlength="80" value="{{ old('city', $store->city) }}" placeholder="Chiclayo">
                </div>
                <div>
                    <label class="input-label" for="doc-ruc">RUC</label>
                    <input id="doc-ruc" name="ruc" class="input-field" inputmode="numeric" maxlength="11" value="{{ old('ruc', $store->ruc) }}" placeholder="20123456789">
                </div>
                <div class="sm:col-span-2">
                    <label class="input-label" for="doc-name">Razón social</label>
                    <input id="doc-name" name="legal_name" class="input-field" maxlength="160" value="{{ old('legal_name', $store->legal_name) }}" placeholder="{{ $store->name }} S.A.C.">
                </div>
            </div>
        </section>

        <section class="glass-card p-4 sm:p-5 space-y-4" aria-labelledby="series-title">
            <h2 id="series-title" class="text-white font-bold text-base">Series y numeración</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach($types as $type => $meta)
                    @php $row = $series[$type]; @endphp
                    <div class="space-y-1">
                        <label class="input-label" for="series-{{ $type }}">{{ $meta['label'] }}</label>
                        <input id="series-{{ $type }}" name="series[{{ $type }}]" class="input-field uppercase" maxlength="4" value="{{ old('series.' . $type, $row->series) }}">
                        <p class="text-[11px] text-white/50">
                            @if($row->last_number > 0)Último emitido: {{ \App\Services\Pos\SalesDocuments::code($row->series, $row->last_number) }}
                            @else Aún no emitiste ninguno. @endif
                        </p>
                    </div>
                @endforeach
            </div>
            <p class="text-[11px] text-white/50">Si cambias una serie, su numeración vuelve a empezar en 1. La boleta empieza con B y la factura con F.</p>
        </section>

        <div class="flex justify-end"><button type="submit" class="btn-primary">Guardar</button></div>
    </form>
</div>
@endsection
