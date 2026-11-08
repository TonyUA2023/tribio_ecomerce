@if(!$product->track_stock)
    <span class="pr-stock is-none">Sin control</span>
@elseif($product->stock <= 0)
    <span class="pr-stock is-out">Sin stock</span>
@else
    <span class="pr-stock {{ $product->isLowStock() ? 'is-low' : '' }}">{{ $product->stock }}</span>
@endif
