<section style="background-color: {{ $data['background_color'] ?? '#f9fafb' }}; color: {{ $data['text_color'] ?? '#111827' }};" class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-extrabold tracking-tight mb-8 text-center">
            {{ $data['title'] ?? 'Explora por Categorías' }}
        </h2>
        
        @if(isset($categories) && $categories->count() > 0)
            @php $layout = $data['layout'] ?? 'grid'; @endphp
            
            @if($layout === 'carousel')
                <div x-data="{
                    activeSlide: 0,
                    slides: {{ $categories->count() }},
                    itemsPerView: window.innerWidth < 640 ? 2 : (window.innerWidth < 1024 ? 3 : 6),
                    get maxSlide() { return Math.max(0, this.slides - this.itemsPerView); }
                }" class="relative px-8">
                    
                    <button @click="activeSlide = Math.max(0, activeSlide - 1)" 
                            class="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 flex items-center justify-center bg-white shadow rounded-full text-gray-800 hover:bg-gray-50"
                            x-show="activeSlide > 0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    </button>

                    <div class="overflow-hidden">
                        <div class="flex transition-transform duration-300 ease-out"
                             :style="`transform: translateX(-${activeSlide * (100 / itemsPerView)}%);`">
                            @foreach($categories as $category)
                                <div class="shrink-0 px-2" :style="`width: ${100 / itemsPerView}%`">
                                    <a href="{{ route('store.catalog', $store->slug) }}?category={{ $category->slug }}" class="flex flex-col items-center p-6 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow h-full">
                                        @if($category->icon)
                                            <div class="text-4xl mb-3">{{ $category->icon }}</div>
                                        @else
                                            <div class="w-12 h-12 bg-gray-100 rounded-full mb-3 flex items-center justify-center text-gray-400">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                            </div>
                                        @endif
                                        <span class="text-sm font-bold text-gray-900 text-center line-clamp-2">{{ $category->name }}</span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <button @click="activeSlide = Math.min(maxSlide, activeSlide + 1)" 
                            class="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-8 h-8 flex items-center justify-center bg-white shadow rounded-full text-gray-800 hover:bg-gray-50"
                            x-show="activeSlide < maxSlide">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 lg:gap-6">
                    @foreach($categories as $category)
                        <a href="{{ route('store.catalog', $store->slug) }}?category={{ $category->slug }}" class="flex flex-col items-center p-6 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            @if($category->icon)
                                <div class="text-4xl mb-3">{{ $category->icon }}</div>
                            @else
                                <div class="w-12 h-12 bg-gray-100 rounded-full mb-3 flex items-center justify-center text-gray-400">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                </div>
                            @endif
                            <span class="text-sm font-bold text-gray-900 text-center line-clamp-2">{{ $category->name }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        @else
            <p class="text-center opacity-75">No hay categorías configuradas.</p>
        @endif
        
        @include('components.store-sections.blocks')
    </div>
</section>
