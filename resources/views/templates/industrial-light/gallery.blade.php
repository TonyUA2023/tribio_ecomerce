<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galería de Fotos — {{ $store->name }}</title>
    <meta name="description" content="Galería de fotos de repuestos y operaciones de {{ $store->name }}">

    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --accent: {{ $store->accent_color ?? '#DC2626' }};
            --accent-hover: #B91C1C;
            --secondary: {{ $store->secondary_color ?? '#16A34A' }};
            --bg: {{ $store->bg_color ?? '#FFFFFF' }};
        }
        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg);
            color: #1F2937;
        }
        .btn-accent {
            background-color: var(--accent);
            color: white;
            transition: all 0.2s ease-in-out;
        }
        .btn-accent:hover {
            background-color: var(--accent-hover);
        }

        /* Subtle entrance animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        /* Pulsating ripple animation for floating WhatsApp button */
        @keyframes whatsapp-ripple {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.4), 0 0 0 0 rgba(37, 211, 102, 0.4);
            }
            40% {
                box-shadow: 0 0 0 8px rgba(37, 211, 102, 0), 0 0 0 0 rgba(37, 211, 102, 0.4);
            }
            80% {
                box-shadow: 0 0 0 8px rgba(37, 211, 102, 0), 0 0 0 16px rgba(37, 211, 102, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0), 0 0 0 0 rgba(37, 211, 102, 0);
            }
        }
        .whatsapp-btn {
            animation: whatsapp-ripple 2s infinite;
        }
    </style>
</head>
<body class="antialiased bg-gray-50 flex flex-col min-h-screen">

    <!-- Header -->
    <header class="bg-white border-b border-gray-100 shadow-sm py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <a href="{{ route('store.show', $store->slug) }}" class="flex items-center gap-3">
                @if($store->logo_path)
                    <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-10 w-auto object-contain">
                @else
                    <span class="text-2xl font-black tracking-tight text-gray-900">TFL <span class="text-accent">PARTS</span></span>
                @endif
            </a>
            <a href="{{ route('store.show', $store->slug) }}" class="text-sm font-bold text-gray-600 hover:text-accent flex items-center gap-1">
                Volver a la tienda
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center max-w-xl mx-auto mb-12">
            <h1 class="text-3xl sm:text-4xl font-black text-gray-900">Galería de Fotos Completa</h1>
            <p class="text-sm text-gray-500 mt-2">Explora nuestro almacén, repuestos agrícolas listos para entrega y nuestro servicio en Chiclayo.</p>
        </div>

        @if($galleryItems->isEmpty())
            <div class="text-center py-20 bg-white rounded-3xl border border-gray-200 shadow-sm">
                <svg class="w-16 h-16 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-gray-400 text-sm font-semibold mt-4">Aún no se han subido fotos a la galería.</p>
                <a href="{{ route('store.show', $store->slug) }}" class="mt-4 inline-block px-5 py-2.5 rounded-lg btn-accent font-bold text-xs">Volver al Inicio</a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 animate-fade-in-up">
                @foreach($galleryItems as $item)
                    <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group">
                        <div class="aspect-square bg-gray-100 flex items-center justify-center overflow-hidden border-b border-gray-100 relative">
                            @if($item->image_path)
                                <img src="{{ $item->image_url }}" alt="{{ $item->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            @else
                                <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="p-6">
                            <h3 class="font-extrabold text-gray-900 text-base leading-tight">{{ $item->title }}</h3>
                            @if($item->description)
                                <p class="text-xs text-gray-500 mt-2 leading-relaxed">{{ $item->description }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-12">
                {{ $galleryItems->links() }}
            </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-500 text-xs py-8 border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-2">
            <p>© {{ date('Y') }} {{ $store->name }}. Todos los derechos reservados.</p>
            <p>Plataforma <a href="{{ route('home') }}" class="text-gray-400 underline">Tribio</a></p>
        </div>
    </footer>

    <!-- Floating WhatsApp Button with Pulsating Effect -->
    @if($store->whatsapp_phone)
        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp_phone) }}?text={{ urlencode('Hola, me gustaría recibir más información.') }}"
           target="_blank"
           class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-14 h-14 bg-[#25D366] rounded-full text-white shadow-2xl hover:bg-[#20ba5a] transition-all duration-300 hover:scale-110 whatsapp-btn"
           aria-label="Contactar por WhatsApp">
            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.746.953 3.71 1.458 5.704 1.463h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
        </a>
    @endif

</body>
</html>
