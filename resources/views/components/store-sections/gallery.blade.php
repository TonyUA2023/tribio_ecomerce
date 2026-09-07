<section style="background-color: {{ $data['background_color'] ?? '#ffffff' }}; color: {{ $data['text_color'] ?? '#111827' }};" class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-extrabold tracking-tight mb-8 text-center">
            {{ $data['title'] ?? 'Nuestra Galería' }}
        </h2>
        
        @if(isset($galleryItems) && $galleryItems->count() > 0)
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($galleryItems as $item)
                    <div class="group relative block w-full aspect-w-1 aspect-h-1 rounded-lg overflow-hidden cursor-pointer" onclick="openLightbox('{{ asset('storage/' . $item->image_path) }}')">
                        <img src="{{ asset('storage/' . $item->image_path) }}" alt="{{ $item->title ?? 'Galería' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    </div>
                @endforeach
            </div>

            <!-- Lightbox Script/Modal should be handled globally or we can add a simple one here -->
            <div id="gallery-lightbox" class="fixed inset-0 z-[100] bg-black bg-opacity-90 hidden items-center justify-center p-4" onclick="this.classList.replace('flex', 'hidden')">
                <img id="lightbox-img" src="" class="max-w-full max-h-[90vh] object-contain rounded">
                <button class="absolute top-4 right-4 text-white text-4xl">&times;</button>
            </div>
            
            <script>
                function openLightbox(url) {
                    const lightbox = document.getElementById('gallery-lightbox');
                    const img = document.getElementById('lightbox-img');
                    img.src = url;
                    lightbox.classList.replace('hidden', 'flex');
                }
            </script>
        @else
            <p class="text-center opacity-75">No hay imágenes en la galería.</p>
        @endif
    </div>
</section>
