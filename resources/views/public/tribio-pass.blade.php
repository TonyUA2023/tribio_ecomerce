@extends('layouts.public')

@section('title', 'Tribio Pass | Tu cuenta única en Tribio')
@section('meta_description', 'Tribio Pass es tu cuenta única para comprar en cualquier tienda de la plataforma y, si lo deseas, administrar tu propio negocio.')

@section('content')
<section class="relative bg-[#F6F6F6] min-h-screen pt-28 pb-20 lg:pt-36"
         x-data="tribioPassHub({{ $hasStore ? 'true' : 'false' }})" x-init="init()">
    <div class="container-tribio max-w-4xl">

        <div class="text-center mb-10">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-sky-50 text-sky-600 border border-sky-100 mb-4">🪪 Tribio Pass</span>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900">Tu cuenta única en Tribio</h1>
            <p class="text-slate-500 text-sm mt-3 max-w-xl mx-auto">Compra en cualquier tienda de la red y administra tu propio negocio, todo con el mismo Tribio Pass.</p>
        </div>

        @if($errors->has('google'))
        <div class="max-w-md mx-auto mb-6 p-3.5 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-600 text-xs text-center">
            {{ $errors->first('google') }}
        </div>
        @endif

        {{-- Loading --}}
        <template x-if="checking">
            <div class="text-center py-16 text-slate-400 text-sm">Cargando tu Tribio Pass…</div>
        </template>

        {{-- ══════════ GUEST STATE ══════════ --}}
        <template x-if="!checking && !authenticated">
            <div class="max-w-md mx-auto bg-white rounded-3xl border border-slate-100 shadow-sm p-6 sm:p-8">

                <div class="flex bg-slate-50 rounded-xl p-1 mb-6 border border-slate-100">
                    <button type="button" @click="tab = 'login'" :class="tab === 'login' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-400'" class="flex-1 py-2 rounded-lg text-xs font-bold transition-all">Iniciar sesión</button>
                    <button type="button" @click="tab = 'register'" :class="tab === 'register' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-400'" class="flex-1 py-2 rounded-lg text-xs font-bold transition-all">Crear Tribio Pass</button>
                </div>

                @if($googleReady)
                <div x-show="tab === 'login' || (tab === 'register' && regStep === 1)" class="mb-5">
                    <a href="{{ route('auth.google.redirect', ['from' => 'tribio-pass']) }}" class="w-full flex items-center justify-center gap-2.5 py-3 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all">
                        <svg class="w-4 h-4" viewBox="0 0 48 48">
                            <path fill="#FFC107" d="M43.6 20.5h-1.9V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.8-.4-4.5z"/>
                            <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 16.3 3 9.7 7.3 6.3 14.7z"/>
                            <path fill="#4CAF50" d="M24 45c5.5 0 10.4-1.9 14.1-5.1l-6.5-5.5C29.6 36 26.9 37 24 37c-5.2 0-9.6-3.3-11.2-8l-6.6 5C9.6 40.6 16.3 45 24 45z"/>
                            <path fill="#1976D2" d="M43.6 20.5h-1.9V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.6l6.5 5.5c-.5.4 7.4-5.4 7.4-15.6 0-1.4-.1-2.8-.4-4.5z"/>
                        </svg>
                        Continuar con Google
                    </a>
                    <div class="flex items-center gap-3 mt-5">
                        <div class="flex-1 h-px bg-slate-100"></div>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">o</span>
                        <div class="flex-1 h-px bg-slate-100"></div>
                    </div>
                </div>
                @endif

                <template x-if="formError">
                    <div class="p-3 mb-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 text-xs" x-text="formError"></div>
                </template>
                <template x-if="formSuccess">
                    <div class="p-3 mb-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-700 text-xs" x-text="formSuccess"></div>
                </template>

                {{-- LOGIN --}}
                <template x-if="tab === 'login'">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Correo electrónico</label>
                            <input type="email" x-model="login.email" class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Contraseña</label>
                            <input type="password" x-model="login.password" @keydown.enter="doLogin()" class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900">
                        </div>
                        <button type="button" @click="doLogin()" :disabled="busy" class="w-full py-3 mt-2 font-bold bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white text-xs uppercase tracking-wider rounded-xl transition-all">
                            <span x-text="busy ? 'Ingresando...' : 'Ingresar a mi Tribio Pass'"></span>
                        </button>
                    </div>
                </template>

                {{-- REGISTER: step 1 (data) -> step 2 (OTP) --}}
                <template x-if="tab === 'register' && regStep === 1">
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre completo</label>
                            <input type="text" x-model="reg.name" class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Correo electrónico</label>
                            <input type="email" x-model="reg.email" class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Celular / WhatsApp</label>
                            <input type="text" x-model="reg.phone" class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Contraseña</label>
                            <input type="password" x-model="reg.password" class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900">
                        </div>
                        <button type="button" @click="sendOtp()" :disabled="busy" class="w-full py-3 mt-2 font-bold bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white text-xs uppercase tracking-wider rounded-xl transition-all">
                            <span x-text="busy ? 'Enviando...' : 'Enviar código de verificación'"></span>
                        </button>
                        <p class="text-[11px] leading-relaxed text-slate-500">Al crear tu cuenta, confirmas que leíste los <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-sky-700 underline">Términos y Condiciones</a> y la <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-sky-700 underline">Política de Privacidad y Tratamiento de Datos</a>.</p>
                    </div>
                </template>

                <template x-if="tab === 'register' && regStep === 2">
                    <div class="space-y-3">
                        <p class="text-xs text-slate-500">Ingresa el código de 6 dígitos que enviamos a <strong x-text="reg.email"></strong>.</p>
                        <input type="text" x-model="reg.token" maxlength="6" placeholder="000000" class="w-full px-3 py-3 text-center text-lg tracking-[0.5em] rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900 font-bold">
                        <button type="button" @click="verifyOtp()" :disabled="busy" class="w-full py-3 mt-2 font-bold bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white text-xs uppercase tracking-wider rounded-xl transition-all">
                            <span x-text="busy ? 'Verificando...' : 'Confirmar y crear mi Tribio Pass'"></span>
                        </button>
                        <button type="button" @click="regStep = 1" class="w-full py-2 text-slate-400 text-[11px] hover:text-slate-600">← Editar mis datos</button>
                    </div>
                </template>
            </div>
        </template>

        {{-- ══════════ AUTHENTICATED STATE ══════════ --}}
        <template x-if="!checking && authenticated">
            <div>
                <div class="flex items-center justify-between mb-8 bg-white rounded-2xl border border-slate-100 p-4 sm:p-5">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full bg-sky-100 text-sky-600 font-black flex items-center justify-center text-lg" x-text="(user.name || '?').charAt(0).toUpperCase()"></div>
                        <div>
                            <p class="text-slate-900 font-bold text-sm" x-text="user.name"></p>
                            <p class="text-slate-400 text-xs" x-text="user.email"></p>
                        </div>
                    </div>
                    <button type="button" @click="doLogout()" class="text-slate-400 hover:text-red-500 text-xs font-semibold transition-colors">Cerrar sesión</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    {{-- Panel de Negocio --}}
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-7">
                        <span class="text-2xl">🏪</span>
                        <h2 class="text-lg font-extrabold text-slate-900 mt-3">Panel de Negocio</h2>
                        <template x-if="hasStore">
                            <div>
                                <p class="text-slate-500 text-xs mt-2 leading-relaxed">Administra tu tienda, productos y pedidos.</p>
                                <a href="{{ route('dashboard.index') }}" class="btn-primary w-full justify-center mt-5 py-3 text-xs">Ir a mi Panel de Negocio →</a>
                            </div>
                        </template>
                        <template x-if="!hasStore">
                            <div>
                                <p class="text-slate-500 text-xs mt-2 leading-relaxed">Aún no tienes un negocio en Tribio. Crea tu tienda y empieza a vender hoy mismo con el mismo Tribio Pass.</p>
                                <a href="{{ route('home') }}#precios" class="btn-secondary w-full justify-center mt-5 py-3 text-xs">Abrir mi negocio →</a>
                            </div>
                        </template>
                    </div>

                    {{-- Panel de Compras --}}
                    <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-7">
                        <span class="text-2xl">🛍️</span>
                        <h2 class="text-lg font-extrabold text-slate-900 mt-3">Mis Compras</h2>
                        <template x-if="orders.length === 0">
                            <div>
                                <p class="text-slate-500 text-xs mt-2 leading-relaxed">Todavía no tienes pedidos. Explora el directorio de negocios y haz tu primera compra.</p>
                                <a href="{{ route('directory') }}" class="btn-secondary w-full justify-center mt-5 py-3 text-xs">Explorar tiendas →</a>
                            </div>
                        </template>
                        <template x-if="orders.length > 0">
                            <p class="text-slate-500 text-xs mt-2" x-text="orders.length + ' pedido(s) registrados con tu Tribio Pass.'"></p>
                        </template>
                    </div>
                </div>

                {{-- Order list --}}
                <template x-if="notice">
                    <div class="p-3 mb-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-700 text-xs" x-text="notice"></div>
                </template>
                <template x-if="orders.length > 0">
                    <div class="bg-white rounded-3xl border border-slate-100 divide-y divide-slate-100 overflow-hidden">
                        <template x-for="order in orders" :key="order.id">
                            <div class="p-4 sm:p-5">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="text-slate-900 font-bold text-sm truncate" x-text="order.store_name"></p>
                                        <p class="text-slate-400 text-[11px] mt-0.5" x-text="order.order_number + ' · ' + order.created_diff"></p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-slate-900 font-bold text-sm" x-text="order.currency_symbol + ' ' + order.total.toFixed(2)"></p>
                                        <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                                              :style="'background:' + order.status_color + '22; color:' + order.status_color"
                                              x-text="order.status_label"></span>
                                    </div>
                                </div>

                                {{-- Pedido entregado: calificar (o editar) la reseña de cada producto --}}
                                <template x-if="order.status === 'delivered' && order.items.some(i => i.can_review || i.review)">
                                    <ul class="mt-3 space-y-2">
                                        <template x-for="(item, idx) in order.items" :key="idx">
                                            <li class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2">
                                                <div class="min-w-0">
                                                    <p class="text-slate-800 font-semibold text-xs truncate" x-text="item.name"></p>
                                                    <template x-if="item.review">
                                                        <p class="text-[11px] mt-0.5"><span class="text-amber-500" x-text="stars(item.review.rating)"></span> <span class="text-slate-400">Tu calificación</span></p>
                                                    </template>
                                                </div>
                                                <template x-if="item.can_review">
                                                    <button type="button" @click="openReview(item)" class="flex-shrink-0 px-3 py-1.5 rounded-lg bg-sky-500 hover:bg-sky-600 text-white text-[11px] font-bold transition-all">⭐ Calificar</button>
                                                </template>
                                                <template x-if="item.review">
                                                    <button type="button" @click="openReview(item)" class="flex-shrink-0 px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-white text-[11px] font-bold transition-all">Editar</button>
                                                </template>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Calificar una compra --}}
                <div x-show="rate.open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @keydown.escape.window="closeReview()" role="dialog" aria-modal="true" aria-labelledby="rate-title">
                    <div class="absolute inset-0" style="background: rgba(15, 23, 42, 0.5);" @click="closeReview()"></div>
                    <div class="relative w-full max-w-md bg-white rounded-3xl p-6 sm:p-7" style="box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);">
                        <h3 id="rate-title" class="text-lg font-extrabold text-slate-900" x-text="rate.item && rate.item.review ? 'Editar tu reseña' : 'Califica tu compra'"></h3>
                        <p class="text-slate-500 text-xs mt-1 truncate" x-text="rate.item ? rate.item.name : ''"></p>

                        <div class="flex items-center gap-1 mt-4" role="radiogroup" aria-label="Calificación">
                            <template x-for="n in 5" :key="n">
                                <button type="button" @click="rate.rating = n" @mouseenter="rate.hover = n" @mouseleave="rate.hover = 0"
                                        :class="(rate.hover || rate.rating) >= n ? 'text-amber-400' : 'text-slate-300'"
                                        class="text-4xl leading-none transition-colors" role="radio"
                                        :aria-label="n + (n === 1 ? ' estrella' : ' estrellas')" :aria-checked="(rate.rating === n).toString()">★</button>
                            </template>
                        </div>

                        <textarea x-model="rate.comment" rows="4" maxlength="1500" placeholder="¿Qué tal la calidad, el envío, el tamaño…? (opcional)"
                                  class="w-full mt-4 px-3 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900"></textarea>
                        <template x-if="rate.error">
                            <p class="mt-3 text-xs text-red-600" x-text="rate.error"></p>
                        </template>

                        <div class="flex items-center justify-end gap-2 mt-5">
                            <button type="button" @click="closeReview()" class="px-4 py-2.5 rounded-xl text-slate-500 hover:bg-slate-50 text-xs font-bold transition-all">Cancelar</button>
                            <button type="button" @click="submitReview()" :disabled="rate.busy" class="px-5 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-600 disabled:opacity-50 text-white text-xs font-bold uppercase tracking-wider transition-all">
                                <span x-text="rate.busy ? 'Enviando...' : 'Publicar reseña'"></span>
                            </button>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-3">Se publica al instante con tu nombre y la inicial de tu apellido.</p>
                    </div>
                </div>
            </div>
        </template>

    </div>
