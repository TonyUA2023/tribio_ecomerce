@extends('layouts.simple')
@section('title', 'Vender')

@unless($ready)
    @section('content')
    <div class="sm-notice"><strong>⏳ Estamos activando este módulo</strong><p>Falta una actualización en el servidor de Tribio. Apenas esté lista podrás vender desde aquí.</p></div>
    @endsection
@else

@section('top_right')
    <a href="{{ route('dashboard.simple.today') }}" class="sm-today" x-data x-on:sm-today.window="$el.querySelector('[data-total]').textContent = $event.detail.total; $el.querySelector('[data-count]').textContent = $event.detail.count" aria-label="Cómo voy hoy">
        <span>Hoy</span><strong data-total>S/ {{ number_format($today['total'], 2) }}</strong><small data-count>{{ $today['count'] }} {{ $today['count'] === 1 ? 'venta' : 'ventas' }}</small>
    </a>
@endsection

@section('content')
<div x-data="simpleSell(@js($products), @js(route('dashboard.simple.sell')), @js($today), 'tribio:simple:q:{{ $store->id }}', @js($more ? route('dashboard.simple.search') : null))" x-init="start()" x-effect="document.body.classList.toggle('sm-lock', !!sheet)" class="sm-sell">

    <div class="sm-search">
        <x-dashboard-icon name="search"/>
        <label for="sm-q" class="sr-only">Buscar producto</label>
        <input id="sm-q" type="search" name="simple_buscar" x-model="q" @input.debounce.250ms="remoteSearch()" placeholder="Buscar producto…" autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" data-1p-ignore="true" data-lpignore="true">
    </div>

    <div class="sm-offline" x-show="queue.length" x-cloak role="status">
        <span x-text="queue.length + (queue.length === 1 ? ' venta guardada sin internet' : ' ventas guardadas sin internet')"></span>
        <button type="button" @click="flush()" :disabled="flushing">Enviar ahora</button>
    </div>

    <template x-if="!products.length">
        <div class="sm-empty">
            <strong>Aún no tienes productos</strong>
            <p>Puedes cobrar ya con el botón <b>Monto</b>, o cargar tus productos en un minuto para venderlos con un toque.</p>
            <a href="{{ route('dashboard.productos.rapido') }}" class="btn-primary">Agregar productos</a>
        </div>
    </template>

    <div class="sm-grid" x-show="products.length" role="list">
        <template x-for="p in visible()" :key="p.id">
            <button type="button" role="listitem" class="sm-tile" :class="{ 'is-in': qtyOf(p.id) > 0 }" @click="add(p)" :aria-label="p.name + ', ' + money(p.price)">
                <span class="sm-tile-name" x-text="p.name"></span>
                <span class="sm-tile-foot"><strong x-text="money(p.price)"></strong><small x-show="p.stock !== null && p.stock <= 0">Sin stock</small></span>
                <span class="sm-badge" x-show="qtyOf(p.id) > 0" x-text="qtyOf(p.id)" aria-hidden="true"></span>
            </button>
        </template>
    </div>
    <p class="sm-none" x-show="products.length && !visible().length" x-cloak>Ningún producto coincide. Usa <b>Monto</b> para cobrar igual.</p>
    <p class="sm-more" x-show="matches().length > visible().length" x-cloak>Mostrando los primeros <span x-text="visible().length"></span>. Escribe en el buscador para ver otros.</p>

    {{-- Barra de cobro --}}
    <div class="sm-bar" x-cloak>
        <button type="button" class="sm-bar-amount" @click="openAmount()"><span aria-hidden="true">＋</span> Monto</button>
        <button type="button" class="sm-bar-cart" x-show="count()" @click="sheet = 'cart'" :aria-label="'Ver el pedido, ' + count() + ' artículos'"><span x-text="count()"></span><small>ver</small></button>
        <button type="button" class="sm-bar-pay" :disabled="!count()" @click="sheet = 'pay'"><span x-text="count() ? 'Cobrar' : 'Toca un producto'"></span><strong x-show="count()" x-text="money(total())"></strong></button>
    </div>

    <div class="sm-scrim" x-show="sheet" x-cloak x-transition.opacity @click="close()" aria-hidden="true"></div>

    {{-- Pedido --}}
    <section class="sm-sheet" x-show="sheet === 'cart'" x-cloak x-transition:enter="sm-rise" x-transition:leave="sm-drop" role="dialog" aria-modal="true" aria-label="Pedido">
        <header><strong>Pedido</strong><button type="button" class="sm-link" @click="clear()">Vaciar</button><button type="button" class="sm-icon-btn" @click="close()" aria-label="Cerrar">✕</button></header>
        <ul class="sm-lines">
            <template x-for="line in cart" :key="line.key">
                <li>
                    <span class="sm-line-name"><strong x-text="line.name"></strong><small x-text="money(line.price) + ' c/u'"></small></span>
                    <span class="sm-stepper">
                        <button type="button" @click="step(line, -1)" aria-label="Quitar uno">−</button>
                        <b x-text="line.qty"></b>
                        <button type="button" @click="step(line, 1)" aria-label="Agregar uno">＋</button>
                    </span>
                    <span class="sm-line-total" x-text="money(line.price * line.qty)"></span>
                </li>
            </template>
        </ul>
        <button type="button" class="sm-primary" @click="sheet = 'pay'" :disabled="!count()">Cobrar <span x-text="money(total())"></span></button>
    </section>

    {{-- Monto: cobrar sin buscar producto --}}
    <section class="sm-sheet" x-show="sheet === 'amount'" x-cloak x-transition:enter="sm-rise" x-transition:leave="sm-drop" role="dialog" aria-modal="true" aria-label="Cobrar por monto">
        <header><strong>Cobrar por monto</strong><button type="button" class="sm-icon-btn" @click="close()" aria-label="Cerrar">✕</button></header>
        <div class="sm-display" aria-live="polite"><small>S/</small><strong x-text="keys || '0'"></strong></div>
        <label class="sr-only" for="sm-note">¿Qué es? (opcional)</label>
        <input id="sm-note" type="text" name="simple_detalle" x-model="note" maxlength="60" class="sm-detail" placeholder="¿Qué es? (opcional) · ej. Arroz 1/2 kg" autocomplete="off" data-1p-ignore="true" data-lpignore="true">
        <div class="sm-keypad">
            <template x-for="k in ['1','2','3','4','5','6','7','8','9','.','0','⌫']" :key="k">
                <button type="button" @click="press(k)" :aria-label="k === '⌫' ? 'Borrar' : k" x-text="k"></button>
            </template>
        </div>
        <div class="sm-two">
            <button type="button" class="sm-secondary" @click="addFree(false)" :disabled="!amount()">Agregar al pedido</button>
            <button type="button" class="sm-primary" @click="addFree(true)" :disabled="!amount()">Cobrar <span x-text="money(amount())"></span></button>
        </div>
    </section>

    {{-- Cobro: un toque y listo --}}
    <section class="sm-sheet" x-show="sheet === 'pay'" x-cloak x-transition:enter="sm-rise" x-transition:leave="sm-drop" role="dialog" aria-modal="true" aria-label="Cobrar">
        <header><strong>¿Cómo te pagan?</strong><button type="button" class="sm-icon-btn" @click="close()" aria-label="Cerrar">✕</button></header>
        <div class="sm-due"><small>Total a cobrar</small><strong x-text="money(total())"></strong></div>

        <p class="sm-label">Efectivo · toca lo que te dan</p>
        <div class="sm-cash">
            <button type="button" class="is-exact" @click="pay('cash', total())" :disabled="busy">Justo</button>
            <template x-for="bill in bills()" :key="bill">
                <button type="button" @click="pay('cash', bill)" :disabled="busy" x-text="'S/ ' + bill"></button>
            </template>
            <button type="button" @click="other = !other" :aria-expanded="other">Otro</button>
        </div>
        <form class="sm-other" x-show="other" x-cloak @submit.prevent="pay('cash', Number(given))">
            <label class="sr-only" for="sm-given">Monto que te dan</label>
            <input id="sm-given" type="number" inputmode="decimal" step="0.01" min="0" name="simple_recibido" x-model="given" placeholder="Monto que te dan" autocomplete="off">
            <button type="submit" class="sm-primary" :disabled="busy || !(Number(given) >= total())">Cobrar</button>
        </form>

        <p class="sm-label">Billetera digital</p>
        <div class="sm-wallets">
            <button type="button" class="is-yape" @click="pay('yape')" :disabled="busy">Yape</button>
            <button type="button" class="is-plin" @click="pay('plin')" :disabled="busy">Plin</button>
        </div>
        <p class="sm-error" x-show="error" x-cloak role="alert" x-text="error"></p>
    </section>

    {{-- Listo --}}
    <section class="sm-done" x-show="sheet === 'done'" x-cloak x-transition.opacity role="status" aria-live="polite" @click="newSale()">
        <div class="sm-done-mark" aria-hidden="true">✓</div>
        <h2 x-text="done.offline ? 'Guardada' : '¡Listo!'"></h2>
        <p class="sm-done-total" x-text="money(done.total)"></p>
        <div class="sm-change" x-show="done.change > 0"><small>Dale de vuelto</small><strong x-text="money(done.change)"></strong></div>
        <p class="sm-done-note" x-show="done.offline">Sin internet: se enviará sola cuando vuelva la señal.</p>
        <button type="button" class="sm-primary" @click.stop="newSale()">Nueva venta</button>
    </section>
