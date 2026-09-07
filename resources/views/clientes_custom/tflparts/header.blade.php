<header class="bg-black text-white border-b border-[#E50914] sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <div class="flex-shrink-0">
                <a href="{{ route('store.show', $store->slug) }}" class="flex items-center gap-2">
                    @if($store->logo_path)
                        <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-8 w-auto">
                    @else
                        <span class="text-xl font-black tracking-widest uppercase italic">{{ $store->name }}</span>
                    @endif
                </a>
            </div>

            <!-- Desktop Menu -->
            <nav class="hidden md:flex space-x-8">
                <a href="{{ route('store.show', $store->slug) }}" class="text-gray-300 hover:text-[#E50914] text-xs font-bold uppercase tracking-wider transition-colors">Inicio</a>
                <a href="{{ route('store.catalog', $store->slug) }}" class="text-gray-300 hover:text-[#E50914] text-xs font-bold uppercase tracking-wider transition-colors">Catálogo</a>
                <a href="{{ route('store.gallery', $store->slug) }}" class="text-gray-300 hover:text-[#E50914] text-xs font-bold uppercase tracking-wider transition-colors">Galería</a>
                <a href="{{ route('store.show', $store->slug) }}#nosotros" class="text-gray-300 hover:text-[#E50914] text-xs font-bold uppercase tracking-wider transition-colors">Nosotros</a>
            </nav>

            <!-- Cart Icon -->
            <div class="flex items-center">
                <button @click="isCartOpen = true" class="relative p-2 text-gray-300 hover:text-white transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <span x-show="cartTotalItems > 0" x-text="cartTotalItems" x-cloak class="absolute top-0 right-0 -mt-1 -mr-1 flex h-4 w-4 items-center justify-center rounded-full bg-[#E50914] text-[9px] font-bold text-white"></span>
                </button>
            </div>
        </div>
    </div>
</header>
