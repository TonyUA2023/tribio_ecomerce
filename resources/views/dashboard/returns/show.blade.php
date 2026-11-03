@php
    $money = fn ($value) => number_format((float) $value, 2);
    $order = $return->order;
    $store = $order->store;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Devolución {{ $return->number }}</title>
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
        .flash { width: 80mm; max-width: 100%; margin: 16px auto 0; padding: 10px; background: #e8f6ee; color: #246c46; font: 600 13px system-ui, sans-serif; border-radius: 8px; }
        .actions { width: 80mm; max-width: 100%; margin: 0 auto 16px; display: flex; gap: 8px; }
        .actions button, .actions a { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid #ccc; background: #fff; font: 600 13px system-ui, sans-serif; color: #111; text-align: center; text-decoration: none; cursor: pointer; }
        @media print { @page { size: 80mm auto; margin: 0; } body { background: #fff; } .ticket { margin: 0; width: 80mm; padding: 2mm 3mm 4mm; } .actions, .flash { display: none; } }
    </style>
</head>
<body>
    @if(session('success'))<div class="flash" role="status">{{ session('success') }}</div>@endif
    <main class="ticket">
        <div class="center">
            <h1>{{ $store->name }}</h1>
            <hr>
            <strong>NOTA DE DEVOLUCIÓN</strong><br>{{ $return->number }}
        </div>
        <hr>
        <div>Fecha: {{ $return->created_at->copy()->setTimezone('America/Lima')->format('d/m/Y H:i') }}</div>
        <div>Venta: {{ $order->order_number }}</div>
        <div>Cliente: {{ $order->customer_name }}</div>
        <div>Motivo: {{ $return->reason }}</div>
        <hr>
        <table>
            @foreach($return->items as $item)
                <tr><td colspan="2">{{ $item['product_name'] }}</td></tr>
                <tr><td class="muted">{{ $item['quantity'] }} und.{{ $item['restock'] ? '' : ' · no vuelve al stock' }}</td><td class="r">{{ $money($item['amount']) }}</td></tr>
            @endforeach
        </table>
        <hr>
        <table>
            <tr class="total"><td>TOTAL S/</td><td class="r">{{ $money($return->total) }}</td></tr>
            @if((float) $return->debt_reduced > 0)<tr><td>Descontado de su deuda</td><td class="r">{{ $money($return->debt_reduced) }}</td></tr>@endif
            @if((float) $return->refunded > 0)<tr><td>Devuelto ({{ $methods[$return->method] ?? 'Pago' }})</td><td class="r">{{ $money($return->refunded) }}</td></tr>@endif
        </table>
        <div style="margin-top: 10mm; border-top: 1px solid #333; text-align: center; font-size: 10px">Firma del cliente</div>
        <div class="center muted" style="font-size: 10px; margin-top: 4px">Documento interno. No es nota de crédito electrónica.</div>
    </main>
    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="{{ route('dashboard.returns.index') }}">Devoluciones</a>
        <a href="{{ route('dashboard.pos.index') }}">Caja</a>
    </div>
    @if($print)<script>window.addEventListener('load', () => window.print());</script>@endif
</body>
</html>
