<x-mail::message>
# 🛍️ Nuevo pedido recibido

Tienes un nuevo pedido **#{{ $order->order_number }}** por **{{ $order->currency }} {{ number_format((float) $order->total, 2) }}**.

**Cliente:** {{ $order->customer_name }}<br>
**Teléfono:** {{ $order->customer_phone }}<br>
**Correo:** {{ $order->customer_email }}
@if($order->customer_address)
<br>
**Dirección:** {{ $order->customer_address }}{{ $order->customer_city ? ', ' . $order->customer_city : '' }}
@endif
<x-mail::table>
| Producto | Cant. | Subtotal |
| :------- | :---: | -------: |
@foreach($order->items as $item)
| {{ $item->product_name }}{{ $item->variant_title ? ' ('.$item->variant_title.')' : '' }} | {{ $item->quantity }} | {{ $order->currency }} {{ number_format((float) $item->subtotal, 2) }} |
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
**Total del pedido:** {{ $order->currency }} {{ number_format((float) $order->total, 2) }}
@if((float) $order->deposit_amount > 0)
<br>**Adelanto pedido al cliente:** {{ $order->currency }} {{ number_format((float) $order->deposit_amount, 2) }} · **Saldo por cobrar:** {{ $order->currency }} {{ number_format((float) $order->total - (float) $order->deposit_amount, 2) }}
@endif
@if($order->required_by)
<br>**El cliente lo necesita para el:** {{ $order->required_by->format('d/m/Y') }}
@endif

<x-mail::button :url="$dashboardUrl">
Ver pedido en el panel
</x-mail::button>

Este pedido quedó registrado con estado **pendiente** — revísalo en tu panel para confirmarlo y coordinar la entrega.

{{ config('app.name') }}
</x-mail::message>
