<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Estado del pago · {{ $store->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="pay-flow-result-page">
    @php
        $paid = $paymentStatus === 'paid';
        $failed = $paymentStatus === 'failed';
    @endphp
    <main class="pay-flow-result" aria-labelledby="payment-title">
        <p class="pay-flow-store">{{ $store->name }}</p>
        <div class="pay-flow-result-icon {{ $paid ? 'is-paid' : '' }}" aria-hidden="true">{{ $paid ? '✓' : ($failed ? '!' : '…') }}</div>
        <h1 id="payment-title">{{ $paid ? 'Tu pago está confirmado' : ($failed ? 'El pago no se completó' : 'Tu pago está pendiente') }}</h1>
        <p>{{ $paid ? 'Recibimos la confirmación de Flow. Tu tienda continuará con la preparación del pedido.' : ($failed ? 'Flow informó que el pago fue rechazado o anulado. Contacta a la tienda con tu número de pedido para coordinar otra forma de pago.' : 'Aún no tenemos la confirmación del pago. Si ya pagaste, espera unos momentos y consulta nuevamente antes de realizar otro pago.') }}</p>
        @if($unavailable)
            <p role="status">No pudimos consultar Flow en este momento. Puedes revisar el estado nuevamente; tu pedido sigue registrado.</p>
        @endif
        <dl class="pay-flow-receipt">
            <div><dt>Pedido</dt><dd>{{ $orderNumber }}</dd></div>
            <div><dt>Total</dt><dd>{{ $currency }} {{ number_format($total, 2) }}</dd></div>
            <div><dt>Medio de pago</dt><dd>Flow</dd></div>
        </dl>
        @if(!$paid && !$failed)
            <form method="post" action="{{ rtrim(config('app.url'), '/') }}/api/flow/{{ $store->id }}/return">
                <input type="hidden" name="token" value="{{ $flowToken }}">
                <button type="submit" class="pay-flow-primary">Consultar estado del pago</button>
            </form>
        @endif
        <a class="pay-flow-back" href="{{ $store->url }}">Volver a la tienda</a>
        <p class="pay-flow-footnote">Pago procesado por Flow · Pedido gestionado por {{ $store->name }}</p>
    </main>
</body>
</html>
