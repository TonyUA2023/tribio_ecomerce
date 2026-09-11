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

        <h1 class="text-3xl md:text-4xl text-[#1A1A1A] font-bold mb-3">¡Gracias por tu compra!</h1>
        <p class="text-gray-600 mb-6">Tu pedido ha sido registrado con éxito. Hemos enviado un correo de confirmación con los detalles.</p>

        {{-- Estado del Pago / Mercado Pago --}}
        @if($order->payment_status === 'paid')
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-center gap-3 text-emerald-800 text-sm font-semibold">
            <span class="text-lg">💳</span>
            <span>¡Pago Aprobado con Tarjeta vía Mercado Pago! Tu orden está 100% confirmada.</span>
        </div>
        @elseif($order->payment_method === 'mercadopago')
        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-center justify-center gap-3 text-amber-800 text-sm font-semibold">
            <span class="text-lg">⏳</span>
            <span>Pago con Tarjeta en proceso de verificación por Mercado Pago. Te notificaremos en cuanto se confirme.</span>
        </div>
        @endif

        <div class="bg-gray-50 rounded-2xl p-6 mb-8 text-left border border-gray-100">
            <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-gray-200">
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-1">Número de Pedido</p>
                    <p class="text-[#1A1A1A] font-bold text-base">{{ $order->order_number }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-1">Fecha</p>
                    <p class="text-[#1A1A1A] font-medium">{{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : date('d/m/Y H:i') }}</p>
                </div>
            </div>

            {{-- Detalle de Items y Variantes --}}
            <div class="mb-4 pb-4 border-b border-gray-200 space-y-3">
                <p class="text-xs text-gray-500 font-bold uppercase tracking-wide">Artículos Comprados</p>
                @foreach($order->items as $item)
                <div class="flex items-center justify-between text-sm py-1">
                    <div class="flex-1 pr-4">
                        <span class="font-medium text-gray-900">{{ $item->product_name }}</span>
                        @if($item->variant_title)
                            <span class="block text-xs text-[#C8A68B] font-semibold">{{ $item->variant_title }}</span>
                        @endif
                        <span class="text-xs text-gray-500">Cant: {{ $item->quantity }} x {{ $order->currency === 'USD' ? '$' : 'S/' }} {{ number_format($item->price, 2) }}</span>
                    </div>
                    <span class="font-bold text-gray-800">
                        {{ $order->currency === 'USD' ? '$' : 'S/' }} {{ number_format($item->subtotal, 2) }}
                    </span>
                </div>
                @endforeach
            </div>
            
            <div class="space-y-2.5">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="font-medium text-[#1A1A1A]">{{ $order->currency === 'USD' ? '$' : 'S/' }} {{ number_format($order->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Envío {{ $order->is_express_shipping ? '(Express)' : '' }}</span>
                    <span class="font-medium text-[#1A1A1A]">+ {{ $order->currency === 'USD' ? '$' : 'S/' }} {{ number_format($order->shipping_cost, 2) }}</span>
                </div>
                <div class="flex justify-between pt-3 border-t border-gray-200">
                    <span class="font-bold text-base text-[#1A1A1A]">Total</span>
                    <span class="font-black text-[#C8A68B] text-xl">{{ $order->currency === 'USD' ? '$' : 'S/' }} {{ number_format($order->total, 2) }}</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <button onclick="window.openCustomerModal ? window.openCustomerModal('orders') : window.dispatchEvent(new CustomEvent('open-customer-modal', { detail: { tab: 'orders' } }))"
                    class="px-6 py-3 bg-[#C8A68B] hover:bg-[#b08e73] text-white font-bold rounded-xl transition-all shadow-md flex items-center justify-center gap-2 text-sm">
                📦 Rastrear mi pedido en tiempo real
            </button>
            <a href="{{ route('store.show', $store->slug) }}" class="px-6 py-3 bg-[#1A1A1A] text-white font-bold rounded-xl hover:bg-stone-800 transition-colors shadow-md text-sm flex items-center justify-center">
                Volver a la tienda
            </a>
            @if($store->whatsapp_link)
            <a href="{{ $store->whatsapp_link }}?text={{ urlencode('Hola, quisiera saber el estado de mi pedido: ' . $order->order_number) }}" target="_blank" class="px-6 py-3 bg-white text-[#1A1A1A] border border-gray-300 font-bold rounded-xl hover:bg-gray-50 transition-colors text-sm flex items-center justify-center">
                WhatsApp
            </a>
            @endif
        </div>

        <div class="mt-8 pt-6 border-t border-stone-100 text-center">
            <p class="text-xs font-semibold text-gray-400 tracking-wider">
                Impulsado por <span class="text-[#1A1A1A] font-bold">Tribio</span>
            </p>
        </div>
    </div>
</div>
@endsection
