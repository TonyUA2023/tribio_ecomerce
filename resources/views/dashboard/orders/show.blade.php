@extends('layouts.dashboard')
@section('title', 'Pedidos')
@section('page_title', 'Pedido #' . $order->order_number)
@section('content')
@php
    // wa.me necesita el número con código de país y solo dígitos; los clientes de Perú
    // suelen escribir solo sus 9 dígitos.
    $phoneDigits = preg_replace('/\D/', '', (string) $order->customer_phone);
    if (strlen($phoneDigits) === 9 && ($order->customer_country ?: 'PE') === 'PE') {
        $phoneDigits = '51' . $phoneDigits;
    }
    $statusOptions = [
        'pending'    => 'Pendiente',
        'confirmed'  => 'Confirmado',
        'processing' => 'En proceso',
        'shipped'    => 'Enviado',
        'delivered'  => 'Entregado',
        'cancelled'  => 'Cancelado',
        'refunded'   => 'Reembolsado',
    ];
    $gatewayLabels = ['mercadopago' => 'Mercado Pago', 'paypal' => 'PayPal', 'flow' => 'Flow', 'manual' => 'Registro manual'];
    $kindLabels = ['full' => 'Pago total', 'deposit' => 'Adelanto', 'balance' => 'Saldo'];
    $itemImage = fn($path) => $path ? (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://']) ? $path : asset('storage/' . $path)) : null;
@endphp

{{-- Barra superior --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('dashboard.pedidos.index') }}" class="btn-ghost inline-flex">← Pedidos</a>
        <span class="badge {{ $order->status_badge }}">{{ $order->status_label }}</span>
        <span class="badge {{ $order->payment_status_badge }}">{{ $order->payment_status_label }}</span>
    </div>
    <p class="text-white/40 text-xs">Realizado el {{ $order->created_at->locale('es')->translatedFormat('d \d\e F Y, H:i') }}</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-5">

        {{-- Cliente y envío: lo primero que necesita el dueño para despachar --}}
        <div class="glass-card p-5" x-data="{ copied: false }">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-white font-bold">📦 Cliente y datos de envío</h2>
                <button type="button" class="btn-ghost text-xs inline-flex"
                        @click="navigator.clipboard.writeText($refs.shippingText.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                    <span x-show="!copied">Copiar datos de envío</span>
                    <span x-show="copied" x-cloak>✓ Copiado</span>
                </button>
                <textarea x-ref="shippingText" class="hidden" readonly>{{ $order->shippingLabelText() }}</textarea>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="text-white/40 text-xs">Nombre completo</dt>
                    <dd class="text-white font-semibold">{{ $order->customer_name }}</dd>
                </div>
                <div>
                    <dt class="text-white/40 text-xs">Documento de identidad</dt>
                    <dd class="text-white font-semibold">
                        @if($order->customer_document_number)
                            {{ $order->customer_document_type ?: 'DNI' }} {{ $order->customer_document_number }}
                        @else
                            <span class="text-white/40 font-normal">No registrado</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-white/40 text-xs">Celular</dt>
                    <dd class="text-white">
                        @if($order->customer_phone)
                            <a href="tel:{{ $order->customer_phone }}" class="font-semibold">{{ $order->customer_phone }}</a>
                            @if($phoneDigits)
                            <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener" class="text-tribio-cyan text-xs font-bold ml-2">WhatsApp →</a>
                            @endif
                        @else — @endif
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-white/40 text-xs">Correo electrónico</dt>
                    <dd class="text-white break-all">
                        @if($order->customer_email)<a href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a>@else — @endif
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-white/40 text-xs">Dirección de entrega</dt>
                    <dd class="text-white font-semibold">{{ $order->customer_address ?: 'Sin dirección registrada' }}</dd>
                </div>
                <div>
                    <dt class="text-white/40 text-xs">Ciudad / Distrito</dt>
                    <dd class="text-white">{{ $order->customer_city ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-white/40 text-xs">Departamento / Región</dt>
                    <dd class="text-white">{{ $order->customer_state ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-white/40 text-xs">País</dt>
                    <dd class="text-white">{{ $order->country_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-white/40 text-xs">Código postal</dt>
                    <dd class="text-white">{{ $order->customer_zipcode ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-white/40 text-xs">Tipo de envío</dt>
                    <dd>
                        @if($order->is_express_shipping)<span class="badge badge-purple">⚡ Envío express</span>
                        @else<span class="text-white">Estándar</span>@endif
                    </dd>
                </div>
                @if($order->customer_notes)
                <div class="sm:col-span-2">
                    <dt class="text-white/40 text-xs">Notas / referencia del cliente</dt>
                    <dd class="text-white whitespace-pre-line">{{ $order->customer_notes }}</dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Productos --}}
        <div class="glass-card p-5">
            <h2 class="text-white font-bold mb-4">🛍️ Productos ({{ (int) $order->items->sum('quantity') }})</h2>
            <div class="divide-y divide-white/5">
                @foreach($order->items as $item)
                <div class="flex gap-3 py-3 first:pt-0">
                    <div class="w-14 h-14 rounded-lg bg-white/5 overflow-hidden flex-shrink-0 flex items-center justify-center">
                        @if($img = $itemImage($item->product_image))
                            <img src="{{ $img }}" alt="" class="w-full h-full object-cover">
                        @else <span class="text-xl">📦</span> @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white font-semibold text-sm">{{ $item->product_name }}</p>
                        @if($item->variant_title)
                            <p class="text-white/50 text-xs">{{ $item->variant_title }}</p>
                        @endif
                        @if($item->product_sku)
                            <p class="text-white/30 text-xs">SKU: {{ $item->product_sku }}</p>
                        @endif
                        <p class="text-white/50 text-xs mt-0.5">{{ $item->quantity }} × {{ $order->money($item->price) }}</p>
                    </div>
                    <p class="text-white font-bold text-sm whitespace-nowrap">{{ $order->money($item->subtotal) }}</p>
                </div>
                @endforeach
            </div>

            <dl class="border-t mt-2 pt-3 space-y-1.5 text-sm" style="border-color: rgba(255,255,255,0.08);">
                <div class="flex justify-between"><dt class="text-white/50">Subtotal</dt><dd class="text-white">{{ $order->money($order->subtotal) }}</dd></div>
                @if((float) $order->discount > 0)
                <div class="flex justify-between"><dt class="text-white/50">Descuento</dt><dd class="text-white">− {{ $order->money($order->discount) }}</dd></div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-white/50">Envío{{ $order->is_express_shipping ? ' (express)' : '' }}</dt>
                    <dd class="text-white">{{ (float) $order->shipping_cost > 0 ? $order->money($order->shipping_cost) : 'Gratis' }}</dd>
                </div>
                <div class="flex justify-between pt-1.5 border-t" style="border-color: rgba(255,255,255,0.08);">
                    <dt class="text-white font-bold">Total</dt><dd class="text-white font-black text-lg">{{ $order->money($order->total) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="space-y-5">
        {{-- Estado --}}
        <div class="glass-card p-5">
            <h2 class="text-white font-bold mb-3">Estado del pedido</h2>
            <form method="POST" action="{{ route('dashboard.pedidos.status', $order) }}" class="flex gap-2">
                @csrf @method('PATCH')
                <select name="status" class="input-field flex-1 min-w-0">
                    @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-primary flex-shrink-0">Actualizar</button>
            </form>
            @if($store->whatsapp_phone)
            <a href="{{ route('dashboard.pedidos.whatsapp', $order) }}" target="_blank" class="btn-whatsapp mt-3 inline-flex w-full justify-center">
                Enviar resumen por WhatsApp
            </a>
            @endif
        </div>

        {{-- Pago --}}
        <div class="glass-card p-5">
            <h2 class="text-white font-bold mb-3">💳 Pago</h2>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-white/40 text-xs">Método de pago</dt>
                    <dd class="text-white font-semibold">{{ $order->payment_method_label }}</dd>
                </div>
                <div>
                    <dt class="text-white/40 text-xs">Estado del pago</dt>
                    <dd><span class="badge {{ $order->payment_status_badge }}">{{ $order->payment_status_label }}</span></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-white/50">Pagado</dt><dd class="text-white font-semibold">{{ $order->money($order->amount_paid) }}</dd>
                </div>
                @if((float) $order->balance_due > 0)
                <div class="flex justify-between">
                    <dt class="text-white/50">Saldo pendiente</dt><dd class="text-white font-semibold">{{ $order->money($order->balance_due) }}</dd>
                </div>
                @endif
                @if($order->paypal_order_id)
                <div><dt class="text-white/40 text-xs">ID de orden PayPal</dt><dd class="text-white/80 text-xs break-all">{{ $order->paypal_order_id }}</dd></div>
                @endif
                @if($order->flow_order_id)
                <div><dt class="text-white/40 text-xs">N° de orden Flow</dt><dd class="text-white/80 text-xs break-all">{{ $order->flow_order_id }}</dd></div>
                @endif
            </dl>

            @if($order->payments->isNotEmpty())
            <div class="border-t mt-4 pt-3" style="border-color: rgba(255,255,255,0.08);">
                <p class="text-white/40 text-xs mb-2">Movimientos</p>
                <ul class="space-y-2">
                    @foreach($order->payments as $payment)
                    <li class="text-xs">
                        <div class="flex justify-between gap-2">
                            <span class="text-white">{{ $kindLabels[$payment->kind] ?? ucfirst($payment->kind) }} · {{ $gatewayLabels[$payment->gateway] ?? ucfirst($payment->gateway) }}</span>
                            <span class="text-white font-semibold whitespace-nowrap">{{ $order->money($payment->amount) }}</span>
                        </div>
                        <p class="text-white/40">
                            {{ $payment->paid_at?->locale('es')->translatedFormat('d M Y, H:i') }}
                            @if($payment->gateway_ref) · Ref. {{ $payment->gateway_ref }} @endif
                            @if($payment->status === 'refunded') · <span class="text-red-400">Reembolsado</span> @endif
                        </p>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