</section>
@endsection

@push('scripts')
<script>
function tribioPassHub(hasStoreInitial) {
    return {
        checking: true,
        authenticated: false,
        user: {},
        hasStore: hasStoreInitial,
        orders: [],
        tab: 'login',
        regStep: 1,
        busy: false,
        formError: '',
        formSuccess: '',
        login: { email: '', password: '' },
        reg: { name: '', email: '', phone: '', password: '', token: '' },
        csrf: '{{ csrf_token() }}',
        notice: '',
        rate: { open: false, item: null, rating: 0, hover: 0, comment: '', busy: false, error: '' },

        stars(n) { return '★'.repeat(n) + '☆'.repeat(5 - n); },

        // "Calificar mi compra": create or edit the review of one delivered order line.
        openReview(item) {
            this.rate = {
                open: true, item: item, hover: 0, busy: false, error: '',
                rating: item.review ? item.review.rating : 0,
                comment: item.review ? (item.review.comment || '') : '',
            };
        },
        closeReview() { this.rate.open = false; },
        async submitReview() {
            if (!this.rate.rating) { this.rate.error = 'Elige de 1 a 5 estrellas.'; return; }
            this.rate.busy = true; this.rate.error = '';
            try {
                const res = await fetch('{{ route('tribio-pass.reviews.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify({ product_id: this.rate.item.product_id, rating: this.rate.rating, comment: this.rate.comment }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    this.rate.item.review = data.review;
                    this.rate.item.can_review = false;
                    this.rate.open = false;
                    this.notice = data.message;
                    setTimeout(() => { this.notice = ''; }, 6000);
                } else {
                    this.rate.error = data.message || Object.values(data.errors || {}).flat().join(' ') || 'No pudimos guardar tu reseña.';
                }
            } catch (e) {
                this.rate.error = 'Error de conexión con el servidor.';
            } finally {
                this.rate.busy = false;
            }
        },

        async init() {
            try {
                const res = await fetch('/customer/current', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.authenticated && data.user) {
                    this.authenticated = true;
                    this.user = data.user;
                    this.hasStore = !!data.user.has_store;
                    this.loadOrders();
                }
            } catch (e) { /* keep guest state on error */ }
            this.checking = false;
        },

        async loadOrders() {
            try {
                const res = await fetch('/customer/orders', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) this.orders = data.orders || [];
            } catch (e) { /* non-fatal */ }
        },

        async doLogin() {
            this.formError = ''; this.busy = true;
            try {
                const res = await fetch('/customer/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify(this.login),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.authenticated = true;
                    this.user = data.user;
                    this.hasStore = !!data.user.has_store;
                    this.loadOrders();
                } else {
                    this.formError = data.message || 'Correo o contraseña incorrectos.';
                }
            } catch (e) {
                this.formError = 'Error de conexión con el servidor.';
            } finally {
                this.busy = false;
            }
        },

        async sendOtp() {
            this.formError = ''; this.busy = true;
            try {
                const res = await fetch('/customer/register/send-otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify(this.reg),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.regStep = 2;
                    this.formSuccess = data.message;
                } else {
                    this.formError = data.message || Object.values(data.errors || {}).flat().join(' ') || 'No se pudo enviar el código.';
                }
            } catch (e) {
                this.formError = 'Error de conexión con el servidor.';
            } finally {
                this.busy = false;
            }
        },

        async verifyOtp() {
            this.formError = ''; this.busy = true;
            try {
                const res = await fetch('/customer/register/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify(this.reg),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.authenticated = true;
                    this.user = data.user;
                    this.hasStore = false;
                    this.loadOrders();
                } else {
                    this.formError = data.message || 'Código incorrecto o expirado.';
                }
            } catch (e) {
                this.formError = 'Error de conexión con el servidor.';
            } finally {
                this.busy = false;
            }
        },

        async doLogout() {
            try {
                await fetch('/customer/logout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                });
            } catch (e) { /* ignore */ }
            this.authenticated = false;
            this.user = {};
            this.orders = [];
            this.tab = 'login';
        },
    };
}
</script>
@endpush
