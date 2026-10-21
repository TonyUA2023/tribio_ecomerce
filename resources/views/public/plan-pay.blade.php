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
                        <span>Pago seguro procesado por Mercado Pago</span>
                    </div>
                </div>
            </div>

            {{-- Right: Billing details + pay --}}
            <div class="md:col-span-7">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-100 shadow-sm">

                    @if(!$billingReady)
                        <div class="p-5 rounded-2xl bg-amber-50 border border-amber-100 text-amber-800 text-sm">
                            <p class="font-bold mb-1">Los pagos todavía no están activos.</p>
                            <p class="text-xs leading-relaxed">Tu tienda <strong>{{ $store->name }}</strong> ya quedó guardada como borrador. En cuanto se configure Mercado Pago en el servidor podrás volver a esta misma página para activarla.</p>
                        </div>
                    @else
                        <div class="border-b border-slate-100 pb-4 mb-5">
                            <h3 class="text-base font-extrabold text-slate-900">Suscripción mensual con Mercado Pago</h3>
                            <p class="text-xs text-slate-400 mt-1">Te llevaremos a Mercado Pago para que autorices el cobro mensual de S/. {{ number_format($plan['price'], 2) }} con tu tarjeta. Tus datos de pago los ve solo Mercado Pago, nunca pasan por nuestros servidores.</p>
                        </div>

                        @if($errors->has('billing'))
                            <div class="p-3.5 mb-4 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-600 text-xs">{{ $errors->first('billing') }}</div>
                        @endif

                        <form method="POST" action="{{ route('plan.subscribe', $store) }}">
                            @csrf
                            <button type="submit"
                                    class="w-full py-3.5 px-4 font-bold bg-sky-500 hover:bg-sky-600 text-white text-xs uppercase tracking-wider rounded-xl transition-all shadow-md shadow-sky-500/10 flex items-center justify-center gap-2.5">
                                Suscribirme por S/. {{ number_format($plan['price'], 2) }} al mes
                            </button>
                        </form>

                        <p class="text-center text-[10px] text-slate-500 mt-4">Al pagar aceptas los <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-slate-700">Términos y Condiciones</a> y confirmas que leíste la <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer" class="underline hover:text-slate-700">Política de Privacidad y Tratamiento de Datos</a>.</p>
                    @endif

                </div>
            </div>

        </div>
    </div>
</section>
@endsection
