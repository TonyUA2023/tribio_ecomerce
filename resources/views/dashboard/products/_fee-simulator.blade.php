{{-- Simulador de comisiones de Mercado Pago. Vive dentro del x-data de "Precio" (usa su penPrice).
     Solo se muestra si la tienda cobra con Mercado Pago. Es informativo: no cambia ningún cobro.
     El cálculo lo hace el servidor (App\Services\Payments\MercadoPagoFees), el mismo para todos. --}}
@php $mpOptions = app(\App\Services\Payments\MercadoPagoFees::class)->options(); @endphp
@if(($store->payment_gateway ?? null) === 'mercado_pago' && !empty($mpOptions))
<div class="border-t border-white/10 pt-4 mt-4"
     x-data="{
        open: false, mode: 'charge', netTarget: '', result: null, timer: null,
        opts: @js($mpOptions), url: '{{ route('dashboard.productos.fee-simulator') }}',
        method: @js($mpOptions[0]['key']), release: @js($mpOptions[0]['release'][0]['key'] ?? ''),
        get releases() { return (this.opts.find(o => o.key === this.method) || this.opts[0]).release; },
        amount() { return parseFloat(this.mode === 'charge' ? penPrice : this.netTarget); },
        money(v) { return (this.result ? this.result.symbol : 'S/') + ' ' + Number(v).toFixed(2); },
        run() {
            clearTimeout(this.timer);
            this.timer = setTimeout(async () => {
                const amount = this.amount();
                if (!(amount > 0)) { this.result = null; return; }
                const q = new URLSearchParams({ mode: this.mode, amount, method: this.method, release: this.release });
                try {
                    const res = await fetch(this.url + '?' + q, { headers: { Accept: 'application/json' } });
                    this.result = res.ok ? await res.json() : null;
                } catch (e) { this.result = null; }
            }, 250);
        },
        useThisPrice() { if (this.result) { penPrice = this.result.charge.toFixed(2); this.mode = 'charge'; } },
     }"
     x-init="run(); $watch('penPrice', () => run()); $watch('netTarget', () => run()); $watch('mode', () => run()); $watch('release', () => run()); $watch('method', () => { release = releases[0].key; run(); })">
    <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-3 text-left cursor-pointer" :aria-expanded="open.toString()">
        <span class="text-sm font-bold text-white">🧮 Simulador de comisiones · Mercado Pago</span>
        <span class="flex items-center gap-2 text-xs text-white/50">
            <span x-show="!open && result && mode === 'charge'" x-cloak>Recibes <strong class="text-tribio-cyan" x-text="result ? money(result.net) : ''"></strong></span>
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </span>
    </button>

    <div x-show="open" x-cloak class="mt-4 space-y-4">
        <p class="text-xs text-white/50">Mercado Pago descuenta una comisión por cada cobro en tu tienda. Calcula cuánto te llega, o qué precio poner para recibir lo que quieres.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-white/80 cursor-pointer"><input type="radio" value="charge" x-model="mode" class="accent-cyan-400"> Cobro este precio → ¿cuánto recibo?</label>
                <label class="flex items-center gap-2 text-sm text-white/80 cursor-pointer"><input type="radio" value="receive" x-model="mode" class="accent-cyan-400"> Quiero recibir → ¿qué precio pongo?</label>
            </div>
            <div x-show="mode === 'receive'" x-cloak>
                <label class="input-label">¿Cuánto quieres recibir? (S/.)</label>
                <input type="number" x-model="netTarget" class="input-field text-right font-bold" step="0.01" min="0" placeholder="0.00">
            </div>
            <p x-show="mode === 'charge'" class="text-xs text-white/50 self-center">Se usa el <strong class="text-white/80">precio de venta</strong> de arriba.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="input-label">¿Con qué te pagan?</label>
                <select x-model="method" class="input-field">
                    <template x-for="o in opts" :key="o.key"><option :value="o.key" x-text="o.label"></option></template>
                </select>
            </div>
            <div>
                <label class="input-label">¿Cuándo recibes tu dinero?</label>
                <select x-model="release" class="input-field">
                    <template x-for="r in releases" :key="r.key"><option :value="r.key" x-text="r.label"></option></template>
                </select>
            </div>
        </div>

        <template x-if="result">
            <div class="rounded-xl border border-white/10 bg-white/[0.03] divide-y divide-white/10 text-sm">
                <div class="flex items-center justify-between px-4 py-3"><span class="text-white/70">Cobras al cliente</span><strong class="text-white" x-text="money(result.charge)"></strong></div>
                <div class="flex items-start justify-between gap-3 px-4 py-3">
                    <span class="text-white/70">Comisión de Mercado Pago<small class="block text-[11px] text-white/40" x-text="result.summary"></small></span>
                    <strong class="text-red-300 whitespace-nowrap" x-text="'− ' + money(result.fee)"></strong>
                </div>
                <div class="flex items-center justify-between px-4 py-3"><span class="font-bold text-white">Recibes</span><strong class="text-tribio-cyan text-base" x-text="money(result.net)"></strong></div>
            </div>
        </template>
        <button type="button" x-show="mode === 'receive' && result" x-cloak @click="useThisPrice()" class="btn-secondary py-2 px-4 text-xs">Usar este precio de venta</button>

        <p class="text-[11px] text-white/40 leading-relaxed">Si vendes por <strong class="text-white/60">WhatsApp o pago directo</strong> no hay comisión de Mercado Pago: recibes el precio completo. Las cifras son una guía basada en las tarifas vigentes de tu cuenta de Mercado Pago; pueden cambiar.</p>
    </div>
</div>
@endif
