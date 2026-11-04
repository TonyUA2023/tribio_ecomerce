@extends('layouts.dashboard')
@section('title', 'Caja')
@section('page_title', '🧾 ' . $register->name)
@push('head')
    <link rel="manifest" href="/pos-manifest.webmanifest">
    <meta name="theme-color" content="#0e7490">
    <script defer src="/pos/offline.js?v=1"></script>
@endpush
@section('content')
@php $money = fn ($value) => 'S/ ' . number_format((float) $value, 2); @endphp
<div class="pos-app w-full max-w-7xl mx-auto space-y-3"
     x-data="posRegister({
        productsUrl: @js(route('dashboard.pos.products')),
        customersUrl: @js(route('dashboard.pos.customers')),
        sellUrl: @js(route('dashboard.pos.sell', $session->id)),
        methods: @js($methods),
        sales: {{ (int) $summary['sales'] }},
        salesTotal: {{ (float) $summary['sales_total'] }},
        creditEnabled: @js($creditEnabled),
        extras: @js($extras),
        priceLists: @js($priceLists),
        quote: @js($quote),
        quoteUrl: @js($extras ? route('dashboard.quotes.store') : null),
        firstDue: @js($firstDue),
        kiosk: @js($kiosk),
        offline: @js($offline),
        storeName: @js($store->name),
     })"
     @keydown.window.f2.prevent="$refs.search.focus()" @keydown.window.f9.prevent="openPayment()">

    <div class="pos-toolbar">
        <div class="pos-toolbar-info">
            <strong class="pos-register">{{ $register->name }}</strong>
            <span class="pos-status">
                @if($kiosk)<span class="pos-kiosk">👤 {{ $kiosk['name'] }}{{ $kiosk['manager'] ? ' (encargado)' : '' }}</span> ·@endif
                Abierta {{ $session->opened_at->locale('es')->diffForHumans() }} ·
                <span x-text="sales + (sales === 1 ? ' venta' : ' ventas') + ' · ' + money(salesTotal)"></span>
            </span>
        </div>
        <div class="pos-toolbar-actions">
            <button type="button" class="pos-chip" @click="showCash = !showCash"><x-dashboard-icon name="coins"/>Ingreso / retiro de efectivo</button>
            @if($registers->count() > 1)<a href="{{ route('dashboard.pos.index', ['cajas' => 1]) }}" class="pos-chip"><x-dashboard-icon name="pos"/>Cambiar de caja</a>@endif
            @if($creditEnabled)<a href="{{ route('dashboard.receivables.index') }}" class="pos-chip"><x-dashboard-icon name="wallet"/>Por cobrar</a>@endif
            @if($extras)
                <a href="{{ route('dashboard.quotes.index') }}" class="pos-chip"><x-dashboard-icon name="receipt"/>Proformas</a>
                <a href="{{ route('dashboard.returns.index') }}" class="pos-chip"><x-dashboard-icon name="refresh"/>Devoluciones</a>
            @endif
            <a href="{{ route('dashboard.pos.sessions.show', $session->id) }}" class="pos-chip"><x-dashboard-icon name="inventory"/>Ventas del turno</a>
            @if($kiosk)<form method="POST" action="{{ route('dashboard.pos.lock') }}">@csrf<button type="submit" class="pos-chip">🔒 Bloquear</button></form>@endif
            <a href="{{ route('dashboard.pos.sessions.close', $session->id) }}" class="pos-chip pos-chip-strong">Cerrar caja</a>
        </div>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="glass-card p-3 flex items-center justify-between gap-3 flex-wrap" x-show="!online || pending.length" x-cloak role="status">
        <p class="text-xs" :class="online ? 'text-amber-200' : 'text-red-300'"
           x-text="(online ? '' : 'Sin conexión: sigues vendiendo y las ventas se guardan en este equipo. ') + (pending.length ? pending.length + (pending.length === 1 ? ' venta' : ' ventas') + ' por enviar.' : '')"></p>
        <div class="flex items-center gap-2">
            <button type="button" class="btn-secondary !py-1 text-xs" x-show="online && pending.length" @click="syncNow()">Enviar ahora</button>
            <button type="button" class="btn-ghost !py-1 text-xs" x-show="pending.length" @click="showPending = !showPending">Ver</button>
        </div>
        <ul class="w-full space-y-1" x-show="showPending">
            <template x-for="item in pending" :key="item.uuid">
                <li class="flex items-center justify-between gap-2 text-[11px] text-white/70">
                    <span x-text="new Date(item.offline_at).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }) + ' · ' + money(item.total) + (item.error ? ' · ' + item.error : ' · esperando conexión')"
                          :class="item.error && 'text-red-300'"></span>
                    <button type="button" class="text-white/40 hover:text-red-300" x-show="item.error" @click="discard(item)">Descartar</button>
                </li>
            </template>
        </ul>
    </div>

    <form method="POST" action="{{ route('dashboard.pos.sessions.movement', $session->id) }}" class="glass-card p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end" x-show="showCash" x-cloak>
        @csrf
        <div>
            <label class="input-label" for="cash-type">Movimiento</label>
            <select id="cash-type" name="type" class="input-field"><option value="out">Retiro (sale efectivo)</option><option value="in">Ingreso (entra efectivo)</option></select>
        </div>
        <div>
            <label class="input-label" for="cash-amount">Monto (S/)</label>
            <input id="cash-amount" name="amount" type="number" step="0.10" min="0.01" class="input-field" required>
        </div>
        <div>
            <label class="input-label" for="cash-reason">Motivo</label>
            <input id="cash-reason" name="reason" maxlength="160" class="input-field" placeholder="Pago de delivery" required>
        </div>
        <x-pos-approval id="movement" class="sm:col-span-3" />
        <button type="submit" class="btn-secondary">Anotar</button>
    </form>

    <div class="pos-layout" x-ref="layout">
        {{-- Products --}}
        <section class="pos-panel pos-products" aria-label="Productos">
            <div class="pos-search">
                <label for="pos-search" class="sr-only">Buscar producto o escanear código</label>
                <x-dashboard-icon name="search" class="pos-search-icon"/>
                <input id="pos-search" x-ref="search" x-model="query" @input.debounce.250ms="search()" @keydown.enter.prevent="scan()" autofocus autocomplete="off"
                       class="input-field !text-base" placeholder="Busca o escanea un código (F2)">
            </div>
            <p class="pos-error" x-show="searchError" x-text="searchError" role="alert"></p>
            <div class="pos-products-scroll">
                <div class="pos-grid">
                    <template x-for="product in products" :key="product.id">
                        <button type="button" class="pos-tile" @click="add(product)">
                            <template x-if="product.image"><img :src="product.image" alt="" class="pos-tile-img"></template>
                            <template x-if="!product.image"><span class="pos-tile-badge" :style="badgeStyle(product)" x-text="initials(product.name)" aria-hidden="true"></span></template>
                            <span class="pos-tile-name" x-text="product.name"></span>
                            <span class="pos-tile-foot">
                                <span class="pos-tile-price" x-text="money(product.price)"></span>
                                <span class="pos-stock" :class="stockClass(product)" x-text="stockLabel(product)"></span>
                            </span>
                        </button>
                    </template>
                </div>
                <p class="pos-empty" x-show="!loading && products.length === 0">No encontramos productos con ese nombre o código.</p>
            </div>
        </section>

        {{-- Mobile: bar that opens the sale (the ticket is a sheet below lg) --}}
        <div class="pos-scrim" x-show="cartOpen" x-cloak @click="cartOpen = false" @keydown.escape.window="cartOpen = false"></div>
        <button type="button" class="pos-cartbar" x-show="lines.length && !cartOpen" x-cloak @click="cartOpen = true" aria-label="Ver la venta">
            <span class="pos-cartbar-count" x-text="lines.reduce((n, l) => n + Number(l.quantity || 0), 0)"></span>
            <span class="pos-cartbar-text">Ver venta</span>
            <strong class="pos-cartbar-total" x-text="money(total())"></strong>
        </button>

        {{-- Ticket --}}
        <section class="pos-panel pos-ticket" :class="cartOpen && 'is-open'" aria-label="Venta actual">
            <template x-if="!done && !savedQuote">
                <div class="pos-ticket-inner">
                    <div class="pos-ticket-head">
                        <h3>Venta <span class="pos-ticket-count" x-show="lines.length" x-text="'· ' + lines.length + (lines.length === 1 ? ' producto' : ' productos')"></span></h3>
                        <button type="button" class="pos-link-danger" x-show="lines.length" @click="reset()">Vaciar</button>
                        <button type="button" class="pos-ticket-close" @click="cartOpen = false" aria-label="Cerrar la venta"><x-dashboard-icon name="close"/></button>
                    </div>
                    <div class="pos-ticket-body">
                    <div class="pos-empty-cart" x-show="!lines.length">
                        <x-dashboard-icon name="cart" class="pos-empty-icon"/>
                        <p>Toca un producto o escanéalo para empezar.</p>
                    </div>

                    <div class="space-y-2">
                        <template x-for="(line, index) in lines" :key="line.key">
                            <div class="pos-line">
                                <div class="pos-line-head">
                                    <p class="pos-line-name" x-text="line.product.name"></p>
                                    <button type="button" class="pos-line-remove" @click="lines.splice(index, 1)" :aria-label="'Quitar ' + line.product.name"><x-dashboard-icon name="close"/></button>
                                </div>
                                <div class="flex flex-wrap gap-2" x-show="line.product.variants.length || line.product.units.length">
                                    <select class="input-field !py-1 !text-xs !w-auto" x-show="line.product.variants.length" x-model.number="line.variantId" :class="!line.variantId && '!border-amber-400'" aria-label="Variante">
                                        <option value="">Elige variante…</option>
                                        <template x-for="variant in line.product.variants" :key="variant.id"><option :value="variant.id" x-text="variant.title"></option></template>
                                    </select>
                                    <select class="input-field !py-1 !text-xs !w-auto" x-show="line.product.units.length" x-model.number="line.unitId" aria-label="Presentación">
                                        <option value="">Unidad</option>
                                        <template x-for="unit in line.product.units" :key="unit.id"><option :value="unit.id" x-text="unit.name + ' (' + unit.factor + ')'"></option></template>
                                    </select>
                                </div>
                                <div class="pos-line-row">
                                    <div class="pos-stepper">
                                        <button type="button" @click="line.quantity > 1 ? line.quantity-- : lines.splice(index, 1)" aria-label="Menos">−</button>
                                        <input type="number" min="1" x-model.number="line.quantity" aria-label="Cantidad">
                                        <button type="button" @click="line.quantity++" aria-label="Más">+</button>
                                    </div>
                                    <label class="pos-line-price"><span>S/</span>
                                        <input type="number" step="0.10" min="0" :value="price(line).toFixed(2)"
                                               @change="line.price = $event.target.value === '' ? null : Number($event.target.value)" aria-label="Precio">
                                    </label>
                                    <span class="pos-line-total" x-text="money(price(line) * line.quantity)"></span>
                                </div>
                                <p class="text-[11px] text-amber-300" x-show="shortage(line)" x-text="shortage(line)"></p>
                                <p class="text-[11px] text-emerald-300" x-show="tierLabel(line)" x-text="tierLabel(line)"></p>
                            </div>
                        </template>
                    </div>

                    <div class="space-y-2" x-show="lines.length">
                        <div class="relative">
                            <label class="input-label" for="pos-customer">Cliente</label>
                            <template x-if="customer">
                                <div class="flex items-center justify-between gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2">
                                    <span class="text-sm text-white" x-text="customer.name"></span>
                                    <button type="button" class="text-xs text-white/50 hover:text-red-300" @click="customer = null">Quitar</button>
                                </div>
                            </template>
                            <template x-if="!customer">
                                <div>
                                    <input id="pos-customer" class="input-field" x-model="customerQuery" @input.debounce.300ms="findCustomers()" placeholder="Cliente varios · busca por nombre, celular o DNI" autocomplete="off">
                                    <div class="absolute z-20 left-0 right-0 mt-1 rounded-xl border border-white/10 bg-slate-900 shadow-lg" x-show="customers.length" @click.outside="customers = []">
                                        <template x-for="c in customers" :key="c.id">
                                            <button type="button" class="block w-full text-left px-3 py-2 hover:bg-white/10" @click="pickCustomer(c)">
                                                <span class="text-sm text-white" x-text="c.name"></span> <span class="text-xs text-white/50" x-text="c.detail || ''"></span>
                                            </button>
                                        </template>
                                    </div>
                                    <button type="button" class="text-[11px] text-tribio-cyan underline mt-1" @click="newCustomer = !newCustomer">¿Cliente nuevo? Anota sus datos</button>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-2" x-show="newCustomer" x-cloak>
                                        <input class="input-field !py-1 !text-xs" x-model="walkIn.name" maxlength="120" placeholder="Nombre" aria-label="Nombre del cliente">
                                        <input class="input-field !py-1 !text-xs" x-model="walkIn.phone" maxlength="20" inputmode="tel" placeholder="Celular" aria-label="Celular del cliente">
                                        <input class="input-field !py-1 !text-xs" x-model="walkIn.document" maxlength="11" inputmode="numeric" placeholder="DNI o RUC" aria-label="DNI o RUC del cliente">
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p class="text-[11px] text-sky-300" x-show="quoteNumber" x-text="'Cobrando la proforma ' + quoteNumber + ' con sus precios'"></p>
                        <div class="flex items-center justify-between gap-2" x-show="extras">
                            <label for="pos-list" class="text-xs text-white/60">Lista de precios</label>
                            <select id="pos-list" class="input-field !py-1 !w-auto !text-xs" x-model="priceList">
                                <option value="menor">Menor</option>
                                <template x-for="(label, key) in priceLists" :key="key"><option :value="key" x-text="label"></option></template>
                            </select>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <label for="pos-discount" class="text-xs text-white/60">Descuento (S/)</label>
                            <input id="pos-discount" type="number" step="0.10" min="0" class="input-field !py-1 !w-28 text-right" x-model.number="discount">
                        </div>
                    </div>

                    {{-- Manager approval (modo caja) --}}
                    <div class="rounded-xl border border-amber-300/40 bg-amber-300/10 p-3 space-y-2" x-show="approval" x-cloak role="alertdialog" aria-labelledby="approval-text">
                        <p id="approval-text" class="text-xs text-amber-100" x-text="approval"></p>
                        <div class="flex items-center gap-2">
                            <label for="approval-pin" class="sr-only">PIN de un encargado</label>
                            <input id="approval-pin" x-ref="approvalPin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" class="input-field !py-1 flex-1" placeholder="PIN del encargado"
                                   x-model="approverPin" @keydown.enter.prevent="submit()">
                            <button type="button" class="btn-primary !py-1 text-xs" @click="submit()">Autorizar</button>
                            <button type="button" class="btn-ghost !py-1 text-xs" @click="approval = ''; approverPin = ''">Cancelar</button>
                        </div>
                    </div>

                    {{-- Payment --}}
                    <div class="space-y-2 border-t border-white/10 pt-3" x-ref="payBlock" x-show="paying" x-cloak>
                        <template x-for="(label, method) in methods" :key="method">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-white/70 w-24" x-text="label"></span>
                                <input type="number" step="0.10" min="0" class="input-field !py-1 flex-1 text-right" x-model="pay[method]" :aria-label="'Monto en ' + label">
                                <button type="button" class="btn-ghost !py-0.5 !px-2 text-[11px]" @click="fill(method)">Todo</button>
                            </div>
                        </template>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="bill in [10, 20, 50, 100, 200]" :key="bill">
                                <button type="button" class="btn-ghost !py-0.5 !px-2 text-[11px]" @click="pay.cash = (Number(pay.cash || 0) + bill).toFixed(2)" x-text="'+ S/ ' + bill"></button>
                            </template>
                        </div>
                        <p class="text-sm" :class="remaining() > 0.004 ? 'text-amber-300' : 'text-emerald-300'"
                           x-text="remaining() > 0.004 ? (credit ? 'Queda debiendo: ' : 'Falta: ') + money(remaining()) : 'Vuelto: ' + money(change())"></p>
                        <template x-if="creditEnabled && remaining() > 0.004">
                            <div class="rounded-xl border border-amber-300/30 bg-amber-300/5 p-2 space-y-2">
                                <label class="flex items-center gap-2 text-sm text-white"><input type="checkbox" x-model="credit"> Dejar a crédito (fiado)</label>
                                <div x-show="credit" class="space-y-2">
                                    <p class="text-[11px] text-amber-200" x-show="!customerKnown()">Elige al cliente o anota su nombre y su celular o DNI.</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <label class="text-[11px] text-white/60">Primer pago
                                            <input type="date" class="input-field !py-1 !text-xs" x-model="terms.first_due" :min="firstDue.min"></label>
                                        <label class="text-[11px] text-white/60">Cuotas
                                            <select class="input-field !py-1 !text-xs" x-model.number="terms.installments">
                                                <template x-for="n in [1, 2, 3, 4, 6, 8, 12]" :key="n"><option :value="n" x-text="n"></option></template>
                                            </select></label>
                                        <label class="text-[11px] text-white/60" x-show="terms.installments > 1">Cada
                                            <select class="input-field !py-1 !text-xs" x-model.number="terms.every_days">
                                                <option value="7">Semana</option><option value="15">15 días</option><option value="30">Mes</option>
                                            </select></label>
                                    </div>
                                    <p class="text-[11px] text-white/60" x-text="planText()"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    </div>{{-- /pos-ticket-body --}}

                    <div class="pos-ticket-foot" x-show="lines.length">
                    <div class="pos-total">
                        <span>Total</span>
                        <strong x-text="money(total())"></strong>
                    </div>
                    <p class="pos-error" x-show="error" x-text="error" role="alert"></p>
                    <button type="button" class="btn-primary pos-cobrar" x-show="lines.length && !paying" @click="openPayment()">Cobrar <kbd>F9</kbd></button>
                    <div class="flex items-center gap-2" x-show="extras && lines.length && !paying">
                        <button type="button" class="btn-secondary flex-1 text-xs" :disabled="saving" @click="saveQuote()">📝 Guardar como proforma</button>
                        <label class="text-[11px] text-white/50 flex items-center gap-1">válida
                            <select class="input-field !py-0.5 !w-auto !text-[11px]" x-model.number="validDays"><option value="3">3 días</option><option value="7">7 días</option><option value="15">15 días</option><option value="30">30 días</option></select></label>
                    </div>
                    <div class="flex gap-2" x-show="paying" x-cloak>
                        <button type="button" class="btn-secondary flex-1" @click="paying = false">Volver</button>
                        <button type="button" class="btn-primary flex-[2]" :disabled="saving || !canPay()" :class="(saving || !canPay()) && 'opacity-50 cursor-not-allowed'" @click="submit()"
                                x-text="saving ? 'Guardando…' : 'Confirmar venta'"></button>
                    </div>
                    </div>{{-- /pos-ticket-foot --}}
                </div>
            </template>

            {{-- Quote saved --}}
            <template x-if="savedQuote">
                <div class="pos-result" role="status">
                    <p class="text-4xl" aria-hidden="true">📝</p>
                    <p class="text-white font-bold" x-text="'Proforma ' + savedQuote.number"></p>
                    <p class="text-2xl font-black text-white" x-text="money(savedQuote.total)"></p>
                    <p class="text-xs text-white/60" x-text="'Válida hasta el ' + savedQuote.valid_until + '. No separa stock.'"></p>
                    <div class="grid grid-cols-1 gap-2">
                        <a :href="savedQuote.print_url + '?imprimir=1'" target="_blank" rel="noopener" class="btn-secondary">🖨️ Imprimir proforma</a>
                        <a :href="savedQuote.whatsapp_url" target="_blank" rel="noopener" class="btn-secondary" x-show="savedQuote.whatsapp_url">💬 Enviar por WhatsApp</a>
                        <button type="button" class="btn-primary" @click="reset()">Nueva venta</button>
                    </div>
                </div>
            </template>

            {{-- Sale done --}}
            <template x-if="done">
                <div class="pos-result" role="status">
                    <p class="text-4xl" aria-hidden="true">✅</p>
                    <p class="text-white font-bold" x-text="'Venta ' + done.order_number"></p>
                    <p class="text-2xl font-black text-white" x-text="money(done.total)"></p>
                    <p class="text-lg text-emerald-300" x-show="done.change > 0" x-text="'Vuelto: ' + money(done.change)"></p>
                    <p class="text-sm text-amber-300" x-show="done.owed > 0" x-text="'Queda debiendo ' + money(done.owed) + ' (fiado)'"></p>
                    <p class="text-xs text-amber-200" x-show="done.offline">Guardada sin conexión en este equipo: se registrará sola al volver internet.</p>
                    <div class="grid grid-cols-1 gap-2">
                        <a :href="done.receipt_url + '?imprimir=1'" target="_blank" rel="noopener" class="btn-secondary" x-show="!done.offline">🖨️ Imprimir ticket</a>
                        <button type="button" class="btn-secondary" x-show="done.offline" @click="printProvisional()">🖨️ Ticket provisional</button>
                        <a :href="done.whatsapp_url" target="_blank" rel="noopener" class="btn-secondary" x-show="done.whatsapp_url">💬 Enviar por WhatsApp</a>
                        <button type="button" class="btn-primary" @click="reset()" x-ref="next">Nueva venta (Enter)</button>
                    </div>
                </div>
            </template>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function posRegister(config) {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const newId = () => crypto.randomUUID ? crypto.randomUUID()
            : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c => (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16));
        const blankPay = () => Object.fromEntries(Object.keys(config.methods).map(m => [m, '']));
        return {
            ...config,
            query: '', products: [], loading: false, searchError: '',
            lines: [], discount: 0, customer: null, customerQuery: '', customers: [], newCustomer: false, walkIn: { name: '', phone: '', document: '' },
            paying: false, pay: blankPay(), saving: false, credit: false, terms: { installments: 1, every_days: 30, first_due: config.firstDue.default }, error: '', done: null, uuid: newId(), showCash: false, cartOpen: false,
            priceList: 'menor', validDays: 7, quoteId: null, quoteNumber: null, savedQuote: null,
            approval: '', approverPin: '', online: navigator.onLine, pending: [], showPending: false,
            init() {
                this.search();
                this.setupOffline();
                if (this.quote) this.loadQuote(this.quote);
                this.$watch('done', value => value && this.$nextTick(() => this.$refs.next?.focus()));
                // Below lg the ticket is a sheet: open it to show the result, close it when the sale is emptied.
                this.$watch('done', value => { if (value) this.cartOpen = true; });
                this.$watch('savedQuote', value => { if (value) this.cartOpen = true; });
                // The amounts live below the lines: bring them into view when charging starts.
                this.$watch('paying', value => value && this.$nextTick(() => this.$refs.payBlock?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })));
                this.$watch('lines.length', count => { if (!count && !this.done && !this.savedQuote) this.cartOpen = false; });
                this.$nextTick(() => this.fitLayout());
                setTimeout(() => this.fitLayout(), 300);
                window.addEventListener('resize', () => this.fitLayout());
            },
            // From lg up the two panels fill what is left of the screen, each scrolling on its own.
            fitLayout() {
                const el = this.$refs.layout;
                if (!el) return;
                if (window.innerWidth < 1024) { el.style.removeProperty('--pos-h'); return; }
                const scroller = el.closest('.dash-content');
                const top = el.getBoundingClientRect().top + (scroller ? scroller.scrollTop : 0);
                const bottom = scroller ? parseFloat(getComputedStyle(scroller).paddingBottom) || 0 : 0;
                el.style.setProperty('--pos-h', Math.max(480, window.innerHeight - top - bottom) + 'px');
            },
            money(value) { return 'S/ ' + Number(value || 0).toFixed(2); },
            initials(name) {
                // Letters only ("Bisagra 3" (par)" → BP); a single word gives its first two letters.
                const words = String(name || '').match(/\p{L}+/gu) || [];
                const letters = words.length > 1 ? words[0][0] + words[1][0] : (words[0] || '').slice(0, 2);
                return letters.toUpperCase() || '·';
            },
            badgeStyle(product) {
                const hue = (product.id * 47) % 360;
                return `background:hsl(${hue} 70% 93%);color:hsl(${hue} 45% 30%)`;
            },
            stockClass(product) {
                if (!product.track_stock) return 'is-none';
                if (product.stock <= 0) return 'is-out';
                return product.stock <= 5 ? 'is-low' : '';
            },
            stockLabel(product) {
                if (!product.track_stock) return 'Sin control';
                return product.stock <= 0 ? 'Sin stock' : 'Stock ' + product.stock;
            },
            async search(exact = false) {
                this.loading = true;
                try {
                    const response = await fetch(this.productsUrl + '?q=' + encodeURIComponent(this.query), { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error();
                    const data = await response.json();
                    this.products = data.products;
                    this.searchError = '';
                    return data;
                } catch (e) {
                    // No connection: the copy of the catalog kept on this device.
                    const data = window.TribioPosOffline ? await window.TribioPosOffline.searchLocal(this.offline.storeId, this.query) : null;
                    if (data && data.products.length) { this.products = data.products; this.searchError = ''; return data; }
                    this.searchError = 'Sin conexión y sin catálogo guardado en este equipo.';
                    return null;
                } finally { this.loading = false; }
            },
            setupOffline() {
                if ('serviceWorker' in navigator) navigator.serviceWorker.register('/pos-sw.js', { scope: '/dashboard/caja' }).catch(() => null);
                const ready = () => window.TribioPosOffline ? Promise.resolve() : new Promise(r => window.addEventListener('load', r, { once: true }));
                ready().then(async () => {
                    await this.refreshPending();
                    this.syncNow();
                    try {
                        const response = await fetch(this.offline.catalogUrl, { headers: { 'Accept': 'application/json' } });
                        if (response.ok) await window.TribioPosOffline.setCatalog(this.offline.storeId, (await response.json()).products);
                    } catch (e) { /* offline: keep the last copy */ }
                });
                window.addEventListener('online', () => { this.online = true; this.syncNow(); });
                window.addEventListener('offline', () => { this.online = false; });
                setInterval(() => this.syncNow(), 30000);
            },
            async refreshPending() {
                if (!window.TribioPosOffline) return;
                this.pending = (await window.TribioPosOffline.queue()).filter(i => i.session === this.offline.session || i.error);
            },
            async syncNow() {
                if (!window.TribioPosOffline || !navigator.onLine) return;
                const result = await window.TribioPosOffline.sync(this.offline.tokenUrl);
                if (result.sent) { this.sales += result.sent; }
                await this.refreshPending();
            },
            async discard(item) {
                if (!confirm('¿Descartar esta venta? No quedará registrada.')) return;
                await window.TribioPosOffline.remove(item.uuid);
                await this.refreshPending();
            },
            async queueOffline(body) {
                const item = { uuid: body.uuid, url: this.sellUrl, session: this.offline.session, body: { ...body, offline_at: new Date().toISOString() },
                    offline_at: new Date().toISOString(), total: this.total(), change: this.change(),
                    lines: this.lines.map(l => ({ name: l.product.name, quantity: l.quantity, subtotal: Math.round(this.price(l) * l.quantity * 100) / 100 })) };
                delete item.body.approver_pin;
                await window.TribioPosOffline.enqueue(item);
                this.lastOffline = item;
                await this.refreshPending();
                this.done = { offline: true, order_number: 'sin conexión', total: item.total, change: item.change, owed: 0, whatsapp_url: null, receipt_url: '' };
            },
            printProvisional() {
                if (this.lastOffline) window.TribioPosOffline.printProvisional(this.storeName, this.lastOffline);
            },
            async scan() {
                if (this.done) { this.reset(); return; }
                if (!this.query.trim()) return;
                const data = await this.search();
                if (!data) return;
                const hit = data.exact ? data.products.find(p => p.id === data.exact.product_id) : (data.products.length === 1 ? data.products[0] : null);
                if (!hit) { this.searchError = data.products.length ? 'Toca el producto correcto en la lista.' : 'Ningún producto tiene ese código.'; return; }
                this.add(hit, data.exact?.variant_id || null, data.exact?.unit_id || null);
                this.query = '';
                this.search();
            },
            add(product, variantId = null, unitId = null) {
                if (this.done || this.savedQuote) this.reset();
                if (!variantId && product.variants.length === 1) variantId = product.variants[0].id;
                // The same product, variant and packaging adds up; a product still missing its variant gets its own line.
                const existing = (variantId || !product.variants.length)
                    ? this.lines.find(l => l.product.id === product.id && (l.variantId || null) === variantId && (l.unitId || null) === unitId && l.price === null)
                    : null;
                if (existing) { existing.quantity++; }
                else { this.lines.push({ key: product.id + ':' + Date.now() + ':' + Math.random(), product, variantId, unitId, quantity: 1, price: null }); }
                this.paying = false;
                this.$refs.search.focus();
            },
            unit(line) { return line.product.units.find(u => u.id === line.unitId) || null; },
            baseUnits(productId) {
                return this.lines.filter(l => l.product.id === productId).reduce((sum, l) => sum + l.quantity * (this.unit(l)?.factor || 1), 0);
            },
            listPrice(line) {
                const variant = line.product.variants.find(v => v.id === line.variantId);
                const base = variant ? variant.price : line.product.price;
                let ratio = 1;
                const tier = [...line.product.tiers].reverse().find(t => this.baseUnits(line.product.id) >= t.min_qty);
                if (tier && line.product.price > 0) ratio = Math.min(1, tier.price / line.product.price);
                let unit = base * ratio;
                // Mirrors PriceListService: the list price, scaled like the variant, unless a tier is cheaper.
                const listed = this.priceList !== 'menor' ? line.product.lists?.[this.priceList] : undefined;
                if (listed !== undefined) unit = Math.min(unit, line.product.price > 0 ? base * listed / line.product.price : listed);
                return Math.round(unit * (this.unit(line)?.factor || 1) * 100) / 100;
            },
            pickCustomer(c) {
                this.customer = c; this.customers = []; this.customerQuery = '';
                if (this.extras && !this.quoteId) this.priceList = this.priceLists[c.relationship] ? c.relationship : 'menor';
            },
            loadQuote(quote) {
                this.quoteId = quote.id; this.quoteNumber = quote.number; this.priceList = quote.priceList; this.discount = quote.discount;
                this.lines = quote.lines.map(l => ({ key: 'q' + Math.random(), product: l.product, variantId: l.variantId, unitId: l.unitId, quantity: l.quantity, price: l.price }));
                if (quote.customer) this.customer = quote.customer;
                else if (quote.walkIn && quote.walkIn.name) { this.walkIn = quote.walkIn; this.newCustomer = true; }
                if (quote.missing.length) this.error = 'Ya no se venden: ' + quote.missing.join(', ') + '.';
            },
            body() {
                return {
                    items: this.lines.map(l => ({ product_id: l.product.id, variant_id: l.variantId || null, unit_id: l.unitId || null, quantity: l.quantity, price: l.price })),
                    discount: Number(this.discount) || 0,
                    contact_id: this.customer?.id || null,
                    customer_name: this.customer ? null : (this.walkIn.name || null),
                    customer_phone: this.customer ? null : (this.walkIn.phone || null),
                    customer_document: this.customer ? null : (this.walkIn.document || null),
                    ...(this.extras ? { price_list: this.priceList } : {}),
                };
            },
            async saveQuote() {
                if (this.saving || !this.lines.length) return;
                if (!this.lines.every(l => !l.product.variants.length || l.variantId)) { this.error = 'Elige la variante de cada producto.'; return; }
                this.saving = true; this.error = '';
                try {
                    const response = await fetch(this.quoteUrl, { method: 'POST', body: JSON.stringify({ ...this.body(), valid_days: this.validDays }),
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
                    const data = await response.json().catch(() => ({}));
                    if (response.ok) this.savedQuote = data;
                    else this.error = data.errors ? Object.values(data.errors)[0][0] : 'No se pudo guardar la proforma.';
                } catch (e) { this.error = 'Sin conexión. Inténtalo de nuevo.'; }
                finally { this.saving = false; }
            },
            price(line) { return line.price !== null && line.price !== undefined ? Number(line.price) : this.listPrice(line); },
            tierLabel(line) {
                const tier = [...line.product.tiers].reverse().find(t => this.baseUnits(line.product.id) >= t.min_qty);
                return tier && line.price === null ? 'Precio por mayor desde ' + tier.min_qty + ' unidades' : '';
            },
            shortage(line) {
                if (!line.product.track_stock) return '';
                const variant = line.product.variants.find(v => v.id === line.variantId);
                const stock = variant ? variant.stock : line.product.stock;
                const wanted = variant ? line.quantity * (this.unit(line)?.factor || 1) : this.baseUnits(line.product.id);
                return wanted > stock ? 'Stock: ' + stock + '. Se vende igual y anotamos el faltante.' : '';
            },
            subtotal() { return this.lines.reduce((sum, l) => sum + Math.round(this.price(l) * l.quantity * 100) / 100, 0); },
            total() { return Math.max(0, Math.round((this.subtotal() - Math.min(Number(this.discount) || 0, this.subtotal())) * 100) / 100); },
            paid(except = null) { return Object.entries(this.pay).filter(([m]) => m !== except).reduce((sum, [, v]) => sum + (Number(v) || 0), 0); },
            nonCash() { return Object.entries(this.pay).filter(([m]) => m !== 'cash').reduce((sum, [, v]) => sum + (Number(v) || 0), 0); },
            remaining() { return Math.max(0, Math.round((this.total() - this.paid()) * 100) / 100); },
            change() { return Math.max(0, Math.round(((Number(this.pay.cash) || 0) - Math.max(0, this.total() - this.nonCash())) * 100) / 100); },
            fill(method) { this.pay[method] = Math.max(0, this.total() - this.paid(method)).toFixed(2); },
            customerKnown() { return !!this.customer || (this.walkIn.name.trim() && (this.walkIn.phone.trim() || this.walkIn.document.trim())); },
            onCredit() { return this.credit && this.remaining() > 0.004; },
            planText() {
                const owed = this.remaining(), n = this.terms.installments || 1;
                if (n === 1) return 'Paga ' + this.money(owed) + ' el ' + this.terms.first_due.split('-').reverse().join('/') + '.';
                const every = { 7: 'cada semana', 15: 'cada 15 días', 30: 'cada mes' }[this.terms.every_days];
                return n + ' cuotas de ' + this.money(Math.floor(owed / n * 100) / 100) + ' ' + every + ', desde el ' + this.terms.first_due.split('-').reverse().join('/') + '.';
            },
            canPay() {
                return this.lines.length && this.lines.every(l => !l.product.variants.length || l.variantId)
                    && (this.remaining() <= 0.004 || (this.onCredit() && this.customerKnown() && this.terms.first_due))
                    && this.nonCash() <= this.total() + 0.004;
            },
            openPayment() {
                if (!this.lines.length) return;
                if (!this.lines.every(l => !l.product.variants.length || l.variantId)) { this.error = 'Elige la variante de cada producto.'; return; }
                this.error = '';
                this.paying = true;
                if (!this.paid()) this.pay.cash = this.total().toFixed(2);
            },
            async findCustomers() {
                if (this.customerQuery.trim().length < 2) { this.customers = []; return; }
                try {
                    const response = await fetch(this.customersUrl + '?q=' + encodeURIComponent(this.customerQuery), { headers: { 'Accept': 'application/json' } });
                    this.customers = response.ok ? await response.json() : [];
                } catch (e) { this.customers = []; }
            },
            async submit() {
                if (this.saving || !this.canPay()) return;
                this.saving = true;
                this.error = '';
                const body = {
                    ...this.body(),
                    uuid: this.uuid,
                    quote_id: this.quoteId,
                    payments: Object.entries(this.pay).filter(([, v]) => Number(v) > 0).map(([method, amount]) => ({ method, amount: Number(amount) })),
                    credit: this.onCredit() ? { ...this.terms } : null,
                    approver_pin: this.approverPin || null,
                };
                const restricted = this.lines.some(l => l.price !== null && !this.quoteId) || Number(this.discount) > 0 || this.onCredit();
                try {
                    const response = await fetch(this.sellUrl, { method: 'POST', body: JSON.stringify(body),
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
                    const data = await response.json().catch(() => ({}));
                    if (response.ok) {
                        if (!data.duplicate) { this.sales++; this.salesTotal += data.total; }
                        this.approval = ''; this.approverPin = '';
                        this.done = data;
                    } else if (response.status === 423) {
                        window.location.reload(); // the register locked itself: back to the PIN screen
                    } else if (data.errors && data.errors.approval) {
                        this.approval = data.errors.approval[0];
                        this.$nextTick(() => this.$refs.approvalPin?.focus());
                    } else {
                        this.error = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'No se pudo guardar la venta. Inténtalo de nuevo.');
                        if (data.errors && data.errors.pin) { this.approverPin = ''; }
                    }
                } catch (e) {
                    // No connection. Same UUID later: a sale that did reach the server is never sold twice.
                    if (!window.TribioPosOffline) {
                        this.error = 'Sin conexión. Vuelve a pulsar «Confirmar venta»: no se cobrará dos veces.';
                    } else if (this.kiosk && !this.kiosk.manager && restricted) {
                        this.error = 'Sin conexión no se pueden autorizar precios, descuentos ni fiado. Quita esos cambios o espera la conexión.';
                    } else if (this.onCredit() && !this.customer) {
                        this.error = 'Sin conexión, el fiado solo es para clientes ya registrados.';
                    } else {
                        await this.queueOffline(body);
                    }
                } finally { this.saving = false; }
            },
            reset() {
                Object.assign(this, { lines: [], discount: 0, customer: null, customerQuery: '', customers: [], newCustomer: false,
                    walkIn: { name: '', phone: '', document: '' }, paying: false, pay: blankPay(), error: '', done: null, uuid: newId(),
                    credit: false, terms: { installments: 1, every_days: 30, first_due: this.firstDue.default },
                    priceList: 'menor', quoteId: null, quoteNumber: null, savedQuote: null, approval: '', approverPin: '', cartOpen: false });
                this.$nextTick(() => this.$refs.search?.focus());
            },
        };
    }
</script>
@endpush
