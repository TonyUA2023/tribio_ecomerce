<section style="background-color: {{ $data['background_color'] ?? '#ffffff' }}; color: {{ $data['text_color'] ?? '#111827' }};" class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-extrabold tracking-tight mb-8 text-center">
            {{ $data['title'] ?? 'Productos Destacados' }}
        </h2>
        
        @if(isset($featuredProducts) && $featuredProducts->count() > 0)
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 lg:gap-8">
                @foreach($featuredProducts->take($data['limit'] ?? 8) as $product)
                    <a href="{{ route('store.product', ['slug' => $store->slug, 'product' => $product->slug]) }}" class="group block">
                        <div class="w-full aspect-w-1 aspect-h-1 bg-gray-200 rounded-lg overflow-hidden xl:aspect-w-7 xl:aspect-h-8">
                            @if($product->image_url)
                                <img src="{{ asset('storage/' . $product->image_url) }}" alt="{{ $product->name }}" class="w-full h-full object-center object-cover group-hover:opacity-75 transition-opacity">
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gray-100 text-gray-400">Sin Imagen</div>
                            @endif
                        </div>
                        <h3 class="mt-4 text-sm font-medium" style="color: {{ $data['text_color'] ?? '#111827' }};">{{ $product->name }}</h3>
                        <p class="mt-1 text-lg font-bold" style="color: {{ $data['text_color'] ?? '#111827' }};">
                            {{ config('tribio.currency') }}{{ number_format($product->price, 2) }}
                        </p>
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-center opacity-75">No hay productos destacados aún.</p>
        @endif
        
        @include('components.store-sections.blocks')
    </div>
</section>
