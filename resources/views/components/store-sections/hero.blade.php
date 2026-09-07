@php
    $alignment = $data['alignment'] ?? 'center';
    $alignClass = $alignment === 'left' ? 'text-left' : ($alignment === 'right' ? 'text-right' : 'text-center');
    $flexClass = $alignment === 'left' ? 'justify-start' : ($alignment === 'right' ? 'justify-end' : 'justify-center');
    $marginClass = $alignment === 'left' ? 'ml-0 mr-auto' : ($alignment === 'right' ? 'mr-0 ml-auto' : 'mx-auto');
    
    $size = $data['size'] ?? 'normal';
    $pyClass = $size === 'large' ? 'py-32' : ($size === 'full' ? 'py-48 min-h-screen flex items-center' : 'py-24');
    
    $inlinePadding = "";
    if(isset($data['padding_y'])) {
        $inlinePadding = "padding-top: {$data['padding_y']}px; padding-bottom: {$data['padding_y']}px;";
        $pyClass = ""; // override default py
    }

    $images = $data['carousel_images'] ?? [];
    if (empty($images) && !empty($data['background_image'])) {
        $images[] = $data['background_image'];
    }
@endphp
<section style="background-color: {{ $data['background_color'] ?? '#f3f4f6' }}; color: {{ $data['text_color'] ?? '#111827' }}; {{ $inlinePadding }}" 
         class="relative overflow-hidden {{ $pyClass }}"
         @if(count($images) > 1) 
         x-data="{ activeSlide: 0, autoSlide() { setInterval(() => { this.activeSlide = (this.activeSlide + 1) % {{ count($images) }}; }, 5000); } }" 
         x-init="autoSlide()"
         @endif>
    
    <!-- Carousel Backgrounds -->
    @if(count($images) > 0)
        <div class="absolute inset-0 z-0 pointer-events-none">
            @foreach($images as $index => $img)
                <div class="absolute inset-0 bg-cover bg-center transition-opacity duration-1000 ease-in-out pointer-events-none"
                     style="background-image: url('{{ $img }}');"
                     @if(count($images) > 1)
                     x-show="activeSlide === {{ $index }}"
                     x-transition:enter="transition-opacity ease-linear duration-1000"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-linear duration-1000"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @endif>
                </div>
            @endforeach
            <!-- Overlay to make text readable -->
            <div class="absolute inset-0 bg-black/40 pointer-events-none"></div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 {{ $alignClass }}">
        <!-- Titles and stuff are now managed by blocks -->
        
        @include('components.store-sections.blocks')
    </div>

    @if(count($images) > 1)
        <!-- Paginator -->
        <div class="absolute bottom-6 left-0 right-0 z-20 flex justify-center gap-2">
            @foreach($images as $index => $img)
                <button @click="activeSlide = {{ $index }}" 
                        class="w-3 h-3 rounded-full transition-all shadow-md"
                        :class="activeSlide === {{ $index }} ? 'bg-white scale-125' : 'bg-white/50 hover:bg-white/80'"></button>
            @endforeach
        </div>
        
        <!-- Navigation Arrows -->
        <button @click="activeSlide = activeSlide === 0 ? {{ count($images) - 1 }} : activeSlide - 1" class="absolute left-4 top-1/2 -translate-y-1/2 z-20 w-10 h-10 flex items-center justify-center rounded-full bg-black/20 text-white hover:bg-black/50 transition-colors backdrop-blur-sm shadow-lg">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <button @click="activeSlide = (activeSlide + 1) % {{ count($images) }}" class="absolute right-4 top-1/2 -translate-y-1/2 z-20 w-10 h-10 flex items-center justify-center rounded-full bg-black/20 text-white hover:bg-black/50 transition-colors backdrop-blur-sm shadow-lg">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
    @endif
</section>
