@php
    $store = $store ?? auth()->user()->currentStore();
    $shareUrl = $store->hasStorefront() && $product->is_active && $store->status === 'active' ? rtrim($store->url, '/') . '/producto/' . $product->slug : null;
@endphp
<div class="pr-menu" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" class="pr-icon" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" aria-label="Acciones de {{ $product->name }}">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
    </button>
    <div class="pr-menu-list" x-show="open" x-cloak role="menu">
        <a href="{{ $edit }}" role="menuitem">Editar</a>
        <a href="{{ route('dashboard.inventario.product', $product->id) }}" role="menuitem">Stock y movimientos</a>
        @if($shareUrl)<a href="https://wa.me/?text={{ rawurlencode($product->name . ' — S/ ' . number_format($product->price, 2) . "\n" . $shareUrl) }}" target="_blank" rel="noopener" role="menuitem">Compartir por WhatsApp</a>@endif
        @if($store->hasStorefront() && $product->video_path)
            <form action="{{ route('dashboard.productos.toggle-home-video', $product->id) }}" method="POST">@csrf<button type="submit" role="menuitem">{{ $product->show_video_on_home ? 'Quitar video de la portada' : 'Video en la portada' }}</button></form>
        @endif
        <form method="POST" action="{{ route('dashboard.productos.destroy', $product) }}" onsubmit="return confirm('¿Eliminar este producto?')">@csrf @method('DELETE')<button type="submit" class="is-danger" role="menuitem">Eliminar</button></form>
    </div>
</div>
