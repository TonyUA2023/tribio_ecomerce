<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} - Código a Medida</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-black text-white font-outfit min-h-screen flex flex-col items-center justify-center">
    
    <div class="max-w-2xl text-center space-y-6">
        <h1 class="text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-500">
            {{ $store->name }}
        </h1>
        <p class="text-xl text-gray-400">
            Esta es una página de ejemplo creada 100% con código a medida.
        </p>
        <p class="text-sm text-gray-500">
            Si estás viendo esto, significa que has seleccionado "Código a Medida" en el panel de control y has creado la carpeta <code>resources/views/clientes_custom/{{ $store->slug }}</code>.
        </p>
        
        <div class="mt-8 pt-8 border-t border-white/10 flex flex-wrap gap-4 justify-center">
            @foreach($categories as $category)
                <span class="px-4 py-2 bg-white/5 rounded-full text-sm">{{ $category->name }}</span>
            @endforeach
        </div>
    </div>

</body>
</html>
