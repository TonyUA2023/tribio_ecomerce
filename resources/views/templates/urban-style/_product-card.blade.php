{{-- Tarjeta de producto de moda: foto vertical, % de descuento en círculo y agregado rápido.
     Un producto con tallas/colores (variantes) lleva a su página para elegir antes de comprar. --}}
@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $price = $product->resolvePrice();
    $compare = $product->resolveComparePrice();
    $onSale = $compare > $price && $compare > 0;
    $symbol = $product->resolveCurrencySymbol();
    $productUrl = route('store.product', [$store->slug, $product->slug]);
    $kicker = $product->relationLoaded('category') && $product->category ? $product->category->getTranslatedName() : $store->name;
@endphp
<article class="us-card">
    <a href="{{ $productUrl }}" class="us-card-media">
        @if($product->image_path)
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">
        @else
            <span style="position: absolute; inset: 0; display: grid; place-items: center; font-size: 40px; color: #bbb" aria-hidden="true">👕</span>
        @endif
        @if($onSale)
            <span class="us-card-off">{{ round((($compare - $price) / $compare) * 100) }}%</span>
        @endif
    </a>
    @if($product->has_variants)
        <a href="{{ $productUrl }}" class="us-card-add" title="{{ $isEn ? 'Choose size' : 'Elegir talla' }}" aria-label="{{ $isEn ? 'Choose size for' : 'Elegir talla de' }} {{ $product->name }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M5 8h14l-1.2 12.1a1 1 0 0 1-1 .9H7.2a1 1 0 0 1-1-.9L5 8Z"/><path stroke-linecap="round" d="M9 10V6a3 3 0 0 1 6 0v4"/></svg>
        </a>
    @else
        <button type="button" class="us-card-add" title="{{ $isEn ? 'Add to cart' : 'Agregar al carrito' }}" aria-label="{{ $isEn ? 'Add to cart' : 'Agregar al carrito' }}: {{ $product->name }}"
                onclick="window.TribioCart && window.TribioCart.add({{ $product->id }}, @js($product->name), {{ $price }}, @js($product->image_path ? $product->image_url : ''), null, this)">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M5 8h14l-1.2 12.1a1 1 0 0 1-1 .9H7.2a1 1 0 0 1-1-.9L5 8Z"/><path stroke-linecap="round" d="M9 10V6a3 3 0 0 1 6 0v4M12 13v4M10 15h4"/></svg>
        </button>
    @endif
    <div class="us-card-body">
        <span class="us-card-kicker">{{ $kicker }}</span>
        <a href="{{ $productUrl }}" class="us-card-name" title="{{ $product->name }}">{{ $product->name }}</a>
        <div class="us-card-price">
            @if($onSale)<s>{{ $symbol }} {{ number_format($compare, 2) }}</s>@endif
            <strong class="{{ $onSale ? 'is-sale' : '' }}">{{ $symbol }} {{ number_format($price, 2) }}</strong>
        </div>
        <div class="us-card-rating">@include('components.storefront.rating-badge', ['product' => $product])</div>
    </div>
</article>
