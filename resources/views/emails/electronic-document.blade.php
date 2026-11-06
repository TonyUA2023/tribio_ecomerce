<!doctype html>
<html lang="es">
<body style="margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#24364b">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
    <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:14px;overflow:hidden">
        <tr><td style="background:#1675dc;color:#fff;padding:18px 24px"><strong style="font-size:16px">{{ $store->name }}</strong><br><span style="font-size:12px;opacity:.85">RUC {{ $store->ruc }}</span></td></tr>
        <tr><td style="padding:24px">
            <p style="margin:0 0 4px;font-size:13px;color:#64758a">{{ $doc->type === 'factura' ? 'Factura electrónica' : 'Boleta de venta electrónica' }}</p>
            <p style="margin:0 0 16px;font-size:22px;font-weight:700">{{ $doc->code() }}</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">
                @if($order)
                <tr><td style="padding:6px 0;color:#64758a">Cliente</td><td align="right" style="padding:6px 0">{{ $order->customer_name }}</td></tr>
                <tr><td style="padding:6px 0;color:#64758a">Fecha</td><td align="right" style="padding:6px 0">{{ $order->created_at->copy()->setTimezone('America/Lima')->format('d/m/Y H:i') }}</td></tr>
                <tr><td style="padding:10px 0 6px;border-top:1px solid #e7edf4;font-weight:700">Total</td><td align="right" style="padding:10px 0 6px;border-top:1px solid #e7edf4;font-weight:700;font-size:18px">S/ {{ number_format((float) $order->total, 2) }}</td></tr>
                @endif
            </table>
            <p style="margin:18px 0 0;padding:12px 14px;border-radius:10px;background:{{ $doc->status === 'observed' ? '#fff4e0' : '#e6f6ef' }};font-size:13px">
                <strong>{{ $doc->status === 'observed' ? 'Aceptado por SUNAT con observaciones' : 'Aceptado por SUNAT' }}</strong>
                @if($doc->sunat_message)<br><span style="color:#64758a">{{ $doc->sunat_message }}</span>@endif
                @if($doc->environment === 'beta')<br><span style="color:#b45309">Ambiente de pruebas: este comprobante no tiene valor tributario.</span>@endif
            </p>
            <p style="margin:16px 0 0;font-size:12px;color:#64758a">Adjuntamos el PDF, el XML firmado y la constancia de recepción (CDR) de SUNAT. Conserva el XML: es el comprobante original.</p>
        </td></tr>
    </table>
</td></tr></table>
</body>
</html>
