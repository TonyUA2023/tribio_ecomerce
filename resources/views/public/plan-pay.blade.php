@extends('layouts.public')

@section('title', 'Confirma tu suscripción | Tribio')

@section('content')
<section class="relative bg-[#F6F6F6] min-h-screen pt-28 pb-20 lg:pt-36">
    <div class="container-tribio">

        <div class="max-w-4xl mx-auto mb-8">
            <p class="text-xs font-bold uppercase tracking-widest text-sky-500 mb-2">Paso 2 de 2</p>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900">Confirma tu suscripción</h1>
            <p class="text-slate-500 text-sm mt-2">Tu tienda <strong>{{ $store->name }}</strong> está lista. Solo falta activar tu plan para publicarla.</p>
        </div>

        <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-8">

            {{-- Left: Cart / Order summary --}}
            <div class="md:col-span-5">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-100 shadow-sm sticky top-28">
                    <span class="text-[10px] font-black text-sky-500 uppercase tracking-widest block mb-1">Tu carrito</span>
                    <h2 class="text-xl font-extrabold text-slate-900 leading-tight uppercase">{{ $plan['label'] }}</h2>

                    <div class="flex items-end gap-1 mt-4">
                        <span class="text-slate-400 text-sm font-bold">S/.</span>
                        <span class="text-4xl font-black text-slate-900 leading-none">{{ number_format($plan['price'], 2) }}</span>
                        <span class="text-slate-400 text-xs font-bold mb-0.5">/mes</span>
                    </div>
                    <p class="text-slate-400 text-[11px] mt-2">Facturación mensual recurrente. Cancela cuando quieras desde tu panel.</p>

                    <ul class="space-y-3 mt-6 border-t border-slate-100 pt-5 text-xs text-slate-600">
                        @foreach($plan['features'] as $feat)
                        <li class="flex items-start gap-2.5">
                            <span class="text-sky-500 font-bold">✓</span>
                            <span class="pt-0.5">{{ $feat }}</span>
                        </li>
                        @endforeach
                    </ul>

                    <div class="mt-6 border-t border-slate-100 pt-5">
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                            <span>Tienda</span>
                            <span class="font-semibold text-slate-800">{{ $store->name }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>Dirección</span>
                            <span class="font-semibold text-slate-800">tribio.pe/tienda/{{ $store->slug }}</span>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 flex items-center gap-2 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                        <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span>Pago seguro procesado por Culqi</span>
                    </div>
                </div>
            </div>

            {{-- Right: Billing details + pay --}}
            <div class="md:col-span-7">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-100 shadow-sm">

                    @if(!$culqiReady)
                        <div class="p-5 rounded-2xl bg-amber-50 border border-amber-100 text-amber-800 text-sm">
                            <p class="font-bold mb-1">Los pagos todavía no están activos.</p>
                            <p class="text-xs leading-relaxed">Tu tienda <strong>{{ $store->name }}</strong> ya quedó guardada como borrador. En cuanto se configuren las llaves de Culqi en el servidor podrás volver a esta misma página para completar el pago y activarla.</p>
                        </div>
                    @else
                        <div x-data="planPay()" x-init="init()">
                            <div class="border-b border-slate-100 pb-4 mb-5">
                                <h3 class="text-base font-extrabold text-slate-900">Datos de facturación</h3>
                                <p class="text-xs text-slate-400 mt-1">Los solicita Culqi para verificar el pago. Tu tarjeta se captura directamente por Culqi, nunca pasa por nuestros servidores.</p>
                            </div>

                            <template x-if="errorMsg">
                                <div class="p-3.5 mb-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-600 text-xs" x-text="errorMsg"></div>
                            </template>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Dirección *</label>
                                    <input type="text" x-model="address" placeholder="Av. Ejemplo 123" minlength="5" maxlength="100"
                                           class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Ciudad *</label>
                                    <input type="text" x-model="addressCity" placeholder="Lima" minlength="2" maxlength="30"
                                           class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900">
                                </div>
                            </div>

                            <button type="button" @click="pay()" :disabled="loading || !canPay"
                                    class="w-full mt-6 py-3.5 px-4 font-bold bg-sky-500 hover:bg-sky-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs uppercase tracking-wider rounded-xl transition-all shadow-md shadow-sky-500/10 flex items-center justify-center gap-2.5">
                                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                <span x-text="loading ? 'Procesando...' : 'Pagar S/. {{ number_format($plan['price'], 2) }} con tarjeta'"></span>
                            </button>

                            <p class="text-center text-[10px] text-slate-500 mt-4">Al pagar aceptas los <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-slate-700">Términos y Condiciones</a> y confirmas que leíste la <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-slate-700">Política de Privacidad y Tratamiento de Datos</a>.</p>
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </div>
</section>
@endsection

@if($culqiReady)
@push('scripts')
<script src="https://js.culqi.com/checkout-js"></script>
<script>
function planPay() {
    return {
        address: '',
        addressCity: '',
        loading: false,
        errorMsg: '',
        culqi: null,

        get canPay() {
            return this.address.trim().length >= 5 && this.addressCity.trim().length >= 2;
        },

        init() {
            const settings = {
                title: 'Tribio - {{ $plan['label'] }}',
                currency: 'PEN',
                amount: {{ (int) round($plan['price'] * 100) }},
            };
            const client = { email: '{{ addslashes(Auth::user()->email) }}' };
            const options = {
                lang: 'auto',
                modal: true,
                installments: false,
                paymentMethods: {
                    tarjeta: true, yape: false, billetera: false, bancaMovil: false, agente: false, cuotealo: false,
                },
            };
            const appearance = {
                theme: 'default',
                defaultStyle: { buttonBackground: '#0ea5e9', buttonTextColor: '#ffffff' },
            };

            this.culqi = new CulqiCheckout('{{ $culqiPublicKey }}', { settings, client, options, appearance });

            const self = this;
            this.culqi.culqi = function () {
                if (self.culqi.token) {
                    const token = self.culqi.token.id;
                    self.culqi.close();
                    self.sendToken(token);
                } else {
                    self.culqi.close();
                    self.loading = false;
                    self.errorMsg = (self.culqi.error && self.culqi.error.user_message) || 'No se pudo procesar la tarjeta. Intenta nuevamente.';
                }
            };
        },

        pay() {
            if (!this.canPay || this.loading) return;
            this.errorMsg = '';
            this.loading = true;
            this.culqi.open();
        },

        async sendToken(token) {
            try {
                const res = await fetch('{{ route('plan.charge', $store) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        culqi_token: token,
                        address: this.address,
                        address_city: this.addressCity,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    this.loading = false;
                    this.errorMsg = data.message || 'No se pudo procesar el pago.';
                }
            } catch (e) {
                this.loading = false;
                this.errorMsg = 'Error de conexión. Intenta nuevamente.';
            }
        },
    };
}
</script>
@endpush
@endif
