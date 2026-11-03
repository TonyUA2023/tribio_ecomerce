@php
    $money = fn ($value) => number_format((float) $value, 2);
    $store = $quote->store;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Proforma {{ $quote->number }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef0f3; color: #111; font: 14px/1.45 system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .sheet { max-width: 720px; margin: 16px auto; background: #fff; padding: 28px; border-radius: 8px; }
        header { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; border-bottom: 2px solid #111; padding-bottom: 12px; }
        h1 { font-size: 20px; margin: 0; }
        .muted { color: #555; font-size: 12px; }
        .doc { text-align: right; }
        .doc strong { font-size: 16px; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th { text-align: left; font-size: 12px; color: #555; border-bottom: 1px solid #ccc; padding: 6px 4px; }
        td { padding: 6px 4px; border-bottom: 1px solid #eee; vertical-align: top; }
        .r { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; width: 260px; margin-top: 12px; }
        .totals td { border: 0; padding: 3px 4px; }
        .grand td { font-size: 17px; font-weight: 700; border-top: 2px solid #111; }
        .note { margin-top: 18px; font-size: 12px; color: #444; }
        .actions { max-width: 720px; margin: 0 auto 16px; display: flex; gap: 8px; flex-wrap: wrap; }
        .actions a, .actions button { flex: 1; padding: 10px; border-radius: 8px; border: 1px solid #ccc; background: #fff; font: 600 13px system-ui, sans-serif; color: #111; text-align: center; text-decoration: none; cursor: pointer; }
        @media (max-width: 520px) { .sheet { padding: 16px; margin: 0; border-radius: 0; } .doc { text-align: left; } }
        @media print { body { background: #fff; } .sheet { margin: 0; max-width: none; } .actions { display: none; } }
    </style>
</head>
<body>
    <main class="sheet">
        <header>
            <div>
                <h1>{{ $store->name }}</h1>
                @if($store->address)<div class="muted">{{ $store->address }}{{ $store->city ? ', ' . $store->city : '' }}</div>@endif
                @if($store->phone || $store->whatsapp_phone)<div class="muted">Tel. {{ $store->phone ?: $store->whatsapp_phone }}</div>@endif
            </div>
            <div class="doc">
                <strong>PROFORMA</strong>
                {{ $quote->number }}<br>
                <span class="muted">{{ $quote->created_at->copy()->setTimezone('America/Lima')->format('d/m/Y') }} · válida hasta el {{ $quote->valid_until->format('d/m/Y') }}</span>
            </div>
        </header>
        <p style="margin: 12px 0 0">Cliente: <strong>{{ $quote->customer_name }}</strong>{{ $quote->customer_document ? ' · ' . (strlen($quote->customer_document) === 11 ? 'RUC' : 'DNI') . ' ' . $quote->customer_document : '' }}</p>
        <table>
            <thead><tr><th>Producto</th><th class="r">Cant.</th><th class="r">Precio</th><th class="r">Importe</th></tr></thead>
            <tbody>
                @foreach($quote->items as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td class="r">{{ $item['quantity'] }}{{ $item['unit_name'] ? ' ' . $item['unit_name'] : '' }}</td>
                        <td class="r">S/ {{ $money($item['price']) }}</td>
                        <td class="r">S/ {{ $money($item['subtotal']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <table class="totals">
            @if((float) $quote->discount > 0)
                <tr><td>Subtotal</td><td class="r">S/ {{ $money($quote->subtotal) }}</td></tr>
                <tr><td>Descuento</td><td class="r">−S/ {{ $money($quote->discount) }}</td></tr>
            @endif
            <tr class="grand"><td>Total</td><td class="r">S/ {{ $money($quote->total) }}</td></tr>
        </table>
        @if($quote->notes)<p class="note">{{ $quote->notes }}</p>@endif
        <p class="note">Precios en soles. Esta proforma no separa stock y no es un comprobante de pago.</p>
    </main>
    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
        @if($whatsapp)<a href="{{ $whatsapp }}" target="_blank" rel="noopener">Enviar por WhatsApp</a>@endif
        <a href="{{ route('dashboard.quotes.index') }}">Proformas</a>
    </div>
    @if($print)<script>window.addEventListener('load', () => window.print());</script>@endif
</body>
</html>
