{{-- Modal Universal de Cuenta de Comprador / Cliente Tribio (Tribio Pass) — compartido por las 5 plantillas --}}
@php
    $payAccent = $payAccent ?? (isset($storefrontTheme) ? $storefrontTheme->color('primary') : ($store->accent_color ?: config("storefront.templates.{$store->template_name}.default_accent", '#1A1A1A')));
    $paySecondary = $paySecondary ?? (isset($storefrontTheme) ? $storefrontTheme->color('primary-200') : ($store->secondary_color ?: config("storefront.templates.{$store->template_name}.default_secondary", '#C8A68B')));
    // Computed locally (not passed from the controller) since this component is
    // @include'd from many different StoreController view methods — self-contained
    // avoids threading googleReady through every one of them.
    $googleReady = filled(config('services.google.client_id'));
@endphp
{{-- Hidden by the ONE style attribute: a second style="" on the same tag is dropped by the browser, which made this panel flash open until Alpine loaded. --}}
<div id="tribioCustomerModal"
     style="display: none; --pay-accent: {{ $payAccent }}; --pay-accent-soft: {{ $paySecondary }};"
     x-data="{
        isOpen: false,
        activeTab: 'orders',
        isLoggedIn: false,
        user: null,
        addresses: [],
        orders: [],
        loading: false,
        trackLoading: false,
        trackResult: null,
        trackError: '',
        errorMessage: '',
        successMessage: '',
        loginData: { email: '', password: '' },
        registerData: { name: '', email: '', phone: '', password: '', address: '', city: '', state: '', country: '{{ \App\Helpers\CurrencyHelper::currentCountry() }}', type: 'casa' },
        trackData: { order_number: '', email: '' },
        showAddressForm: false,
        newAddress: { id: null, type: 'casa', address: '', city: '', state: '', country: '{{ \App\Helpers\CurrencyHelper::currentCountry() }}', zipcode: '', reference: '', phone: '', is_default: false },
        fromCheckout: false,
        countriesList: [],
        statesList: [],
        addressSuggestions: [],
        addressSearchTimer: null,
        verifyStep: false,
        otpToken: '',

        init() {
            this.checkSession();
            this.fetchCountries();
            window.addEventListener('open-customer-modal', (e) => {
                this.isOpen = true;
                if (e.detail && e.detail.tab) {
                    this.activeTab = e.detail.tab;
                } else if (!this.isLoggedIn) {
                    this.activeTab = 'login';
                }
                if (e.detail && e.detail.email) { this.loginData.email = e.detail.email; this.registerData.email = e.detail.email; }
                if (e.detail && e.detail.name) this.registerData.name = e.detail.name;
                if (e.detail && e.detail.phone) this.registerData.phone = e.detail.phone;
                if (e.detail && e.detail.address) this.registerData.address = e.detail.address;
                if (e.detail && e.detail.city) this.registerData.city = e.detail.city;
                if (e.detail && e.detail.state) this.registerData.state = e.detail.state;
                if (e.detail && e.detail.country) this.registerData.country = e.detail.country;
                if (e.detail && e.detail.fromCheckout) this.fromCheckout = true;
                if (this.isLoggedIn) { this.loadOrders(); this.loadAddresses(); }
            });
            window.openCustomerModal = (tab = null, extra = {}) => {
                window.dispatchEvent(new CustomEvent('open-customer-modal', { detail: { tab, ...extra } }));
            };
            if (new URLSearchParams(window.location.search).get('tribio_pass_login') === '1') {
                window.openCustomerModal();
            }
        },

        async checkSession() {
            try {
                const res = await fetch('/customer/current', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.authenticated && data.user) {
                    this.isLoggedIn = true;
                    this.user = data.user;
                    this.addresses = data.addresses || [];
                    window.dispatchEvent(new CustomEvent('customer-authenticated', { detail: data }));
                } else {
                    this.isLoggedIn = false; this.user = null; this.addresses = [];
                }
            } catch (e) { console.error('Error checking customer session:', e); }
        },

        async doLogin() {
            this.errorMessage = ''; this.successMessage = ''; this.loading = true;
            try {
                const res = await fetch('/customer/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.loginData)
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.isLoggedIn = true; this.user = data.user; this.addresses = data.addresses || [];
                    this.activeTab = 'orders'; this.successMessage = data.message;
                    window.dispatchEvent(new CustomEvent('customer-authenticated', { detail: data }));
                    this.loadOrders();
                    if (this.fromCheckout) {
                        setTimeout(() => {
                            this.isOpen = false; this.fromCheckout = false;
                            window.dispatchEvent(new CustomEvent('open-cart-drawer'));
                        }, 750);
                    }
                } else {
                    this.errorMessage = data.message || 'Error al iniciar sesión.';
                }
            } catch (e) { this.errorMessage = 'Error de conexión con el servidor.'; }
            finally { this.loading = false; }
        },

        async fetchCountries() {
            try {
                const res = await fetch('https://countriesnow.space/api/v0.1/countries/states');
                const data = await res.json();
                if (!data.error) { this.countriesList = data.data; this.updateStates(this.registerData.country); }
            } catch (e) {}
        },
        updateStates(countryIso2) {
            const country = this.countriesList.find(c => c.iso2 === countryIso2 || c.name === countryIso2);
            this.statesList = country ? country.states : [];
        },
        searchAddress(query) {
            if (!query || query.length < 4) { this.addressSuggestions = []; return; }
            clearTimeout(this.addressSearchTimer);
            this.addressSearchTimer = setTimeout(async () => {
                try {
                    const countryParam = this.registerData.country ? `&countrycodes=${this.registerData.country}` : '';
                    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}${countryParam}&addressdetails=1&limit=5`);
                    this.addressSuggestions = await res.json();
                } catch (e) {}
            }, 500);
        },
        selectAddress(item) {
            this.registerData.address = item.display_name;
            if (item.address) {
                if (item.address.city || item.address.town || item.address.village) this.registerData.city = item.address.city || item.address.town || item.address.village;
                if (item.address.state) this.registerData.state = item.address.state;
            }
            this.addressSuggestions = [];
        },
        async doRegister() {
            this.errorMessage = ''; this.successMessage = ''; this.loading = true;
            try {
                const res = await fetch('/customer/register/send-otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.registerData)
                });
                const data = await res.json();
                if (res.ok && data.success) { this.verifyStep = true; this.successMessage = data.message; }
                else { this.errorMessage = data.message || 'Error al procesar el registro.'; }
            } catch (e) { this.errorMessage = 'Error de conexión con el servidor.'; }
            finally { this.loading = false; }
        },
        async verifyOtp() {
            this.errorMessage = ''; this.successMessage = ''; this.loading = true;
            try {
                const payload = { ...this.registerData, token: this.otpToken };
                const res = await fetch('/customer/register/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.verifyStep = false; this.otpToken = '';
                    this.isLoggedIn = true; this.user = data.user; this.addresses = data.addresses || [];
                    this.activeTab = 'orders';
                    this.successMessage = '¡Felicidades, tu cuenta fue creada exitosamente!';
                    window.dispatchEvent(new CustomEvent('customer-authenticated', { detail: data }));
                    this.loadOrders();
                    if (this.fromCheckout) {
                        setTimeout(() => {
                            this.isOpen = false; this.fromCheckout = false;
                            window.dispatchEvent(new CustomEvent('open-cart-drawer'));
                        }, 750);
                    }
                } else {
                    this.errorMessage = data.message || 'Error al validar el código.';
                }
            } catch (e) { this.errorMessage = 'Error de conexión con el servidor.'; }
            finally { this.loading = false; }
        },

        async doLogout() {
            try {
                await fetch('/customer/logout', { method: 'POST', headers: { 'Accept': 'application/json' } });
                this.isLoggedIn = false; this.user = null; this.orders = []; this.addresses = []; this.activeTab = 'login';
                window.dispatchEvent(new CustomEvent('customer-logged-out'));
            } catch (e) { console.error(e); }
        },

        async loadOrders() {
            if (!this.isLoggedIn) return;
            this.loading = true;
            try {
                const res = await fetch('/customer/orders', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) this.orders = data.orders || [];
            } catch (e) { console.error(e); }
            finally { this.loading = false; }
        },

        async loadAddresses() {
            if (!this.isLoggedIn) return;
            try {
                const res = await fetch('/customer/addresses', { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) this.addresses = data.addresses || [];
            } catch (e) { console.error(e); }
        },

        async saveAddress() {
            if (!this.newAddress.address) { this.errorMessage = 'Por favor ingresa la dirección.'; return; }
            this.loading = true;
            try {
                const res = await fetch('/customer/addresses', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.newAddress)
                });
                const data = await res.json();
                if (data.success) {
                    this.addresses = data.addresses || [];
                    this.showAddressForm = false;
                    this.newAddress = { id: null, type: 'casa', address: '', city: '', state: '', country: 'PE', zipcode: '', reference: '', phone: '', is_default: false };
                    window.dispatchEvent(new CustomEvent('customer-authenticated', { detail: { user: this.user, addresses: this.addresses } }));
                } else {
                    this.errorMessage = data.message || 'Error al guardar la dirección';
                }
            } catch (e) { this.errorMessage = 'Error al guardar la dirección.'; }
            finally { this.loading = false; }
        },

        async deleteAddress(id) {
            if (!confirm('¿Deseas eliminar esta dirección?')) return;
            try {
                const res = await fetch(`/customer/addresses/${id}`, { method: 'DELETE', headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.success) this.addresses = data.addresses || [];
            } catch (e) { console.error(e); }
        },

        async doTrack() {
            if (!this.trackData.order_number) return;
            this.trackLoading = true; this.trackError = ''; this.trackResult = null;
            try {
                const res = await fetch('/customer/track-order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.trackData)
                });
                const data = await res.json();
                if (res.ok && data.success) this.trackResult = data.order;
                else this.trackError = data.message || 'No se encontró información del pedido.';
            } catch (e) { this.trackError = 'Error de conexión al consultar el pedido.'; }
            finally { this.trackLoading = false; }
        }
     }"
     x-show="isOpen"
     class="fixed inset-0 z-[1000] flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
     aria-modal="true"
     role="dialog">

    <div class="fixed inset-0 bg-[var(--pay-text)]/60 backdrop-blur-sm transition-opacity" @click="isOpen = false"></div>

    <div class="relative bg-[var(--pay-surface)] rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden border border-[var(--pay-border)] z-10"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">

        <div class="px-6 py-5 border-b border-[var(--pay-border)] flex items-center justify-between bg-[var(--pay-surface-muted)]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl text-white flex items-center justify-center font-black text-sm shadow-md" style="background: var(--pay-accent);">
                    <span x-show="!isLoggedIn">T</span>
                    <span x-show="isLoggedIn" x-text="user ? user.name.charAt(0).toUpperCase() : 'U'"></span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-[var(--pay-text)] text-base sm:text-lg" x-text="isLoggedIn ? user.name : 'Portal de Comprador'"></h2>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 tracking-wider">Tribio Pass</span>
                    </div>
                    <p class="text-xs text-[var(--pay-text-muted)]" x-text="isLoggedIn ? user.email : 'Tu cuenta universal para compras y pedidos'"></p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <template x-if="isLoggedIn">
                    <button @click="doLogout" title="Cerrar Sesión" class="text-xs font-semibold text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-xl transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span class="hidden sm:inline">Salir</span>
                    </button>
                </template>
                <button @click="isOpen = false" class="w-8 h-8 rounded-full bg-[var(--pay-border)] hover:bg-stone-300 text-[var(--pay-text-muted)] hover:text-[var(--pay-text)] flex items-center justify-center transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div class="px-6 pt-3 pb-2 border-b border-[var(--pay-border)] flex gap-2 overflow-x-auto text-xs font-bold scrollbar-none">
            <template x-if="!isLoggedIn">
                <div class="flex gap-2 w-full">
                    <button @click="activeTab = 'login'; errorMessage = ''; successMessage = ''"
                            :style="activeTab === 'login' ? 'background: var(--pay-accent); color: #fff;' : ''"
                            :class="activeTab !== 'login' ? 'bg-[var(--pay-surface-muted)] text-[var(--pay-text-muted)] hover:bg-stone-200' : 'shadow-sm'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 11-12 0 6 6 0 0112 0zM3 21a9 9 0 0118 0"/></svg>
                        Iniciar Sesión
                    </button>
                    <button @click="activeTab = 'register'; errorMessage = ''; successMessage = ''"
                            :style="activeTab === 'register' ? 'background: var(--pay-accent); color: #fff;' : ''"
                            :class="activeTab !== 'register' ? 'bg-[var(--pay-surface-muted)] text-[var(--pay-text-muted)] hover:bg-stone-200' : 'shadow-sm'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-1a4 4 0 11-8 0 4 4 0 018 0zM2 21a6 6 0 0112 0"/></svg>
                        Crear Cuenta
                    </button>
                    <button @click="activeTab = 'track'; errorMessage = ''; successMessage = ''"
                            :style="activeTab === 'track' ? 'background: var(--pay-accent); color: #fff;' : ''"
                            :class="activeTab !== 'track' ? 'bg-[var(--pay-surface-muted)] text-[var(--pay-text-muted)] hover:bg-stone-200' : 'shadow-sm'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Rastrear Pedido
                    </button>
                </div>
            </template>

            <template x-if="isLoggedIn">
                <div class="flex gap-2 w-full">
                    <button @click="activeTab = 'orders'; loadOrders();"
                            :style="activeTab === 'orders' ? 'background: var(--pay-accent); color: #fff;' : ''"
                            :class="activeTab !== 'orders' ? 'bg-[var(--pay-surface-muted)] text-[var(--pay-text-muted)] hover:bg-stone-200' : 'shadow-sm'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Mis Pedidos
                        <span x-show="orders.length > 0" class="px-1.5 py-0.2 text-[10px] rounded-full" :class="activeTab === 'orders' ? 'bg-white/25 text-white' : 'bg-stone-200 text-stone-700'" x-text="orders.length"></span>
                    </button>
                    <button @click="activeTab = 'addresses'; loadAddresses();"
                            :style="activeTab === 'addresses' ? 'background: var(--pay-accent); color: #fff;' : ''"
                            :class="activeTab !== 'addresses' ? 'bg-[var(--pay-surface-muted)] text-[var(--pay-text-muted)] hover:bg-stone-200' : 'shadow-sm'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Mis Direcciones
                        <span x-show="addresses.length > 0" class="px-1.5 py-0.2 text-[10px] rounded-full" :class="activeTab === 'addresses' ? 'bg-white/25 text-white' : 'bg-stone-200 text-stone-700'" x-text="addresses.length"></span>
                    </button>
                    <button @click="activeTab = 'track'"
                            :style="activeTab === 'track' ? 'background: var(--pay-accent); color: #fff;' : ''"
                            :class="activeTab !== 'track' ? 'bg-[var(--pay-surface-muted)] text-[var(--pay-text-muted)] hover:bg-stone-200' : 'shadow-sm'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center flex items-center justify-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Rastrear Código
                    </button>
                </div>
            </template>
        </div>

        <div class="px-6 pt-3" x-show="errorMessage || successMessage">
            <div x-show="errorMessage" class="p-3 bg-[var(--pay-danger-bg)] border border-red-200 text-red-700 text-xs rounded-xl flex items-center justify-between">
                <span x-text="errorMessage"></span>
                <button @click="errorMessage = ''" class="text-red-500 font-bold ml-2">&times;</button>
            </div>
            <div x-show="successMessage" class="p-3 bg-[var(--pay-success-bg)] border border-emerald-200 text-emerald-700 text-xs rounded-xl flex items-center justify-between">
                <span x-text="successMessage"></span>
                <button @click="successMessage = ''" class="text-emerald-500 font-bold ml-2">&times;</button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 text-[var(--pay-text)] space-y-4">

            {{-- 1. LOGIN --}}
            <template x-if="!isLoggedIn && activeTab === 'login'">
                <form @submit.prevent="doLogin" class="space-y-4 max-w-md mx-auto py-2">
                    <div class="text-center mb-6">
                        <h3 class="font-bold text-[var(--pay-text)] text-xl">Bienvenido a Tribio</h3>
                        <p class="text-xs text-[var(--pay-text-muted)] mt-1">Inicia sesión con tu cuenta universal para gestionar tus compras y direcciones.</p>
                    </div>
                    @if($googleReady)
                    <a href="{{ route('auth.google.redirect', ['store' => $store->slug]) }}" class="w-full flex items-center justify-center gap-2.5 py-3 border rounded-xl text-sm font-bold transition-all hover:bg-[var(--pay-surface-muted)]" style="border-color: var(--pay-border); color: var(--pay-text);">
                        <svg class="w-4 h-4" viewBox="0 0 48 48">
                            <path fill="#FFC107" d="M43.6 20.5h-1.9V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.8-.4-4.5z"/>
                            <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 16.3 3 9.7 7.3 6.3 14.7z"/>
                            <path fill="#4CAF50" d="M24 45c5.5 0 10.4-1.9 14.1-5.1l-6.5-5.5C29.6 36 26.9 37 24 37c-5.2 0-9.6-3.3-11.2-8l-6.6 5C9.6 40.6 16.3 45 24 45z"/>
                            <path fill="#1976D2" d="M43.6 20.5h-1.9V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.6l6.5 5.5c-.5.4 7.4-5.4 7.4-15.6 0-1.4-.1-2.8-.4-4.5z"/>
                        </svg>
                        Continuar con Google
                    </a>
                    <div class="flex items-center gap-3 my-1">
                        <div class="flex-1 h-px" style="background: var(--pay-border);"></div>
                        <span class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--pay-text-muted);">o</span>
                        <div class="flex-1 h-px" style="background: var(--pay-border);"></div>
                    </div>
                    @endif
                    <div>
                        <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Correo Electrónico</label>
                        <input type="email" x-model="loginData.email" required placeholder="tu@correo.com"
                               class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-xl px-4 py-2.5 text-sm focus:bg-white outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Contraseña</label>
                        <input type="password" x-model="loginData.password" required placeholder="••••••••"
                               class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-xl px-4 py-2.5 text-sm focus:bg-white outline-none transition">
                    </div>
                    <button type="submit" :disabled="loading" style="background: var(--pay-accent);"
                            class="w-full py-3 text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm disabled:opacity-50 hover:opacity-90">
                        <span x-show="loading" class="pay-spinner"></span>
                        <span x-text="loading ? 'Iniciando sesión...' : 'Ingresar a mi cuenta'"></span>
                    </button>
                    <div class="text-center pt-2">
                        <p class="text-xs text-[var(--pay-text-muted)]">¿Aún no tienes cuenta?
                            <button type="button" @click="activeTab = 'register'" class="font-bold text-[var(--pay-accent-soft)] hover:underline">Crear cuenta gratis</button>
                        </p>
                    </div>
                </form>
            </template>

            {{-- 2. REGISTER --}}
            <template x-if="!isLoggedIn && activeTab === 'register'">
                <div x-show="!verifyStep">
                    <form @submit.prevent="doRegister" class="space-y-4">
                        <div class="text-center">
                            <span class="inline-block px-3 py-1 bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-bold rounded-full mb-2">1 cuenta para todas tus compras</span>
                            <h3 class="font-bold text-[var(--pay-text)] text-xl">Crear Cuenta Comprador Tribio</h3>
                            <p class="text-xs text-[var(--pay-text-muted)] mt-1">Regístrate una sola vez y disfruta de compras en 1-click y seguimiento en tiempo real.</p>
                        </div>

                        @if($googleReady)
                        <a href="{{ route('auth.google.redirect', ['store' => $store->slug]) }}" class="w-full flex items-center justify-center gap-2.5 py-3 border rounded-xl text-sm font-bold transition-all hover:bg-[var(--pay-surface-muted)]" style="border-color: var(--pay-border); color: var(--pay-text);">
                            <svg class="w-4 h-4" viewBox="0 0 48 48">
                                <path fill="#FFC107" d="M43.6 20.5h-1.9V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.8-.4-4.5z"/>
                                <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 16.3 3 9.7 7.3 6.3 14.7z"/>
                                <path fill="#4CAF50" d="M24 45c5.5 0 10.4-1.9 14.1-5.1l-6.5-5.5C29.6 36 26.9 37 24 37c-5.2 0-9.6-3.3-11.2-8l-6.6 5C9.6 40.6 16.3 45 24 45z"/>
                                <path fill="#1976D2" d="M43.6 20.5h-1.9V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.6l6.5 5.5c-.5.4 7.4-5.4 7.4-15.6 0-1.4-.1-2.8-.4-4.5z"/>
                            </svg>
                            Continuar con Google
                        </a>
                        <div class="flex items-center gap-3 my-1">
                            <div class="flex-1 h-px" style="background: var(--pay-border);"></div>
                            <span class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--pay-text-muted);">o regístrate con tu correo</span>
                            <div class="flex-1 h-px" style="background: var(--pay-border);"></div>
                        </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Nombre Completo *</label>
                                <input type="text" x-model="registerData.name" required placeholder="Ej. Juan Pérez" class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-xl px-4 py-2 text-sm focus:bg-white outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Teléfono / WhatsApp</label>
                                <input type="tel" x-model="registerData.phone" placeholder="+51 987 654 321" class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-xl px-4 py-2 text-sm focus:bg-white outline-none transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Correo Electrónico *</label>
                                <input type="email" x-model="registerData.email" required placeholder="correo@ejemplo.com" class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-xl px-4 py-2 text-sm focus:bg-white outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Contraseña *</label>
                                <input type="password" x-model="registerData.password" required minlength="6" placeholder="Mínimo 6 caracteres" class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-xl px-4 py-2 text-sm focus:bg-white outline-none transition">
                            </div>
                        </div>

                        <div class="p-3 bg-[var(--pay-surface-muted)] rounded-2xl border border-[var(--pay-border)] space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-[var(--pay-text)]">Tu dirección principal (opcional)</span>
                                <div class="flex gap-1">
                                    <button type="button" @click="registerData.type = 'casa'" :style="registerData.type === 'casa' ? 'background: var(--pay-accent); color:#fff;' : ''" :class="registerData.type !== 'casa' ? 'bg-white text-[var(--pay-text-muted)] border border-[var(--pay-border)]' : ''" class="px-2.5 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition">Casa</button>
                                    <button type="button" @click="registerData.type = 'trabajo'" :style="registerData.type === 'trabajo' ? 'background: var(--pay-accent); color:#fff;' : ''" :class="registerData.type !== 'trabajo' ? 'bg-white text-[var(--pay-text-muted)] border border-[var(--pay-border)]' : ''" class="px-2.5 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition">Trabajo</button>
                                    <button type="button" @click="registerData.type = 'otro'" :style="registerData.type === 'otro' ? 'background: var(--pay-accent); color:#fff;' : ''" :class="registerData.type !== 'otro' ? 'bg-white text-[var(--pay-text-muted)] border border-[var(--pay-border)]' : ''" class="px-2.5 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition">Otro</button>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <select x-model="registerData.country" @change="updateStates($event.target.value)" class="w-full bg-white border border-[var(--pay-border)] rounded-xl px-3 py-2 text-xs outline-none">
                                    <template x-for="c in countriesList" :key="c.iso2">
                                        <option :value="c.iso2" x-text="c.name" :selected="c.iso2 === registerData.country"></option>
                                    </template>
                                </select>
                                <select x-model="registerData.state" class="w-full bg-white border border-[var(--pay-border)] rounded-xl px-3 py-2 text-xs outline-none">
                                    <option value="">Selecciona Departamento/Estado</option>
                                    <template x-for="s in statesList" :key="s.state_code">
                                        <option :value="s.name" x-text="s.name"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="relative">
                                <input type="text" x-model="registerData.address" @input="searchAddress($event.target.value)" placeholder="Calle, Av., Número o Dpto (Autocompletado)" class="w-full bg-white border border-[var(--pay-border)] rounded-xl px-3.5 py-2 text-xs outline-none">
                                <div x-show="addressSuggestions.length > 0" class="absolute z-50 w-full bg-white border border-[var(--pay-border)] mt-1 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                    <template x-for="item in addressSuggestions" :key="item.place_id">
                                        <div @click="selectAddress(item)" class="p-2.5 text-xs border-b border-[var(--pay-border)] cursor-pointer hover:bg-[var(--pay-surface-muted)]">
                                            <span x-text="item.display_name" class="text-[var(--pay-text-muted)]"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <input type="text" x-model="registerData.city" placeholder="Ciudad / Provincia (Ej. Lima)" class="w-full bg-white border border-[var(--pay-border)] rounded-xl px-3 py-2 text-xs outline-none">
                        </div>

                        <button type="submit" :disabled="loading" style="background: var(--pay-accent);" class="w-full py-3 text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm disabled:opacity-50 hover:opacity-90">
                            <span x-show="loading" class="pay-spinner"></span>
                            <span x-text="loading ? 'Enviando...' : 'Completar Registro'"></span>
                        </button>
                        <p class="text-[11px] leading-relaxed text-[var(--pay-text-muted)]">Al crear tu cuenta, confirmas que leíste los <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer" class="underline font-semibold">Términos y Condiciones de Tribio</a> y la <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer" class="underline font-semibold">Política de Privacidad y Tratamiento de Datos</a>.</p>

                        <div class="text-center">
                            <p class="text-xs text-[var(--pay-text-muted)]">¿Ya estás registrado?
                                <button type="button" @click="activeTab = 'login'" class="font-bold text-[var(--pay-accent-soft)] hover:underline">Iniciar sesión</button>
                            </p>
                        </div>
                    </form>
                </div>

                <div x-show="verifyStep" class="py-6 space-y-6" style="display: none;">
                    <div class="text-center space-y-2">
                        <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="font-bold text-[var(--pay-text)] text-xl">Verifica tu Correo</h3>
                        <p class="text-sm text-[var(--pay-text-muted)] max-w-sm mx-auto">
                            Hemos enviado un código de seguridad de 6 dígitos a <br><strong class="text-[var(--pay-text)]" x-text="registerData.email"></strong>.
                        </p>
                    </div>

                    <form @submit.prevent="verifyOtp" class="max-w-xs mx-auto space-y-4">
                        <div>
                            <input type="text" x-model="otpToken" maxlength="6" required placeholder="000000" pattern="\d*"
                                   class="w-full text-center text-3xl tracking-[0.5em] font-mono bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-xl px-4 py-3 focus:bg-white outline-none transition">
                            <p class="text-center text-[10px] text-[var(--pay-text-muted)] mt-2">Revisa tu bandeja de entrada o spam.</p>
                        </div>
                        <button type="submit" :disabled="loading || otpToken.length !== 6" style="background: var(--pay-accent);" class="w-full py-3 text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm disabled:opacity-50 hover:opacity-90">
                            <span x-show="loading" class="pay-spinner"></span>
                            <span x-text="loading ? 'Verificando...' : 'Verificar y Crear Cuenta'"></span>
                        </button>
                        <div class="text-center">
                            <button type="button" @click="verifyStep = false" class="text-xs font-bold text-[var(--pay-accent-soft)] hover:underline">← Volver y editar correo</button>
                        </div>
                    </form>
                </div>
            </template>

            {{-- 3. ORDERS --}}
            <template x-if="isLoggedIn && activeTab === 'orders'">
                <div class="space-y-4">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h3 class="font-bold text-[var(--pay-text)] text-lg">Historial de Compras & Envíos</h3>
                            <p class="text-xs text-[var(--pay-text-muted)]">Monitorea el progreso de despacho de tus paquetes en tiempo real.</p>
                        </div>
                        <button @click="loadOrders()" class="text-xs text-[var(--pay-text-muted)] hover:text-[var(--pay-text)] flex items-center gap-1 bg-[var(--pay-surface-muted)] hover:bg-stone-200 px-3 py-1.5 rounded-xl transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Actualizar
                        </button>
                    </div>

                    <template x-if="loading">
                        <div class="py-12 text-center text-[var(--pay-text-muted)]">
                            <div class="inline-block pay-spinner mb-2" style="border-top-color: var(--pay-accent); border-color: var(--pay-border);"></div>
                            <p class="text-xs font-semibold">Cargando tus pedidos...</p>
                        </div>
                    </template>

                    <template x-if="!loading && orders.length === 0">
                        <div class="py-12 px-4 text-center bg-[var(--pay-surface-muted)] rounded-2xl border border-dashed border-[var(--pay-border)]">
                            <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center mx-auto shadow-sm mb-3">
                                <svg class="w-6 h-6 text-[var(--pay-text-muted)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <h4 class="font-bold text-[var(--pay-text)] text-sm mb-1">Aún no tienes pedidos registrados</h4>
                            <p class="text-xs text-[var(--pay-text-muted)] max-w-sm mx-auto mb-4">Cuando realices compras en esta tienda o en la red Tribio, aparecerán automáticamente aquí con su seguimiento en vivo.</p>
                            <button @click="isOpen = false" style="background: var(--pay-accent);" class="px-5 py-2 text-white text-xs font-bold rounded-xl hover:opacity-90 transition">Explorar Catálogo</button>
                        </div>
                    </template>

                    <template x-if="!loading && orders.length > 0">
                        <div class="space-y-4">
                            <template x-for="order in orders" :key="order.id">
                                <div class="bg-[var(--pay-surface-muted)] rounded-2xl p-4 sm:p-5 border border-[var(--pay-border)] transition-all shadow-sm">
                                    <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-[var(--pay-border)]">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-sm text-[var(--pay-text)]" x-text="order.order_number"></span>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                      :class="{
                                                          'bg-amber-100 text-amber-800': order.status === 'pending',
                                                          'bg-blue-100 text-blue-800': order.status === 'confirmed',
                                                          'bg-purple-100 text-purple-800': order.status === 'processing',
                                                          'bg-indigo-100 text-indigo-800': order.status === 'shipped',
                                                          'bg-emerald-100 text-emerald-800': order.status === 'delivered',
                                                          'bg-red-100 text-red-800': order.status === 'cancelled'
                                                      }"
                                                      x-text="order.status_label">
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-[var(--pay-text-muted)] mt-0.5" x-text="order.store_name + ' • ' + order.created_at"></p>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-black text-base text-[var(--pay-text)]" x-text="order.currency_symbol + ' ' + order.total.toFixed(2)"></div>
                                            <span class="text-[10px] text-[var(--pay-text-muted)]" x-text="order.items_count + (order.items_count === 1 ? ' producto' : ' productos')"></span>
                                        </div>
                                    </div>

                                    <div class="py-4">
                                        <div class="text-[11px] font-bold text-[var(--pay-text-muted)] mb-2 flex items-center justify-between">
                                            <span>Estado del envío:</span>
                                            <span class="font-extrabold" style="color: var(--pay-accent);" x-text="order.status_label"></span>
                                        </div>
                                        <div class="relative flex items-center justify-between">
                                            <div class="absolute left-3 right-3 top-3 h-1 bg-[var(--pay-border)] -z-0"></div>
                                            <div class="absolute left-3 top-3 h-1 bg-[var(--pay-success)] transition-all duration-500 -z-0" :style="'width: ' + ((order.status_step - 1) / 4 * 100) + '%'"></div>
                                            <template x-for="(label, i) in ['Recibido','Confirmado','Preparando','En camino','Entregado']" :key="i">
                                                <div class="flex flex-col items-center relative z-10">
                                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shadow-sm transition-colors"
                                                         :class="order.status_step >= (i+1) ? 'bg-[var(--pay-success)] text-white' : 'bg-[var(--pay-border)] text-[var(--pay-text-muted)]'">
                                                        <svg x-show="order.status_step > (i+1)" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                        <span x-show="order.status_step <= (i+1)" x-text="i+1"></span>
                                                    </div>
                                                    <span class="text-[10px] mt-1 font-semibold text-[var(--pay-text-muted)] text-center" x-text="label"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

@if($store->made_to_order_enabled)
                                    {{-- Pedido por encargo: etapa de producción y saldo por pagar --}}
                                    <template x-if="order.production_stage">
                                        <div class="mb-3 p-3 rounded-xl bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] text-xs space-y-1.5">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-bold text-[var(--pay-text-muted)]">✂️ Hecho a pedido</span>
                                                <span class="font-extrabold" style="color: var(--pay-accent);" x-text="order.production_stage_label"></span>
                                            </div>
                                            <p x-show="order.estimated_ready_at" class="text-[var(--pay-text-muted)]" x-text="'Listo aprox.: ' + order.estimated_ready_at"></p>
                                            <template x-if="order.balance_due > 0">
                                                <div class="flex items-center justify-between gap-2 pt-1">
                                                    <span class="text-[var(--pay-text)]">Saldo: <strong x-text="order.currency_symbol + ' ' + order.balance_due.toFixed(2)"></strong></span>
                                                    <a x-show="order.balance_url" :href="order.balance_url" class="font-bold text-white px-3 py-1.5 rounded-lg" style="background: var(--pay-accent);">Ver y pagar</a>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
@endif
                                    <div x-data="{ expanded: false }" class="mt-2 pt-2 border-t border-[var(--pay-border)]">
                                        <button @click="expanded = !expanded" class="text-xs font-bold text-[var(--pay-text-muted)] hover:text-[var(--pay-text)] flex items-center justify-between w-full">
                                            <span x-text="expanded ? 'Ocultar productos ▲' : 'Ver detalle de productos ▼'"></span>
                                            <span class="text-[11px] text-[var(--pay-text-muted)] font-normal" x-text="order.shipping_address ? 'Destino: ' + order.shipping_address : ''"></span>
                                        </button>
                                        <div x-show="expanded" class="mt-2 space-y-1.5 pt-1">
                                            <template x-for="item in order.items" :key="item.name">
                                                <div class="flex justify-between items-center text-xs bg-white p-2 rounded-lg border border-[var(--pay-border)]">
                                                    <span class="font-medium text-[var(--pay-text)]" x-text="item.quantity + 'x ' + item.name"></span>
                                                    <span class="font-bold text-[var(--pay-text-muted)]" x-text="order.currency_symbol + ' ' + item.subtotal.toFixed(2)"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- 4. ADDRESSES --}}
            <template x-if="isLoggedIn && activeTab === 'addresses'">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-[var(--pay-text)] text-lg">Direcciones Guardadas</h3>
                            <p class="text-xs text-[var(--pay-text-muted)]">Agiliza tus compras guardando tus lugares frecuentes de entrega.</p>
                        </div>
                        <button @click="showAddressForm = !showAddressForm; newAddress = { id: null, type: 'casa', address: '', city: '', state: '', country: 'PE', zipcode: '', reference: '', phone: '', is_default: false }"
                                style="background: var(--pay-accent);" class="text-xs font-bold text-white px-3.5 py-2 rounded-xl transition shadow-sm hover:opacity-90">
                            <span x-text="showAddressForm ? 'Cancelar' : '+ Nueva Dirección'"></span>
                        </button>
                    </div>

                    <div x-show="showAddressForm" class="p-4 bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-2xl space-y-3">
                        <h4 class="font-bold text-xs text-[var(--pay-text)] uppercase tracking-wider">Guardar Dirección</h4>
                        <div>
                            <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Tipo de Dirección</label>
                            <div class="flex gap-2">
                                <button type="button" @click="newAddress.type = 'casa'" :style="newAddress.type === 'casa' ? 'background: var(--pay-accent); color:#fff;' : ''" :class="newAddress.type !== 'casa' ? 'bg-white text-[var(--pay-text)] border border-[var(--pay-border)]' : ''" class="flex-1 py-2 rounded-xl text-xs font-bold transition">Casa</button>
                                <button type="button" @click="newAddress.type = 'trabajo'" :style="newAddress.type === 'trabajo' ? 'background: var(--pay-accent); color:#fff;' : ''" :class="newAddress.type !== 'trabajo' ? 'bg-white text-[var(--pay-text)] border border-[var(--pay-border)]' : ''" class="flex-1 py-2 rounded-xl text-xs font-bold transition">Trabajo</button>
                                <button type="button" @click="newAddress.type = 'otro'" :style="newAddress.type === 'otro' ? 'background: var(--pay-accent); color:#fff;' : ''" :class="newAddress.type !== 'otro' ? 'bg-white text-[var(--pay-text)] border border-[var(--pay-border)]' : ''" class="flex-1 py-2 rounded-xl text-xs font-bold transition">Otro</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Dirección Exacta *</label>
                            <input type="text" x-model="newAddress.address" placeholder="Av., Calle, Número, Piso, Dpto" required class="w-full bg-white border border-[var(--pay-border)] rounded-xl px-3 py-2 text-xs outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Ciudad</label>
                                <input type="text" x-model="newAddress.city" placeholder="Lima, Trujillo, etc." class="w-full bg-white border border-[var(--pay-border)] rounded-xl px-3 py-2 text-xs outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Departamento / Estado</label>
                                <input type="text" x-model="newAddress.state" placeholder="Lima, Cusco, etc." class="w-full bg-white border border-[var(--pay-border)] rounded-xl px-3 py-2 text-xs outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Referencia</label>
                            <input type="text" x-model="newAddress.reference" placeholder="Frente al parque, portón verde..." class="w-full bg-white border border-[var(--pay-border)] rounded-xl px-3 py-2 text-xs outline-none">
                        </div>
                        <label class="flex items-center gap-2 text-xs text-[var(--pay-text-muted)] cursor-pointer pt-1">
                            <input type="checkbox" x-model="newAddress.is_default" class="rounded">
                            <span>Marcar como dirección predeterminada</span>
                        </label>
                        <button type="button" @click="saveAddress()" :disabled="loading" style="background: var(--pay-accent);" class="w-full py-2.5 text-white text-xs font-bold rounded-xl transition shadow-sm hover:opacity-90">Guardar Dirección</button>
                    </div>

                    <template x-if="addresses.length === 0">
                        <div class="py-8 text-center bg-[var(--pay-surface-muted)] rounded-2xl border border-dashed border-[var(--pay-border)]">
                            <p class="text-xs text-[var(--pay-text-muted)]">No tienes direcciones guardadas aún.</p>
                        </div>
                    </template>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <template x-for="addr in addresses" :key="addr.id">
                            <div class="p-4 bg-[var(--pay-surface-muted)] rounded-2xl border border-[var(--pay-border)] relative flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-bold text-xs text-[var(--pay-text)] capitalize" x-text="addr.type"></span>
                                        <template x-if="addr.is_default">
                                            <span class="text-[9px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">Predeterminada</span>
                                        </template>
                                    </div>
                                    <p class="text-xs font-semibold text-[var(--pay-text)]" x-text="addr.address"></p>
                                    <p class="text-[11px] text-[var(--pay-text-muted)]" x-text="(addr.city || '') + (addr.state ? ', ' + addr.state : '')"></p>
                                    <template x-if="addr.reference">
                                        <p class="text-[10px] text-[var(--pay-text-muted)] italic mt-1" x-text="'Ref: ' + addr.reference"></p>
                                    </template>
                                </div>
                                <div class="mt-3 pt-2 border-t border-[var(--pay-border)] flex justify-end">
                                    <button @click="deleteAddress(addr.id)" class="text-[11px] text-red-500 hover:text-red-700 font-semibold">Eliminar</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- 5. TRACK --}}
            <template x-if="activeTab === 'track'">
                <div class="space-y-4 max-w-md mx-auto py-2">
                    <div class="text-center mb-4">
                        <h3 class="font-bold text-[var(--pay-text)] text-xl">Rastreo de Paquete</h3>
                        <p class="text-xs text-[var(--pay-text-muted)] mt-1">Ingresa el código único de tu orden para ver el avance del despacho en tiempo real.</p>
                    </div>
                    <form @submit.prevent="doTrack" class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Número de Pedido *</label>
                            <input type="text" x-model="trackData.order_number" required placeholder="Ej. PED-998822" class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-xl px-4 py-2.5 text-sm font-mono uppercase focus:bg-white outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Correo de compra (Opcional)</label>
                            <input type="email" x-model="trackData.email" placeholder="tu@correo.com" class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-xl px-4 py-2.5 text-sm focus:bg-white outline-none">
                        </div>
                        <button type="submit" :disabled="trackLoading" style="background: var(--pay-accent);" class="w-full py-3 text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm disabled:opacity-50 hover:opacity-90">
                            <span x-show="trackLoading" class="pay-spinner"></span>
                            <span x-text="trackLoading ? 'Consultando...' : 'Consultar Estado del Paquete'"></span>
                        </button>
                    </form>

                    <div x-show="trackError" class="p-3 bg-[var(--pay-danger-bg)] border border-red-200 text-red-700 text-xs rounded-xl">
                        <span x-text="trackError"></span>
                    </div>

                    <template x-if="trackResult">
                        <div class="mt-4 p-4 bg-[var(--pay-surface-muted)] rounded-2xl border border-[var(--pay-border)] space-y-3">
                            <div class="flex justify-between items-start border-b border-[var(--pay-border)] pb-2">
                                <div>
                                    <span class="font-mono font-bold text-sm text-[var(--pay-text)]" x-text="trackResult.order_number"></span>
                                    <p class="text-[11px] text-[var(--pay-text-muted)]" x-text="trackResult.store_name + ' • ' + trackResult.created_at"></p>
                                </div>
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800" x-text="trackResult.status_label"></span>
                            </div>
                            <div class="py-2">
                                <div class="flex items-center justify-between text-[10px] font-bold text-[var(--pay-text-muted)]">
                                    <template x-for="(label, i) in ['Recibido','Confirmado','Preparando','En camino','Entregado']" :key="i">
                                        <span :class="trackResult.status_step >= (i+1) ? 'text-emerald-700' : ''" x-text="(i+1) + '. ' + label"></span>
                                    </template>
                                </div>
                            </div>
                            <div class="text-xs text-[var(--pay-text-muted)] pt-1">
                                <p class="font-semibold mb-1">Productos:</p>
                                <template x-for="it in trackResult.items" :key="it.name">
                                    <div class="flex justify-between text-[11px] text-[var(--pay-text-muted)]">
                                        <span x-text="it.quantity + 'x ' + it.name"></span>
                                        <span x-text="trackResult.currency_symbol + ' ' + (it.price * it.quantity).toFixed(2)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div class="px-6 py-3 bg-[var(--pay-surface-muted)] border-t border-[var(--pay-border)] flex items-center justify-between text-[11px] text-[var(--pay-text-muted)]">
            <span class="flex items-center gap-1.5 font-medium">
                <svg class="w-3.5 h-3.5 text-[var(--pay-success)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Compras protegidas por <strong class="text-[var(--pay-text)]">Tribio</strong>
            </span>
            <span class="text-[10px]">Cuenta Universal de Comprador</span>
        </div>
    </div>
</div>
