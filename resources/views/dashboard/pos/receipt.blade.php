@php
    $money = fn ($value) => number_format((float) $value, 2);
    $store = $order->store;
    $at = $order->created_at->copy()->setTimezone('America/Lima');
    $fiscal = in_array($order->document_type, ['boleta', 'factura'], true);
    $isInvoice = $order->document_type === 'factura';
    $igv = \App\Services\Pos\SalesDocuments::breakdown((float) $order->total);
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $order->document_label }} {{ $order->document_code ?? $order->order_number }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef0f3; color: #111; font: 12px/1.35 ui-monospace, 'Courier New', monospace; }
        .ticket { width: 80mm; max-width: 100%; margin: 16px auto; background: #fff; padding: 4mm 4mm 6mm; }
        .center { text-align: center; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .muted { color: #444; }
        hr { border: 0; border-top: 1px dashed #777; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.r { text-align: right; white-space: nowrap; padding-left: 6px; }
        .total td { font-size: 15px; font-weight: 700; padding-top: 3px; }
        .sunat { width: 80mm; max-width: 100%; margin: -8px auto 16px; padding: 10px 12px; background: #fff; border-radius: 8px; font: 13px/1.4 system-ui, sans-serif; color: #111; }
        .sunat strong { display: block; margin-bottom: 2px; }
        .sunat small { color: #555; display: block; margin-top: 2px; }
        .sunat form { margin-top: 8px; }
        .sunat button { width: 100%; padding: 11px; border-radius: 8px; border: 0; background: #1b8a5a; color: #fff; font: 700 14px system-ui, sans-serif; cursor: pointer; }
        .sunat .ok { color: #146c43; } .sunat .bad { color: #b42318; } .sunat .warn { color: #8a5200; }
        .flash { width: 80mm; max-width: 100%; margin: 0 auto 10px; padding: 9px 12px; border-radius: 8px; font: 600 13px system-ui, sans-serif; }
        .flash.ok { background: #e6f6ef; color: #146c43; } .flash.bad { background: #fdecec; color: #b42318; }
        .actions { width: 80mm; max-width: 100%; margin: 0 auto 16px; display: flex; gap: 8px; }
        .actions button, .actions a { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid #ccc; background: #fff; font: 600 13px system-ui, sans-serif; color: #111; text-align: center; text-decoration: none; cursor: pointer; }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { background: #fff; }
            .ticket { margin: 0; width: 80mm; padding: 2mm 3mm 4mm; }
            .actions, .sunat, .flash { display: none; }
        }
    </style>
</head>
<body>
    <main class="ticket">
        <div class="center">
            <h1>{{ $fiscal && $store->legal_name ? $store->legal_name : $store->name }}</h1>
            @if($fiscal && $store->legal_name && $store->legal_name !== $store->name)<div class="muted">{{ $store->name }}</div>@endif
            @if($fiscal && $store->ruc)<div><strong>RUC {{ $store->ruc }}</strong></div>@endif
            @if($store->address)<div class="muted">{{ $store->address }}{{ $store->city ? ', ' . $store->city : '' }}</div>@endif
            @if($store->phone || $store->whatsapp_phone)<div class="muted">Tel. {{ $store->phone ?: $store->whatsapp_phone }}</div>@endif
            <hr>
            <strong>{{ mb_strtoupper($order->document_label) }}</strong><br>{{ $order->document_code ?? $order->order_number }}
            @if($order->document_code)<div class="muted" style="font-size: 10px">Pedido {{ $order->order_number }}</div>@endif
        </div>
        <hr>
        <div>Fecha: {{ $at->format('d/m/Y H:i') }}</div>
        @if($order->registerSession)<div>Caja: {{ $order->registerSession->register->name }}</div>@endif
        <div>{{ $isInvoice ? 'Razón social' : 'Cliente' }}: {{ $order->customer_name }}</div>
        @if($order->customer_document_number)<div>{{ $order->customer_document_type ?: 'Doc.' }}: {{ $order->customer_document_number }}</div>@endif
        @if($isInvoice && $order->customer_address)<div>Dirección: {{ $order->customer_address }}</div>@endif
        <hr>
        <table>
            @foreach($order->items as $item)
                <tr><td colspan="2">{{ $item->product_name }}{{ $item->variant_title ? ' · ' . $item->variant_title : '' }}</td></tr>
                <tr>
                    <td class="muted">
                        @if($item->unit_name)
                            {{ $item->unit_quantity }} {{ $item->unit_name }} × {{ $money((float) $item->subtotal / max(1, $item->unit_quantity)) }}
                        @else
                            {{ $item->quantity }} × {{ $money($item->price) }}
                        @endif
                    </td>
                    <td class="r">{{ $money($item->subtotal) }}</td>
                </tr>
            @endforeach
        </table>
        <hr>
        <table>
            @if((float) $order->discount > 0)
                <tr><td>Subtotal</td><td class="r">{{ $money($order->subtotal) }}</td></tr>
                <tr><td>Descuento</td><td class="r">−{{ $money($order->discount) }}</td></tr>
            @endif
            @if($fiscal)
                <tr><td>Op. gravada</td><td class="r">{{ $money($igv['taxable']) }}</td></tr>
                <tr><td>IGV (18%)</td><td class="r">{{ $money($igv['igv']) }}</td></tr>
            @endif
            <tr class="total"><td>TOTAL S/</td><td class="r">{{ $money($order->total) }}</td></tr>
            @foreach($order->payments->where('status', 'paid') as $payment)
                <tr><td>{{ $methods[$payment->method] ?? 'Pago' }}</td><td class="r">{{ $money($payment->method === 'cash' && $order->cash_tendered ? $order->cash_tendered : $payment->amount) }}</td></tr>
            @endforeach
            @if((float) $order->change_given > 0)<tr><td>Vuelto</td><td class="r">{{ $money($order->change_given) }}</td></tr>@endif
        </table>
        @if($order->due_date && $order->relationLoaded('installments') && $order->installments->isNotEmpty())
            <hr>
            <table>
                <tr><td><strong>AL CRÉDITO</strong></td><td class="r"><strong>Debe {{ $money($order->balance_due) }}</strong></td></tr>
                @foreach($order->installments as $installment)
                    <tr><td>Cuota {{ $installment->number }} · {{ $installment->due_date->format('d/m/Y') }}</td><td class="r">{{ $money($installment->amount) }}</td></tr>
                @endforeach
            </table>
            <div style="margin-top: 10mm; border-top: 1px solid #333; text-align: center; font-size: 10px">Firma del cliente</div>
        @endif
        <hr>
        <div class="center">¡Gracias por tu compra!</div>
        @unless($fiscal)<div class="center muted" style="font-size: 10px; margin-top: 4px">Documento interno. No es comprobante de pago electrónico.</div>@endunless
    </main>
    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="{{ route('dashboard.pos.index') }}">Volver a la caja</a>
        @if(app(\App\Services\Pos\PosSchema::class)->extrasReady() && !in_array($order->status, ['cancelled', 'refunded'], true))<a href="{{ route('dashboard.returns.create', $order->id) }}">Devolver</a>@endif
    </div>
    @if(session('success'))<div class="flash ok" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="flash bad" role="alert">{{ session('error') }}</div>@endif
    @if($sunat)
        @php $doc = $sunat['doc']; @endphp
        <section class="sunat" aria-label="SUNAT">
            @if($sunat['state'] === 'internal')
                <strong>Comprobante interno</strong><small>Esta serie no se informa a SUNAT.</small>
            @elseif($doc && $doc->isDelivered())
                <strong class="ok">✓ SUNAT recibió {{ $doc->code() }}</strong>
                <small>{{ $doc->label() }}@if($doc->environment === 'beta') · ambiente de pruebas @endif</small>
                <small><a href="{{ route('dashboard.pos.documents.electronic.download', [$doc->id, 'pdf']) }}" target="_blank">PDF</a> · <a href="{{ route('dashboard.pos.documents.electronic.download', [$doc->id, 'xml']) }}">XML</a>· <a href="{{ route('dashboard.pos.documents.electronic.download', [$doc->id, 'cdr']) }}">CDR</a></small>
            @elseif($doc && $doc->status === 'rejected')
                <strong class="bad">SUNAT rechazó este comprobante</strong><small>{{ $doc->sunat_message }}</small>
            @else
                <strong class="{{ $doc ? 'bad' : 'warn' }}">{{ $doc ? 'No se pudo enviar a SUNAT' : 'Todavía no se envió a SUNAT' }}</strong>
                @if($doc?->sunat_message)<small>{{ $doc->sunat_message }}</small>@endif
                @if($sunat['can'])
                    <form method="POST" action="{{ route('dashboard.pos.documents.submit', $order->id) }}">@csrf<button type="submit">{{ $doc ? 'Reintentar envío' : 'Enviar a SUNAT' }}</button></form>
                    @if($sunat['deadline'])<small>Plazo: hasta el {{ $sunat['deadline']->format('d/m/Y') }}. Se enviará con el número {{ $order->document_code }}.</small>@endif
                @elseif($sunat['why'])
                    <small>{{ $sunat['why'] }}</small>
                @endif
            @endif
        </section>
    @endif
    @if($print)<script>window.addEventListener('load', () => window.print());</script>@endif
</body>
</html>
