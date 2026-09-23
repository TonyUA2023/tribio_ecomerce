{{--
    Estrellas compactas para una tarjeta de producto ("★ 4.8 (12)"). No imprime NADA si el producto
    aún no tiene reseñas, así que una tienda sin reseñas se ve exactamente igual que antes.
    Lee los contadores de la propia fila (products.average_rating / reviews_count): cero consultas.

        @include('components.storefront.rating-badge', ['product' => $product])
        @include('components.storefront.rating-badge', ['product' => $product, 'tone' => 'rgba(255,255,255,.6)'])

    Solo estilos en línea: sin clases nuevas, sin recompilar Tailwind, en cualquier plantilla.
--}}
@if((int) ($product->reviews_count ?? 0) > 0)
<span class="tr-rating-badge" style="display:flex;align-items:center;gap:.3rem;margin:.2rem 0 .3rem;font-size:.72rem;line-height:1;font-weight:400;text-transform:none;letter-spacing:0;color:{{ $tone ?? '#78716c' }}" title="{{ number_format((float) $product->average_rating, 1) }}/5 · {{ (int) $product->reviews_count }}">
<span aria-hidden="true" style="color:#f59e0b;font-size:.85rem">★</span><strong style="font-weight:700">{{ number_format((float) $product->average_rating, 1) }}</strong><span>({{ (int) $product->reviews_count }})</span>
</span>
@endif
