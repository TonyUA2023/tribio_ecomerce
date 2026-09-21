@extends('layouts.dashboard')
@section('title','Pasarela de Pago') @section('page_title','💳 Pasarela de Pago')
@section('content')
<div class="w-full max-w-5xl mx-auto space-y-5 sm:space-y-6">
    <form id="payment-gateway-form" method="POST" action="{{ route('dashboard.gateway.update') }}" class="space-y-5 sm:space-y-6" x-data="{
        checkoutMode: '{{ old('checkout_mode', $store?->checkout_mode ?? 'whatsapp') }}',
        gateway: '{{ old('payment_gateway', $store?->payment_gateway ?? '') }}',
        showMpToken: false, showPaypalSecret: false, showFlowSecret: false,
    }">
        @csrf

        {{-- Barra de acción: pegada arriba, no tapa el contenido al hacer scroll --}}
        <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
            <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
                <a href="{{ route('dashboard.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver al panel" title="Volver al panel">←</a>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Pasarela de Pago</p>
                    <p class="text-white font-bold text-sm truncate">Cómo cobras a tus clientes</p>
                </div>
                <button type="submit" class="btn-primary flex-shrink-0 !px-4 sm:!px-6 text-xs sm:text-sm">
                    <span class="hidden sm:inline">💾 Guardar Cambios</span><span class="sm:hidden">💾 Guardar</span>
                </button>
            </div>
        </div>

        @if($errors->any())
        <div class="glass-card p-4 sm:p-5 border border-red-400/20 bg-red-500/5">
            <p class="text-red-400 font-bold text-xs sm:text-sm mb-1.5 flex items-center gap-2">⚠️ Revisa estos campos antes de guardar:</p>
            <ul class="text-xs text-red-400/90 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $error_msg)
                    <li>{{ $error_msg }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Modo de Venta y Pasarela de Pago --}}
        <div class="glass-card p-5 sm:p-6">
            <h3 class="text-white font-bold text-sm mb-2">💳 Modo de Venta y Pasarela de Pago</h3>
            <p class="text-xs text-white/50 mb-4">Elige cómo concretar tus pedidos y, si aceptas tarjeta, configura tus credenciales para recibir el dinero.</p>
            <div class="space-y-4">
                <div>
                    <label class="input-label">Modo de Venta / Checkout</label>
                    <select name="checkout_mode" x-model="checkoutMode" class="input-field">
                        <option value="whatsapp">Solo WhatsApp (Redirección Directa)</option>
                        <option value="card">Solo Tarjeta (Pasarela de Pago)</option>
                        <option value="mixed">Ambos (Tarjeta + WhatsApp)</option>
                    </select>
                </div>

                <div x-show="checkoutMode === 'card' || checkoutMode === 'mixed'" x-cloak class="space-y-4 bg-white/3 border border-white/5 p-4 rounded-xl">
                    <div>
                        <label class="input-label">Pasarela de Pago Habilitada</label>
                        <select name="payment_gateway" x-model="gateway" class="input-field">
                            <option value="">Ninguna todavía — selecciona una...</option>
                            <option value="mercado_pago">Mercado Pago</option>
                            <option value="paypal">PayPal</option>
                            <option value="flow">Flow</option>
                        </select>
                        <p class="text-[10px] text-white/40 mt-1">Solo una pasarela está activa a la vez: al elegir una y guardar, es la única que se le cobra a tus clientes y la única que verán en el checkout — así evitas confusiones. Puedes cambiarla cuando quieras; tus credenciales guardadas de otras pasarelas no se pierden.</p>
                        @error('payment_gateway') <p class="text-sm text-red-300 mt-1" role="alert">{{ $message }}</p> @enderror
                    </div>

                    {{-- Campos para Mercado Pago --}}
                    <div x-show="gateway === 'mercado_pago'" x-cloak class="space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <p class="text-[11px] text-tribio-cyan font-bold uppercase tracking-wider">Mercado Pago (Tarjetas Débito y Crédito)</p>
                            @php
                                $currentMpToken = $store?->mp_access_token ?? $store?->gateway_access_token;
                                $isTest = $currentMpToken && str_starts_with(trim($currentMpToken), 'TEST-');
                                $isLive = $currentMpToken && str_starts_with(trim($currentMpToken), 'APP_USR-');
                            @endphp
                            @if($isTest)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">🧪 Modo Pruebas (Sandbox)</span>
                            @elseif($isLive)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">🟢 Modo Producción (En Vivo)</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-white/10 text-white/50">No configurado</span>
                            @endif
                        </div>

                        <div class="p-3 bg-white/5 rounded-xl border border-white/10 text-xs text-slate-300 space-y-1.5">
                            <p class="font-semibold text-white flex items-center gap-1.5"><span>🔒 Pagos 100% Seguros y Certificados PCI-DSS</span></p>
                            <p class="text-white/60 text-[11px]">
                                Los datos de tarjeta se procesan directamente en Mercado Pago. Obtén tus credenciales en el
                                <a href="https://www.mercadopago.com/developers/panel/app" target="_blank" class="text-tribio-cyan underline font-bold hover:text-white">Panel de Desarrolladores de Mercado Pago ↗</a>.
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[10px] pt-1">
                                <div class="bg-black/30 p-2 rounded-lg border border-white/5">
                                    <span class="font-bold text-amber-300">🧪 Para pruebas (Sandbox):</span>
                                    <p class="text-white/60 mt-0.5">Usa credenciales que inician con <code class="text-tribio-cyan">TEST-...</code> para pagar con tarjetas de test sin dinero real.</p>
                                </div>
                                <div class="bg-black/30 p-2 rounded-lg border border-white/5">
                                    <span class="font-bold text-emerald-300">🚀 Para vender en vivo:</span>
                                    <p class="text-white/60 mt-0.5">Usa credenciales que inician con <code class="text-emerald-400">APP_USR-...</code> para recibir dinero real en tu cuenta.</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="input-label">Token de Acceso (Access Token) *</label>
                            <div class="relative">
                                <input :type="showMpToken ? 'text' : 'password'" name="mp_access_token" value="{{ old('mp_access_token', $store?->mp_access_token ?? $store?->gateway_access_token) }}" class="input-field font-mono text-xs pr-11" placeholder="TEST-... o APP_USR-...">
                                <button type="button" @click="showMpToken = !showMpToken" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 text-sm" :aria-label="showMpToken ? 'Ocultar token' : 'Mostrar token'">
                                    <span x-show="!showMpToken">👁️</span><span x-show="showMpToken" x-cloak>🙈</span>
                                </button>
                            </div>
                            <p class="text-[10px] text-white/40 mt-1">Este dato es secreto: no lo compartas con nadie.</p>
                        </div>
                        <div>
                            <label class="input-label">Llave Pública (Public Key) *</label>
                            <input type="text" name="mp_public_key" value="{{ old('mp_public_key', $store?->mp_public_key ?? $store?->gateway_public_key) }}" class="input-field font-mono text-xs" placeholder="TEST-... o APP_USR-...">
                        </div>
                    </div>

                    {{-- Campos para Flow --}}
                    <div x-show="gateway === 'flow'" x-cloak>
                        @include('components.checkout.flow-settings')
                    </div>

                    {{-- Campos para PayPal --}}
                    <div x-show="gateway === 'paypal'" x-cloak class="space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2 pt-2">
                            <p class="text-[11px] text-tribio-cyan font-bold uppercase tracking-wider">PayPal (Clientes internacionales, cobra en USD)</p>
                            @if($store?->paypal_client_id)
                                @if(($store->paypal_mode ?? 'sandbox') === 'live')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">🟢 Modo Producción (En Vivo)</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">🧪 Modo Pruebas (Sandbox)</span>
                                @endif
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-white/10 text-white/50">No configurado</span>
                            @endif
                        </div>

                        <div class="p-3 bg-white/5 rounded-xl border border-white/10 text-xs text-slate-300 space-y-1.5">
                            <p class="font-semibold text-white flex items-center gap-1.5"><span>💵 PayPal siempre cobra en dólares (USD)</span></p>
                            <p class="text-white/60 text-[11px]">
                                A diferencia de Mercado Pago, PayPal no admite soles — tus productos deben tener un precio en USD configurado (o se convierte automáticamente al tipo de cambio del momento). Obtén tus credenciales en el
                                <a href="https://developer.paypal.com/dashboard/applications" target="_blank" class="text-tribio-cyan underline font-bold hover:text-white">Panel de Desarrolladores de PayPal ↗</a>.
                            </p>
                        </div>

                        <div>
                            <label class="input-label">Modo</label>
                            <select name="paypal_mode" class="input-field">
                                <option value="sandbox" {{ old('paypal_mode', $store?->paypal_mode ?? 'sandbox') === 'sandbox' ? 'selected' : '' }}>🧪 Pruebas (Sandbox)</option>
                                <option value="live" {{ old('paypal_mode', $store?->paypal_mode ?? 'sandbox') === 'live' ? 'selected' : '' }}>🟢 Producción (En Vivo)</option>
                            </select>
                        </div>
                        <div>
                            <label class="input-label">Client ID</label>
                            <input type="text" name="paypal_client_id" value="{{ old('paypal_client_id', $store?->paypal_client_id) }}" class="input-field font-mono text-xs" placeholder="AeA1QIZX...">
                        </div>
                        <div>
                            <label class="input-label">Client Secret</label>
                            <div class="relative">
                                <input :type="showPaypalSecret ? 'text' : 'password'" name="paypal_client_secret" value="{{ old('paypal_client_secret', $store?->paypal_client_secret) }}" class="input-field font-mono text-xs pr-11" placeholder="EL...">
                                <button type="button" @click="showPaypalSecret = !showPaypalSecret" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 text-sm" :aria-label="showPaypalSecret ? 'Ocultar secreto' : 'Mostrar secreto'">
                                    <span x-show="!showPaypalSecret">👁️</span><span x-show="showPaypalSecret" x-cloak>🙈</span>
                                </button>
                            </div>
                            <p class="text-[10px] text-white/40 mt-1">Este dato es secreto: no lo compartas con nadie.</p>
                        </div>
                        <div>
                            <label class="input-label">Webhook ID <span class="text-white/40 normal-case font-normal">(opcional)</span></label>
                            <input type="text" name="paypal_webhook_id" value="{{ old('paypal_webhook_id', $store?->paypal_webhook_id) }}" class="input-field font-mono text-xs" placeholder="Solo si configuraste un webhook en tu app de PayPal">
                            <p class="text-[10px] text-white/40 mt-1">No es obligatorio: los pagos se confirman sin esto. Solo sirve como respaldo adicional.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
