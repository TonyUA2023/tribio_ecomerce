<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Confirmado — {{ $store->name }}</title>
    <meta name="robots" content="noindex, nofollow">

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
            --secondary-hover: #15803D;
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
        .btn-secondary {
            background-color: var(--secondary);
            color: white;
            transition: all 0.2s ease-in-out;
        }
        .btn-secondary:hover {
            background-color: var(--secondary-hover);
        }
        .text-accent {
            color: var(--accent);
        }
        .text-secondary {
            color: var(--secondary);
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
    </style>
</head>
<body class="antialiased bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

    <div class="max-w-xl w-full bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-xl p-8 sm:p-10 text-center space-y-6 animate-fade-in-up">
        <!-- Success Badge -->
        <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center text-4xl mx-auto text-green-600 animate-bounce">
            ✓
        </div>

        <div class="space-y-2">
            <h1 class="text-3xl font-black text-gray-900 leading-tight">¡Pedido Recibido!</h1>
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest">Pedido #{{ $order->order_number }}</p>
        </div>

        <div class="p-6 bg-gray-50 rounded-2xl border border-gray-100 text-left space-y-4">
            <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest border-b border-gray-200 pb-2">Resumen del Cliente</h3>
            <div class="grid grid-cols-2 gap-4 text-xs text-gray-600">
                <div>
                    <span class="block font-bold text-gray-400 uppercase tracking-wider text-[10px]">Cliente</span>
                    <span class="font-bold text-gray-800 text-sm">{{ $order->customer_name }}</span>
                </div>
                <div>
                    <span class="block font-bold text-gray-400 uppercase tracking-wider text-[10px]">Teléfono</span>
                    <span class="font-bold text-gray-800 text-sm">{{ $order->customer_phone }}</span>
                </div>
                <div class="col-span-2">
                    <span class="block font-bold text-gray-400 uppercase tracking-wider text-[10px]">Dirección de Entrega</span>
                    <span class="font-bold text-gray-800 text-sm">{{ $order->customer_address }}</span>
                </div>
                @if($order->customer_notes)
                    <div class="col-span-2">
                        <span class="block font-bold text-gray-400 uppercase tracking-wider text-[10px]">Notas de Pedido</span>
                        <span class="font-medium text-gray-600 text-xs italic">{{ $order->customer_notes }}</span>
                    </div>
                @endif
            </div>

            <div class="border-t border-gray-200 pt-4 flex justify-between items-center">
                <span class="text-sm font-bold text-gray-600">Total a Pagar:</span>
                <span class="text-xl font-black text-secondary">S/. {{ number_format($order->total, 2) }}</span>
            </div>
        </div>

        <!-- Call to actions -->
        <div class="space-y-3 pt-2">
            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $store->whatsapp_phone) }}?text={{ $order->buildWhatsappMessage() }}"
               target="_blank"
               class="w-full py-4 px-4 rounded-xl font-bold btn-secondary shadow-lg shadow-green-600/20 text-center flex items-center justify-center gap-2">
                Enviar copia del pedido a WhatsApp
            </a>
            <a href="{{ route('store.show', $store->slug) }}"
               class="w-full py-3.5 px-4 rounded-xl bg-gray-100 text-gray-700 hover:bg-gray-200 font-bold text-sm text-center inline-block">
                Volver a la Tienda
            </a>
        </div>

        <p class="text-[10px] text-gray-400 max-w-xs mx-auto leading-tight">
            Se ha abierto una pestaña externa de WhatsApp. Si no cargó o se cerró, puedes presionar el botón verde para volver a enviarlo.
        </p>
    </div>

</body>
</html>
