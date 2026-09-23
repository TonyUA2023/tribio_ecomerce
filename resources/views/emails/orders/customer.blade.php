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

@if($order->items->contains(fn ($item) => !empty($item->customization)))
**Personalización**

@foreach($order->items->filter(fn ($item) => !empty($item->customization)) as $item)
*{{ $item->product_name }}*<br>
@foreach($item->customization as $row)
{{ $row['label'] }}: **{{ $row['value'] }}**<br>
@endforeach

@endforeach
@endif
**Subtotal:** {{ $order->currency }} {{ number_format((float) $order->subtotal, 2) }}<br>
**Envío:** {{ $order->currency }} {{ number_format((float) $order->shipping_cost, 2) }}<br>
**Total:** {{ $order->currency }} {{ number_format((float) $order->total, 2) }}
@if((float) $order->deposit_amount > 0)
<br>**Adelanto:** {{ $order->currency }} {{ number_format((float) $order->deposit_amount, 2) }} · **Saldo antes de la entrega:** {{ $order->currency }} {{ number_format((float) $order->total - (float) $order->deposit_amount, 2) }}
@endif
@if($order->estimated_ready_at)
<br>**Listo aproximadamente el:** {{ $order->estimated_ready_at->format('d/m/Y') }}
@endif

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
