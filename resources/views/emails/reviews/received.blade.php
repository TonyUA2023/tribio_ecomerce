<x-mail::message>
# ⭐ Nueva reseña en tu tienda

**{{ $reviewerName }}** calificó **{{ $productName }}** con {{ $review->rating }}/5 {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
@if($commentHtml)

<x-mail::panel>
{!! $commentHtml !!}
</x-mail::panel>
@endif

<x-mail::button :url="$dashboardUrl">
Ver y responder en el panel
</x-mail::button>

Responder públicamente a tus clientes genera confianza en quienes visitan tu tienda. La reseña ya está visible en el producto; si algo no corresponde, puedes ocultarla desde el panel.

{{ config('app.name') }}
</x-mail::message>
