{{-- One store of the directory: its own products as the cover, live preview on click. --}}
@php
    $thumbs = $s['thumbnails'];
    $mediaClass = count($thumbs) === 0 ? 'is-empty' : (count($thumbs) < 3 ? 'is-single' : '');
@endphp
<article class="dx-store" style="--accent: {{ $s['accent'] }}">
    <button type="button" class="dx-store-media {{ $mediaClass }}" @click="openPreview({{ $s['id'] }})" aria-label="Vista previa en vivo de {{ $s['name'] }}">
        @forelse(count($thumbs) < 3 ? array_slice($thumbs, 0, 1) : $thumbs as $thumb)
            <img src="{{ $thumb }}" alt="" loading="lazy" onerror="dxBroken(this)">
        @empty
            {{-- No product photos yet: the store's own mark on its brand color. --}}
            <span class="dx-store-mark" data-initial="{{ $s['initial'] }}" aria-hidden="true">
                @if($s['logo'])<img src="{{ $s['logo'] }}" alt="" loading="lazy" onerror="dxBroken(this)">@else{{ $s['initial'] }}@endif
            </span>
        @endforelse
        <span class="dx-store-live"><span class="dx-live-dot" aria-hidden="true"></span> Vista previa en vivo</span>
        @if($s['is_featured'])<span class="dx-store-featured">★ Destacado</span>@endif
    </button>
    <div class="dx-store-body">
        <div class="dx-store-id">
            <span class="dx-avatar" data-initial="{{ $s['initial'] }}">
                @if($s['logo'])<img src="{{ $s['logo'] }}" alt="" loading="lazy" onerror="dxBroken(this)">@else{{ $s['initial'] }}@endif
            </span>
            <div style="min-width: 0;">
                <h3>{{ $s['name'] }}</h3>
                <p>
                    {{ $s['category_icon'] }} {{ $s['category_label'] ?? 'Tienda' }}@if($s['city']) · {{ $s['city'] }}@endif
                    @if($s['rating']) · <span class="dx-rating">★ {{ number_format($s['rating']['value'], 1) }}</span>@endif
                </p>
            </div>
        </div>
        @if($s['summary'])
            <p class="dx-store-summary">{{ $s['summary'] }}</p>
        @endif
        <div class="dx-store-foot">
            <span>{{ $s['products_count'] ? $s['products_count'] . ' ' . ($s['products_count'] === 1 ? 'producto' : 'productos') : 'Catálogo en preparación' }}</span>
            <button type="button" class="dx-btn dx-btn-line" @click="openPreview({{ $s['id'] }})">Vista previa</button>
            <a class="dx-btn dx-btn-dark" href="{{ $s['url'] }}">Visitar ↗</a>
        </div>
    </div>
</article>