</div>

<script>
function simpleSell(products, url, today, stateKey, searchUrl) {
    const storeKey = stateKey.replace(':q:', ':queue:');
    const read = () => { try { return JSON.parse(localStorage.getItem(storeKey) || '[]'); } catch (e) { return []; } };
    const write = list => { try { localStorage.setItem(storeKey, JSON.stringify(list)); } catch (e) {} };
    const uid = () => (crypto.randomUUID ? crypto.randomUUID() : 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10));
    const plain = text => String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    let seq = 0;
    return {
        products, q: '', remote: [], remoteSeq: 0, remoteCtrl: null, cart: [], sheet: null, keys: '', note: '', other: false, given: '', busy: false, error: '',
        done: { total: 0, change: 0, offline: false }, saleId: null, queue: [], flushing: false, today,
        start() {
            this.queue = read();
            window.addEventListener('online', () => this.flush());
            this.flush();
            this.announce();
        },
        // ── catalogo
        matches() {
            const words = plain(this.q).split(/\s+/).filter(Boolean);
            if (!words.length) return this.products;
            const local = this.products.filter(p => { const n = plain(p.name); return words.every(w => n.includes(w)); });
            // More products than the screen carries: the ones found on the server join the list (no repeats).
            if (!this.remote.length) return local;
            const seen = new Set(local.map(p => p.id));
            return local.concat(this.remote.filter(p => !seen.has(p.id)));
        },
        // Only the answer to the last thing typed is used.
        async remoteSearch() {
            const text = this.q.trim();
            const seq = ++this.remoteSeq;
            this.remoteCtrl?.abort();
            if (!searchUrl || text.length < 2) { this.remote = []; return; }
            this.remoteCtrl = new AbortController();
            try {
                const res = await fetch(searchUrl + '?q=' + encodeURIComponent(text), { headers: { Accept: 'application/json' }, signal: this.remoteCtrl.signal });
                if (res.ok && seq === this.remoteSeq) this.remote = await res.json();
            } catch (e) { /* offline or superseded: the products on the screen still answer */ }
        },
        visible() { return this.matches().slice(0, 120); },
        // ── pedido
        qtyOf(id) { return this.cart.filter(l => l.id === id).reduce((n, l) => n + l.qty, 0); },
        add(p) {
            const line = this.cart.find(l => l.id === p.id);
            line ? line.qty++ : this.cart.push({ key: ++seq, id: p.id, name: p.name, price: p.price, qty: 1 });
            navigator.vibrate?.(8);
        },
        step(line, by) {
            line.qty += by;
            if (line.qty < 1) this.cart = this.cart.filter(l => l !== line);
            if (!this.cart.length) this.sheet = null;
        },
        clear() { this.cart = []; this.sheet = null; },
        count() { return this.cart.reduce((n, l) => n + l.qty, 0); },
        total() { return Math.round(this.cart.reduce((sum, l) => sum + l.price * l.qty, 0) * 100) / 100; },
        money(value) { return 'S/ ' + Number(value || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        close() { if (!this.busy) { this.sheet = null; this.error = ''; } },
        // ── monto libre
        openAmount() { this.keys = ''; this.note = ''; this.sheet = 'amount'; },
        press(k) {
            if (k === '⌫') { this.keys = this.keys.slice(0, -1); return; }
            if (k === '.') { if (this.keys.includes('.')) return; this.keys = (this.keys || '0') + '.'; return; }
            if (this.keys.includes('.') && this.keys.split('.')[1].length >= 2) return;
            if (this.keys.replace('.', '').length >= 7) return;
            this.keys = this.keys === '0' ? k : this.keys + k;
        },
        amount() { return Math.round((parseFloat(this.keys) || 0) * 100) / 100; },
        addFree(payNow) {
            if (!this.amount()) return;
            this.cart.push({ key: ++seq, id: null, free: true, name: this.note.trim() || 'Venta por monto', price: this.amount(), qty: 1 });
            this.sheet = payNow ? 'pay' : null;
        },
        // ── cobro
        bills() { const t = this.total(); return [10, 20, 50, 100, 200].filter(b => b > t).slice(0, 3); },
        payload(method, tendered) {
            return {
                uuid: this.saleId ??= uid(), method, tendered: method === 'cash' ? tendered : null,
                items: this.cart.map(l => l.free ? { free: true, name: l.name, price: l.price, quantity: l.qty } : { product_id: l.id, quantity: l.qty }),
            };
        },
        async pay(method, tendered = null) {
            if (this.busy || !this.count()) return;
            if (method === 'cash' && !(tendered >= this.total())) { this.error = 'Lo que te dan no alcanza para el total.'; return; }
            this.busy = true; this.error = '';
            const body = this.payload(method, tendered);
            try {
                const res = await this.send(body);
                if (res.status === 422) { this.error = (await res.json().catch(() => ({}))).message || 'No pudimos registrar la venta. Revisa el pedido.'; this.saleId = null; return; }
                if (!res.ok) throw new Error('http ' + res.status);
                const data = await res.json();
                this.finish({ total: data.total, change: data.change, offline: false });
                this.today = data.today; this.announce();
            } catch (e) {
                // No signal: the sale is kept on the phone (same uuid, real time) and goes up when it can.
                this.queue.push({ ...body, offline_at: new Date().toISOString() }); write(this.queue);
                this.today = { count: this.today.count + 1, total: Math.round((this.today.total + this.total()) * 100) / 100 }; this.announce();
                this.finish({ total: this.total(), change: method === 'cash' ? Math.round((tendered - this.total()) * 100) / 100 : 0, offline: true });
            } finally { this.busy = false; }
        },
        send(body) {
            return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(body) });
        },
        finish(info) {
            this.done = info; this.cart = []; this.saleId = null; this.other = false; this.given = ''; this.sheet = 'done';
            navigator.vibrate?.(30);
            if (!info.change) setTimeout(() => { if (this.sheet === 'done') this.newSale(); }, 1400);
        },
        newSale() { this.sheet = null; this.q = ''; },
        // ── sin conexión
        async flush() {
            if (this.flushing || !this.queue.length) return;
            this.flushing = true;
            try {
                while (this.queue.length) {
                    const res = await this.send(this.queue[0]);
                    if (res.ok || res.status === 422) {
                        if (res.ok) { const data = await res.json(); this.today = data.today; this.announce(); }
                        this.queue.shift(); write(this.queue);
                    } else break;
                }
            } catch (e) { /* still offline */ } finally { this.flushing = false; }
        },
        announce() { window.dispatchEvent(new CustomEvent('sm-today', { detail: { total: this.money(this.today.total), count: this.today.count + (this.today.count === 1 ? ' venta' : ' ventas') } })); },
    };
}
</script>
@endsection
@endunless
