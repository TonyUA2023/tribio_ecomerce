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
                <template x-if="orders.length > 0">
                    <div class="bg-white rounded-3xl border border-slate-100 divide-y divide-slate-100 overflow-hidden">
                        <template x-for="order in orders" :key="order.id">
                            <div class="p-4 sm:p-5 flex items-center justify-between gap-4">
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
                        </template>
                    </div>
                </template>
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
