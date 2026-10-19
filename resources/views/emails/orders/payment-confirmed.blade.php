<x-mail::message>
# {{ $confirmed ? '¡Pago confirmado!' : 'No pudimos confirmar tu pago' }}

@if($confirmed)
Hola {{ $order->customer_name }}, **{{ $store->name }}** verificó tu pago de **{{ $order->money($order->amount_paid) }}**. Tu pedido **#{{ $order->order_number }}** ya está confirmado y pasa a preparación.
@else
Hola {{ $order->customer_name }}, **{{ $store->name }}** no encontró el pago de tu pedido **#{{ $order->order_number }}**.
@endif

@if($note)
> {{ $note }}

@endif
Si tienes alguna pregunta, responde directamente a este correo{{ $store->whatsapp_phone ? ' o escríbenos por WhatsApp' : '' }}.

{{ $store->name }}
</x-mail::message>
