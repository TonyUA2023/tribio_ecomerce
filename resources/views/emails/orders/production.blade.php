<x-mail::message>
# {{ $order->production_stage === 'ready' ? '¡Tu pedido está listo!' : 'Novedades de tu pedido' }}

Hola {{ $order->customer_name }}, tu pedido **#{{ $order->order_number }}** en **{{ $store->name }}** ahora está en: **{{ $order->production_stage_label }}**.

@if($note)
> {{ $note }}

@endif
@if($order->estimated_ready_at && $order->production_stage !== 'ready')
Fecha estimada: **{{ $order->estimated_ready_at->format('d/m/Y') }}**.

@endif
@if($balanceUrl)
Tienes un saldo pendiente de **{{ $order->money($order->balance_due) }}**.

<x-mail::button :url="$balanceUrl">
Ver pedido y pagar saldo
</x-mail::button>
@endif

Si tienes alguna pregunta, responde directamente a este correo{{ $store->whatsapp_phone ? ' o escríbenos por WhatsApp' : '' }}.

{{ $store->name }}
</x-mail::message>
