<x-mail::message>
# ¡Gracias por tu pedido, {{ $order->customer_name }}!

Hemos recibido tu pedido **#{{ $order->order_number }}** en **{{ $store->name }}**. Aquí tienes el resumen:

<x-mail::table>
| Producto | Cant. | Precio |
| :------- | :---: | -----: |
@foreach($order->items as $item)
| {{ $item->product_name }}{{ $item->variant_title ? ' ('.$item->variant_title.')' : '' }} | {{ $item->quantity }} | {{ $order->currency }} {{ number_format((float) $item->price, 2) }} |
@endforeach
</x-mail::table>

**Subtotal:** {{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}<br>
**Envío:** {{ $order->currency }} {{ number_format((float) $order->shipping_cost, 2) }}<br>
**Total:** {{ $order->currency }} {{ number_format((float) $order->total, 2) }}

@if($order->customer_address)
**Dirección de envío:** {{ $order->customer_address }}{{ $order->customer_city ? ', ' . $order->customer_city : '' }}

@endif
<x-mail::button :url="$confirmationUrl">
Ver mi pedido
</x-mail::button>

Si tienes alguna pregunta sobre tu pedido, puedes responder directamente a este correo{{ $store->whatsapp_phone ? ' o escribirnos por WhatsApp' : '' }}.

Gracias por tu compra,<br>
{{ $store->name }}
</x-mail::message>
