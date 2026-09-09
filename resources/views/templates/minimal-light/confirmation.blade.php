@extends('templates.minimal-light.layout')

@section('title', 'Pedido Confirmado | ' . $store->name)

@section('content')
<div class="bg-[#FDF8EF] min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full bg-white rounded-3xl shadow-xl border border-gray-100 p-8 md:p-12 text-center">
        
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h1 class="text-3xl md:text-4xl font-serif text-[#1A1A1A] font-bold mb-4">¡Gracias por tu compra!</h1>
        <p class="text-gray-600 mb-8">Tu pedido ha sido registrado con éxito. Hemos enviado un correo de confirmación con los detalles.</p>

        <div class="bg-gray-50 rounded-2xl p-6 mb-8 text-left border border-gray-100">
            <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-gray-200">
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-1">Número de Pedido</p>
                    <p class="text-[#1A1A1A] font-medium">{{ $order->order_number }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-1">Fecha</p>
                    <p class="text-[#1A1A1A] font-medium">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
            
            <div class="space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="font-medium text-[#1A1A1A]">{{ $order->currency === 'USD' ? '$' : 'S/' }} {{ number_format($order->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Envío</span>
                    <span class="font-medium text-[#1A1A1A]">+ {{ $order->currency === 'USD' ? '$' : 'S/' }} {{ number_format($order->shipping_cost, 2) }}</span>
                </div>
                <div class="flex justify-between pt-3 border-t border-gray-200">
                    <span class="font-bold text-[#1A1A1A]">Total</span>
                    <span class="font-bold text-[#C8A68B] text-lg">{{ $order->currency === 'USD' ? '$' : 'S/' }} {{ number_format($order->total, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('store.show', $store->slug) }}" class="px-8 py-3 bg-[#1A1A1A] text-white font-bold rounded-xl hover:bg-[#C8A68B] transition-colors shadow-md">
                Volver a la tienda
            </a>
            <a href="{{ $store->whatsapp_link }}?text={{ urlencode('Hola, quisiera saber el estado de mi pedido: ' . $order->order_number) }}" target="_blank" class="px-8 py-3 bg-white text-[#1A1A1A] border border-gray-300 font-bold rounded-xl hover:bg-gray-50 transition-colors">
                Contactar Soporte
            </a>
        </div>
    </div>
</div>
@endsection
