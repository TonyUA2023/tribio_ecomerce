{{-- Carrusel horizontal de productos con flechas y barra de progreso. Espera $products. --}}
<div class="tx-rail" x-data="txRail">
    <button type="button" class="tx-rail-btn is-prev" @click="scroll(-1)" :disabled="atStart" aria-label="{{ \App\Helpers\TranslationHelper::isEn() ? 'Previous' : 'Anterior' }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 6-6 6 6 6"/></svg>
    </button>
    <div class="tx-rail-track" x-ref="track" @scroll.passive="update()">
        @foreach($products as $product)
            @include('templates.textil-pro._product-card', ['product' => $product])
        @endforeach
    </div>
    <button type="button" class="tx-rail-btn is-next" @click="scroll(1)" :disabled="atEnd" aria-label="{{ \App\Helpers\TranslationHelper::isEn() ? 'Next' : 'Siguiente' }}">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
    </button>
    <div class="tx-rail-progress" x-show="!(atStart && atEnd)" aria-hidden="true"><i :style="bar"></i></div>
</div>

@once
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('txRail', () => ({
            atStart: true, atEnd: false, bar: 'width: 30%',
            init() {
                this.$nextTick(() => this.update());
                window.addEventListener('resize', () => this.update(), { passive: true });
            },
            update() {
                const track = this.$refs.track;
                if (!track) return;
                const max = track.scrollWidth - track.clientWidth;
                this.atStart = track.scrollLeft <= 4;
                this.atEnd = track.scrollLeft >= max - 4;
                const size = Math.max(12, Math.min(100, track.clientWidth / track.scrollWidth * 100));
                const offset = max > 0 ? (track.scrollLeft / max) * (100 - size) : 0;
                this.bar = `width: ${size}%; left: ${offset}%`;
                // Las flechas se centran en la foto de la primera tarjeta.
                const media = track.querySelector('.tx-card-media');
                if (media) this.$el.style.setProperty('--tx-rail-img', media.offsetHeight + 'px');
            },
            scroll(direction) {
                const track = this.$refs.track;
                track.scrollBy({ left: direction * track.clientWidth * 0.9, behavior: 'smooth' });
            },
        }));
    });
</script>
@endpush
@endonce
