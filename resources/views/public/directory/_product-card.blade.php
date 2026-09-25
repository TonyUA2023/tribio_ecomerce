{{-- One product of the directory (server-rendered twin of the Alpine card in directory.blade.php). --}}
<article class="dx-card" style="--accent: {{ $p['store']['accent'] }}">
    <a class="dx-card-media" href="{{ $p['url'] }}" data-initial="{{ mb_substr($p['name'], 0, 1) }}">
        @if($p['image'])
            <img src="{{ $p['image'] }}" alt="{{ $p['name'] }}" loading="lazy" onerror="dxBroken(this)">
        @else
            <span class="dx-fallback" aria-hidden="true">{{ mb_substr($p['name'], 0, 1) }}</span>
        @endif
        <span class="dx-badges">
            @if($p['discount_percent'])<span class="dx-badge dx-badge-deal">-{{ $p['discount_percent'] }}%</span>@endif
            @if($p['made_to_order'])<span class="dx-badge">✂️ Hecho a pedido</span>@endif
            @unless($p['available'])<span class="dx-badge dx-badge-out">Agotado</span>@endunless
        </span>
    </a>
    <div class="dx-card-body">
        <a class="dx-card-name" href="{{ $p['url'] }}">{{ $p['name'] }}</a>
        <div class="dx-price">
            <strong>{{ $p['price_label'] }}</strong>
            @if($p['compare_label'])<s>{{ $p['compare_label'] }}</s>@endif
        </div>
        @if($p['rating'])
            <span class="dx-rating">★ {{ number_format($p['rating'], 1) }} <small>({{ $p['reviews_count'] }})</small></span>
        @endif
        <button type="button" class="dx-card-store" @click="openPreview({{ $p['store']['id'] }})" aria-label="Vista previa de la tienda {{ $p['store']['name'] }}">
            <span class="dx-avatar" data-initial="{{ mb_substr($p['store']['name'], 0, 1) }}">
                @if($p['store']['logo'])<img src="{{ $p['store']['logo'] }}" alt="" loading="lazy" onerror="dxBroken(this)">@else{{ mb_substr($p['store']['name'], 0, 1) }}@endif
            </span>
            <span class="dx-card-store-name">{{ $p['store']['name'] }}</span>
            <em>Ver tienda</em>
        </button>
    </div>
</article>
