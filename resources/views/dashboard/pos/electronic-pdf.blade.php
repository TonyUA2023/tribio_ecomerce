@php $money = fn ($value) => number_format((float) $value, 2); @endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1b2430; }
    h1, h2, p { margin: 0; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .emisor h1 { font-size: 15px; }
    .emisor p { margin-top: 3px; color: #46556a; line-height: 1.4; }
    .box { border: 1.5px solid #1b2430; border-radius: 6px; padding: 10px 8px; text-align: center; width: 190px; }
    .box .ruc { font-size: 11px; font-weight: bold; }
    .box .title { font-size: 11px; font-weight: bold; margin: 8px 0; }
    .box .num { font-size: 13px; font-weight: bold; }
    .meta { margin-top: 14px; border: 1px solid #c9d3df; border-radius: 6px; }
    .meta td { padding: 4px 8px; }
    .meta .k { color: #64758a; width: 90px; }
    .items { margin-top: 12px; }
    .items th { background: #eef2f7; text-align: left; padding: 5px 6px; font-size: 9px; border-bottom: 1px solid #c9d3df; }
    .items td { padding: 5px 6px; border-bottom: 1px solid #edf0f4; vertical-align: top; }
    .r { text-align: right; }
    .totals { margin-top: 10px; width: 230px; margin-left: auto; }
    .totals td { padding: 3px 6px; }
    .totals .grand td { font-size: 12px; font-weight: bold; border-top: 1.5px solid #1b2430; padding-top: 5px; }
    .words { margin-top: 10px; font-weight: bold; }
    .foot { margin-top: 16px; }
    .foot td { vertical-align: top; }
    .small { font-size: 8.5px; color: #64758a; line-height: 1.5; }
    .beta { margin-top: 8px; padding: 5px 8px; background: #fff4e0; color: #8a5200; border-radius: 4px; text-align: center; font-weight: bold; }
</style>
</head>
<body>
<table class="head"><tr>
    <td class="emisor">
        <h1>{{ mb_strtoupper($store->legal_name ?: $store->name) }}</h1>
        @if($store->legal_name && $store->legal_name !== $store->name)<p>{{ $store->name }}</p>@endif
        <p>{{ $branch?->address }}@if($branch?->district) · {{ $branch->district }}, {{ $branch->province }}, {{ $branch->department }}@endif</p>
        @if($store->phone)<p>Tel. {{ $store->phone }}</p>@endif
    </td>
    <td style="width:200px"><div class="box"><div class="ruc">R.U.C. {{ $store->ruc }}</div><div class="title">{{ $title }}</div><div class="num">{{ $doc->series }}-{{ str_pad((string) $doc->number, 8, '0', STR_PAD_LEFT) }}</div></div></td>
</tr></table>

<table class="meta">
    <tr><td class="k">Cliente</td><td>{{ $order->customer_name ?: 'CLIENTE VARIOS' }}</td><td class="k">Fecha de emisión</td><td>{{ $at->format('d/m/Y H:i') }}</td></tr>
    <tr><td class="k">{{ strlen(preg_replace('/\D+/', '', (string) $order->customer_document_number)) === 11 ? 'RUC' : 'DNI' }}</td><td>{{ $order->customer_document_number ?: '-' }}</td><td class="k">Moneda</td><td>SOLES</td></tr>
    <tr><td class="k">Forma de pago</td><td>{{ $order->installments->isEmpty() ? 'Contado' : 'Crédito' }}</td><td class="k">Local</td><td>{{ $branch?->name }} ({{ $branch?->sunat_code }})</td></tr>
</table>

<table class="items">
    <thead><tr><th>Cant.</th><th>Unid.</th><th>Descripción</th><th class="r">P. unit.</th><th class="r">Importe</th></tr></thead>
    <tbody>
    @foreach($order->items as $item)
        <tr><td>{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td><td>UND</td><td>{{ $item->product_name }}@if($item->variant_title) · {{ $item->variant_title }}@endif</td><td class="r">{{ $money($item->price) }}</td><td class="r">{{ $money($item->subtotal) }}</td></tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    @if((float) $order->discount > 0)<tr><td>Descuento</td><td class="r">- S/ {{ $money($order->discount) }}</td></tr>@endif
    <tr><td>Op. gravadas</td><td class="r">S/ {{ $money($taxable) }}</td></tr>
    <tr><td>IGV (18 %)</td><td class="r">S/ {{ $money($igv) }}</td></tr>
    <tr class="grand"><td>Importe total</td><td class="r">S/ {{ $money($total) }}</td></tr>
</table>
<p class="words">SON: {{ $words }}</p>

<table class="foot"><tr>
    <td style="width:110px"><img src="{{ $qr }}" width="100" height="100" alt="QR"></td>
    <td class="small">
        Representación impresa de la {{ $title }}.<br>
        @if($doc->hash)Resumen (hash): {{ $doc->hash }}<br>@endif
        Consulta su validez en www.sunat.gob.pe · Estado: {{ $doc->label() }}@if($doc->sunat_message) — {{ $doc->sunat_message }}@endif
    </td>
</tr></table>
@if($doc->environment === 'beta')<p class="beta">COMPROBANTE DE PRUEBA · sin valor tributario</p>@endif
</body>
</html>
