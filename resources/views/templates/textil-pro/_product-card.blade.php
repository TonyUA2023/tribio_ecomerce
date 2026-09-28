{{-- Tarjeta de producto estilo tienda deportiva: foto sobre gris (segunda foto al pasar el mouse),
     % de descuento, "3 colores", nombre y precio. Con tallas/colores lleva a la ficha para elegir. --}}
@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $price = $product->resolvePrice();
    $compare = $product->resolveComparePrice();
    $onSale = $compare > $price && $compare > 0;
    $symbol = $product->resolveCurrencySymbol();
    $productUrl = route('store.product', [$store->slug, $product->slug]);
    $images = $product->all_images;
    $altImage = $images[1] ?? null;
    $colors = \App\Services\Storefront\CatalogFacets::colorCount($product);
@endphp
<article class="tx-card">
    <a href="{{ $productUrl }}" class="tx-card-media {{ $altImage ? 'has-alt' : '' }}" aria-label="{{ $product->name }}">
        <img src="{{ $images[0] }}" alt="{{ $product->name }}" loading="lazy">
        @if($altImage)
            <img src="{{ $altImage }}" alt="" class="is-alt" loading="lazy">
        @endif
        @if($onSale)
            <span class="tx-badge">-{{ round((($compare - $price) / $compare) * 100) }}%</span>
        @elseif($product->created_at && $product->created_at->gt(now()->subDays(21)))
            <span class="tx-badge is-dark">{{ $isEn ? 'New' : 'Nuevo' }}</span>
        @endif
    </a>
    @if($product->has_variants)
        <a href="{{ $productUrl }}" class="tx-card-add" title="{{ $isEn ? 'Choose size' : 'Elegir talla' }}" aria-label="{{ $isEn ? 'Choose size for' : 'Elegir talla de' }} {{ $product->name }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2"/><circle cx="9.5" cy="20" r="1.3"/><circle cx="17" cy="20" r="1.3"/></svg>
        </a>
    @else
        <button type="button" class="tx-card-add" title="{{ $isEn ? 'Add to cart' : 'Agregar al carrito' }}" aria-label="{{ $isEn ? 'Add to cart' : 'Agregar al carrito' }}: {{ $product->name }}"
                onclick="window.TribioCart && window.TribioCart.add({{ $product->id }}, @js($product->name), {{ $price }}, @js($images[0]), null, this)">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h9.2a1 1 0 0 0 1-.8L20 8H6.2"/><path stroke-linecap="round" d="M13 10v4M11 12h4"/></svg>
        </button>
    @endif
    <div class="tx-card-info">
        @if($colors > 1)
            <span class="tx-card-meta">{{ $colors }} {{ $isEn ? 'colors' : 'colores' }}</span>
        @elseif($product->relationLoaded('category') && $product->category)
            <span class="tx-card-meta">{{ $product->category->getTranslatedName() }}</span>
        @endif
        <a href="{{ $productUrl }}" class="tx-card-name">{{ $product->name }}</a>
        <div class="tx-price">
            <span class="{{ $onSale ? 'is-sale' : '' }}">{{ $symbol }} {{ number_format($price, 2) }}</span>
            @if($onSale)<s>{{ $symbol }} {{ number_format($compare, 2) }}</s>@endif
        </div>
        @include('components.storefront.rating-badge', ['product' => $product])
    </div>
</article>
