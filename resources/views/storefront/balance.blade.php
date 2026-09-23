<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Pedido #{{ $order->order_number }} · {{ $store->name }}</title>
    @php
        $stageKeys = array_keys(\App\Models\Order::PRODUCTION_STAGES);
        $stageIndex = array_search($order->production_stage, $stageKeys, true);
        $cancelled = in_array($order->status, ['cancelled', 'refunded'], true);
        $owes = !$cancelled && (float) $order->balance_due > 0;
        $firstName = \Illuminate\Support\Str::of((string) $order->customer_name)->trim()->explode(' ')->first();
        $notices = [
            'paid' => ['ok', '¡Pago recibido! Gracias, ya registramos tu pago.'],
            'pending' => ['wait', 'Estamos esperando la confirmación de Mercado Pago. Si ya pagaste, no vuelvas a pagar: esta página se actualizará cuando se confirme.'],
            'failed' => ['error', 'El pago no se completó. Puedes intentarlo de nuevo o coordinar con la tienda.'],
            'unavailable' => ['error', 'No pudimos abrir Mercado Pago en este momento. Intenta de nuevo en unos minutos.'],
        ];
    @endphp
    <style>
        :root {
            --accent: {{ $palette['primary'] ?? '#1E293B' }};
            --accent-soft: {{ $palette['primary-50'] ?? '#EEF2F7' }};
            --on-accent: {{ $palette['on-primary'] ?? '#FFFFFF' }};
            --ink: #1E1D1B; --muted: #6B6A66; --line: #E8E6E1; --bg: #F7F6F3; --card: #FFFFFF;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 560px; margin: 0 auto; padding: 24px 16px 48px; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .brand img { width: 44px; height: 44px; border-radius: 12px; object-fit: contain; background: #fff; border: 1px solid var(--line); }
        .brand span { font-weight: 700; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 20px; margin-bottom: 14px; }
        h1 { font-size: 22px; line-height: 1.25; margin: 0 0 4px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 0 0 12px; }
        .muted { color: var(--muted); font-size: 14px; margin: 0; }
        .notice { border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; font-size: 14px; font-weight: 600; }
        .notice.ok { background: #E7F6EC; color: #1D6B3A; }
        .notice.wait { background: #FFF6E0; color: #8A5A00; }
        .notice.error { background: #FDECEC; color: #9B2C2C; }
        .steps { list-style: none; margin: 0; padding: 0; }
        .steps li { display: flex; align-items: center; gap: 10px; padding: 6px 0; color: var(--muted); font-size: 14px; }
        .steps li.done, .steps li.now { color: var(--ink); }
        .steps li.now { font-weight: 700; }
        .dot { flex: none; width: 24px; height: 24px; border-radius: 999px; display: grid; place-items: center; font-size: 12px; font-weight: 700; background: var(--line); color: var(--muted); }
        .done .dot { background: var(--accent-soft); color: var(--accent); }
        .now .dot { background: var(--accent); color: var(--on-accent); }
        .item { padding: 10px 0; border-top: 1px solid var(--line); }
        .item:first-of-type { border-top: 0; padding-top: 0; }
        .item-head { display: flex; justify-content: space-between; gap: 12px; font-weight: 600; }
        .item dl { margin: 6px 0 0; font-size: 13px; color: var(--muted); }
        .item dl div { display: flex; flex-wrap: wrap; gap: 4px; }
        .item dd { margin: 0; color: var(--ink); overflow-wrap: anywhere; }
        .totals { margin: 0; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
        .totals dd { margin: 0; font-weight: 600; }
        .totals .due { border-top: 1px solid var(--line); margin-top: 6px; padding-top: 10px; font-size: 17px; font-weight: 800; }
        .btn { display: block; width: 100%; border: 0; border-radius: 14px; padding: 15px 16px; font: inherit; font-weight: 700; text-align: center; text-decoration: none; cursor: pointer; }
        .btn-primary { background: var(--accent); color: var(--on-accent); }
        .btn-ghost { background: transparent; color: var(--ink); border: 1px solid var(--line); margin-top: 10px; }
        .btn:focus-visible { outline: 3px solid var(--accent-soft); outline-offset: 2px; }
        .fine { font-size: 12px; color: var(--muted); text-align: center; margin: 10px 0 0; }
        .back { display: block; text-align: center; margin-top: 18px; color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>
<main>
    <a class="brand" href="{{ $store->url }}" style="color: inherit; text-decoration: none;">
        <img src="{{ $store->logo_url }}" alt="">
        <span>{{ $store->name }}</span>
    </a>

    @if($state && isset($notices[$state]))
        <p class="notice {{ $notices[$state][0] }}" role="status">{{ $notices[$state][1] }}</p>
    @endif

    <section class="card" aria-labelledby="order-title">
        <h1 id="order-title">Pedido #{{ $order->order_number }}</h1>
        <p class="muted">
            Hola {{ $firstName }},
            @if($cancelled)
                este pedido fue cancelado.
            @elseif($order->production_stage === 'ready')
                ¡tu pedido está listo para entregar!
            @else
                así va tu pedido hecho a pedido.
            @endif
        </p>
    </section>

    @unless($cancelled)
    <section class="card" aria-labelledby="stage-title">
        <h2 id="stage-title">Producción</h2>
        <ol class="steps">
            @foreach(\App\Models\Order::PRODUCTION_STAGES as $stageKey => $stageLabel)
            @php
                $stepState = $loop->index < $stageIndex ? 'done' : ($loop->index === $stageIndex ? 'now' : '');
            @endphp
            <li class="{{ $stepState }}" @if($stepState === 'now') aria-current="step" @endif>
                <span class="dot" aria-hidden="true">{{ $stepState === 'done' ? '✓' : $loop->iteration }}</span>
                {{ $stageLabel }}
            </li>
            @endforeach
        </ol>
        @if($order->estimated_ready_at && $order->production_stage !== 'ready')
        <p class="muted" style="margin-top: 10px;">Fecha estimada: <strong>{{ $order->estimated_ready_at->format('d/m/Y') }}</strong></p>
        @endif
    </section>
    @endunless

    <section class="card" aria-labelledby="items-title">
        <h2 id="items-title">Tu pedido</h2>
        @foreach($order->items as $item)
        <div class="item">
            <div class="item-head">
                <span>{{ $item->quantity }} × {{ $item->product_name }}@if($item->variant_title) <span class="muted">· {{ $item->variant_title }}</span>@endif</span>
                <span>{{ $order->money($item->subtotal) }}</span>
            </div>
            @if(!empty($item->customization))
            <dl>
                @foreach($item->customization as $row)
                <div><dt>{{ $row['label'] }}:</dt><dd>{{ ($row['type'] ?? null) === 'file' ? 'Archivo recibido ✓' : $row['value'] }}</dd></div>
                @endforeach
            </dl>
            @endif
        </div>
        @endforeach
    </section>

    <section class="card" aria-labelledby="pay-title">
        <h2 id="pay-title">Pago</h2>
        <dl class="totals">
            <div><dt>Total del pedido</dt><dd>{{ $order->money($order->total) }}</dd></div>
            <div><dt>Pagado</dt><dd>{{ $order->money($order->amount_paid) }}</dd></div>
            @if($owes)
            <div class="due"><dt>Saldo pendiente</dt><dd>{{ $order->money($order->balance_due) }}</dd></div>
            @endif
        </dl>

        @if($owes)
            <div style="margin-top: 16px;">
                @if($canPayOnline)
                <form method="POST" action="{{ $startUrl }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Pagar saldo · {{ $order->money($order->balance_due) }}</button>
                </form>
                <p class="fine">Pago seguro con Mercado Pago: tarjeta, Yape y más.</p>
                @endif
                @if($whatsappUrl)
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn {{ $canPayOnline ? 'btn-ghost' : 'btn-primary' }}">
                    {{ $canPayOnline ? 'Prefiero coordinar por WhatsApp' : 'Coordinar el pago por WhatsApp' }}
                </a>
                @elseif(!$canPayOnline)
                <p class="fine">La tienda te indicará cómo pagar el saldo.</p>
                @endif
            </div>
        @elseif(!$cancelled)
            <p class="notice ok" style="margin: 14px 0 0;">✓ Pedido pagado por completo</p>
        @endif
    </section>

    <a class="back" href="{{ $store->url }}">Volver a {{ $store->name }}</a>
</main>
</body>
</html>
