{{-- Modal Universal de Cuenta de Comprador / Cliente Tribio --}}
<div id="tribioCustomerModal"
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
        registerData: { name: '', email: '', phone: '', password: '', address: '', city: '', state: '', type: 'casa' },
        trackData: { order_number: '', email: '' },
        showAddressForm: false,
        newAddress: { id: null, type: 'casa', address: '', city: '', state: '', country: 'PE', zipcode: '', reference: '', phone: '', is_default: false },

        init() {
            this.checkSession();
            window.addEventListener('open-customer-modal', (e) => {
                this.isOpen = true;
                if (e.detail && e.detail.tab) {
                    this.activeTab = e.detail.tab;
                } else if (!this.isLoggedIn) {
                    this.activeTab = 'login';
                }
                if (this.isLoggedIn) {
                    this.loadOrders();
                    this.loadAddresses();
                }
            });
            window.openCustomerModal = (tab = null) => {
                window.dispatchEvent(new CustomEvent('open-customer-modal', { detail: { tab } }));
            };
        },

        async checkSession() {
            try {
                const res = await fetch('/customer/current', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.authenticated && data.user) {
                    this.isLoggedIn = true;
                    this.user = data.user;
                    this.addresses = data.addresses || [];
                    window.dispatchEvent(new CustomEvent('customer-authenticated', { detail: data }));
                } else {
                    this.isLoggedIn = false;
                    this.user = null;
                    this.addresses = [];
                }
            } catch (e) {
                console.error('Error checking customer session:', e);
            }
        },

        async doLogin() {
            this.errorMessage = '';
            this.successMessage = '';
            this.loading = true;
            try {
                const res = await fetch('/customer/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.loginData)
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.isLoggedIn = true;
                    this.user = data.user;
                    this.addresses = data.addresses || [];
                    this.activeTab = 'orders';
                    this.successMessage = data.message;
                    window.dispatchEvent(new CustomEvent('customer-authenticated', { detail: data }));
                    this.loadOrders();
                } else {
                    this.errorMessage = data.message || 'Error al iniciar sesión.';
                }
            } catch (e) {
                this.errorMessage = 'Error de conexión con el servidor.';
            } finally {
                this.loading = false;
            }
        },

        async doRegister() {
            this.errorMessage = '';
            this.successMessage = '';
            this.loading = true;
            try {
                const res = await fetch('/customer/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.registerData)
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.isLoggedIn = true;
                    this.user = data.user;
                    this.addresses = data.addresses || [];
                    this.activeTab = 'orders';
                    this.successMessage = data.message;
                    window.dispatchEvent(new CustomEvent('customer-authenticated', { detail: data }));
                    this.loadOrders();
                } else {
                    let msg = data.message || 'Error en el registro.';
                    if (data.errors) {
                        msg = Object.values(data.errors).flat().join(' ');
                    }
                    this.errorMessage = msg;
                }
            } catch (e) {
                this.errorMessage = 'Error de conexión con el servidor.';
            } finally {
                this.loading = false;
            }
        },

        async doLogout() {
            try {
                await fetch('/customer/logout', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' }
                });
                this.isLoggedIn = false;
                this.user = null;
                this.orders = [];
                this.addresses = [];
                this.activeTab = 'login';
                window.dispatchEvent(new CustomEvent('customer-logged-out'));
            } catch (e) {
                console.error(e);
            }
        },

        async loadOrders() {
            if (!this.isLoggedIn) return;
            this.loading = true;
            try {
                const res = await fetch('/customer/orders', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.orders = data.orders || [];
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },

        async loadAddresses() {
            if (!this.isLoggedIn) return;
            try {
                const res = await fetch('/customer/addresses', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.addresses = data.addresses || [];
                }
            } catch (e) {
                console.error(e);
            }
        },

        async saveAddress() {
            if (!this.newAddress.address) {
                alert('Por favor ingresa la dirección.');
                return;
            }
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
                    alert(data.message || 'Error al guardar la dirección');
                }
            } catch (e) {
                alert('Error al guardar la dirección.');
            } finally {
                this.loading = false;
            }
        },

        async deleteAddress(id) {
            if (!confirm('¿Deseas eliminar esta dirección?')) return;
            try {
                const res = await fetch(`/customer/addresses/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                if (data.success) {
                    this.addresses = data.addresses || [];
                }
            } catch (e) {
                console.error(e);
            }
        },

        async doTrack() {
            if (!this.trackData.order_number) return;
            this.trackLoading = true;
            this.trackError = '';
            this.trackResult = null;
            try {
                const res = await fetch('/customer/track-order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(this.trackData)
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.trackResult = data.order;
                } else {
                    this.trackError = data.message || 'No se encontró información del pedido.';
                }
            } catch (e) {
                this.trackError = 'Error de conexión al consultar el pedido.';
            } finally {
                this.trackLoading = false;
            }
        }
     }"
     x-show="isOpen"
     style="display: none;"
     class="fixed inset-0 z-[1000] flex items-center justify-center p-3 sm:p-5 overflow-y-auto"
     aria-modal="true"
     role="dialog">

    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-stone-900/60 backdrop-blur-sm transition-opacity"
         @click="isOpen = false"></div>

    {{-- Modal Card --}}
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden border border-stone-200/80 z-10">
        
        {{-- Modal Top Bar --}}
        <div class="px-6 py-5 border-b border-stone-100 flex items-center justify-between bg-gradient-to-r from-stone-50 via-white to-stone-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-[#1A1A1A] text-white flex items-center justify-center font-black text-sm shadow-md">
                    <span x-show="!isLoggedIn">T</span>
                    <span x-show="isLoggedIn" x-text="user ? user.name.charAt(0).toUpperCase() : 'U'"></span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-gray-900 text-base sm:text-lg" x-text="isLoggedIn ? user.name : 'Portal de Comprador'"></h2>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 tracking-wider">
                            Tribio Pass
                        </span>
                    </div>
                    <p class="text-xs text-gray-500" x-text="isLoggedIn ? user.email : 'Tu cuenta universal para compras y pedidos'"></p>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <template x-if="isLoggedIn">
                    <button @click="doLogout" title="Cerrar Sesión" class="text-xs font-semibold text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-xl transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        <span class="hidden sm:inline">Salir</span>
                    </button>
                </template>
                <button @click="isOpen = false" class="w-8 h-8 rounded-full bg-stone-100 hover:bg-stone-200 text-stone-500 hover:text-stone-800 flex items-center justify-center text-sm font-bold transition">
                    ✕
                </button>
            </div>
        </div>

        {{-- Nav Tabs --}}
        <div class="px-6 pt-3 pb-2 border-b border-stone-100 flex gap-2 overflow-x-auto text-xs font-bold scrollbar-none">
            <template x-if="!isLoggedIn">
                <div class="flex gap-2 w-full">
                    <button @click="activeTab = 'login'; errorMessage = ''; successMessage = ''"
                            :class="activeTab === 'login' ? 'bg-[#1A1A1A] text-white shadow-sm' : 'bg-stone-100 text-gray-600 hover:bg-stone-200'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center">
                        🔑 Iniciar Sesión
                    </button>
                    <button @click="activeTab = 'register'; errorMessage = ''; successMessage = ''"
                            :class="activeTab === 'register' ? 'bg-[#1A1A1A] text-white shadow-sm' : 'bg-stone-100 text-gray-600 hover:bg-stone-200'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center">
                        ✨ Crear Cuenta
                    </button>
                    <button @click="activeTab = 'track'; errorMessage = ''; successMessage = ''"
                            :class="activeTab === 'track' ? 'bg-[#1A1A1A] text-white shadow-sm' : 'bg-stone-100 text-gray-600 hover:bg-stone-200'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center">
                        📦 Rastrear Pedido
                    </button>
                </div>
            </template>

            <template x-if="isLoggedIn">
                <div class="flex gap-2 w-full">
                    <button @click="activeTab = 'orders'; loadOrders();"
                            :class="activeTab === 'orders' ? 'bg-[#1A1A1A] text-white shadow-sm' : 'bg-stone-100 text-gray-600 hover:bg-stone-200'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center flex items-center justify-center gap-1.5">
                        📦 Mis Pedidos
                        <span x-show="orders.length > 0" class="px-1.5 py-0.2 text-[10px] rounded-full" :class="activeTab === 'orders' ? 'bg-stone-700 text-white' : 'bg-stone-200 text-stone-700'" x-text="orders.length"></span>
                    </button>
                    <button @click="activeTab = 'addresses'; loadAddresses();"
                            :class="activeTab === 'addresses' ? 'bg-[#1A1A1A] text-white shadow-sm' : 'bg-stone-100 text-gray-600 hover:bg-stone-200'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center flex items-center justify-center gap-1.5">
                        📍 Mis Direcciones
                        <span x-show="addresses.length > 0" class="px-1.5 py-0.2 text-[10px] rounded-full" :class="activeTab === 'addresses' ? 'bg-stone-700 text-white' : 'bg-stone-200 text-stone-700'" x-text="addresses.length"></span>
                    </button>
                    <button @click="activeTab = 'track'"
                            :class="activeTab === 'track' ? 'bg-[#1A1A1A] text-white shadow-sm' : 'bg-stone-100 text-gray-600 hover:bg-stone-200'"
                            class="flex-1 py-2 px-3 rounded-xl transition text-center">
                        🔍 Rastrear Código
                    </button>
                </div>
            </template>
        </div>

        {{-- Alerts --}}
        <div class="px-6 pt-3" x-show="errorMessage || successMessage">
            <div x-show="errorMessage" class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center justify-between">
                <span x-text="errorMessage"></span>
                <button @click="errorMessage = ''" class="text-red-500 font-bold ml-2">✕</button>
            </div>
            <div x-show="successMessage" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-xl flex items-center justify-between">
                <span x-text="successMessage"></span>
                <button @click="successMessage = ''" class="text-emerald-500 font-bold ml-2">✕</button>
            </div>
        </div>

        {{-- Scrollable Content Body --}}
        <div class="flex-1 overflow-y-auto p-6 text-stone-800 space-y-4">
            
            {{-- 1. TAB: INICIAR SESIÓN --}}
            <template x-if="!isLoggedIn && activeTab === 'login'">
                <form @submit.prevent="doLogin" class="space-y-4 max-w-md mx-auto py-2">
                    <div class="text-center mb-6">
                        <h3 class="font-bold text-gray-900 text-xl">Bienvenido a Tribio</h3>
                        <p class="text-xs text-gray-500 mt-1">Inicia sesión con tu cuenta universal para gestionar tus compras y direcciones.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Correo Electrónico</label>
                        <input type="email" x-model="loginData.email" required placeholder="tu@correo.com"
                               class="w-full bg-stone-50 border border-stone-300 rounded-xl px-4 py-2.5 text-sm focus:bg-white focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Contraseña</label>
                        <input type="password" x-model="loginData.password" required placeholder="••••••••"
                               class="w-full bg-stone-50 border border-stone-300 rounded-xl px-4 py-2.5 text-sm focus:bg-white focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition">
                    </div>

                    <button type="submit" :disabled="loading"
                            class="w-full py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm disabled:opacity-50">
                        <span x-show="loading" class="animate-spin">⏳</span>
                        <span x-text="loading ? 'Iniciando sesión...' : 'Ingresar a mi cuenta'"></span>
                    </button>

                    <div class="text-center pt-2">
                        <p class="text-xs text-gray-500">¿Aún no tienes cuenta? 
                            <button type="button" @click="activeTab = 'register'" class="font-bold text-[#C8A68B] hover:underline">Crear cuenta gratis</button>
                        </p>
                    </div>
                </form>
            </template>

            {{-- 2. TAB: CREAR CUENTA UNIVERSAL --}}
            <template x-if="!isLoggedIn && activeTab === 'register'">
                <form @submit.prevent="doRegister" class="space-y-4 max-w-lg mx-auto py-1">
                    <div class="text-center mb-4">
                        <span class="inline-block px-3 py-1 bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-bold rounded-full mb-2">
                            🚀 1 Cuenta para todas tus compras
                        </span>
                        <h3 class="font-bold text-gray-900 text-xl">Crear Cuenta Comprador Tribio</h3>
                        <p class="text-xs text-gray-500 mt-1">Regístrate una sola vez y disfruta de compras en 1-click y seguimiento en tiempo real.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Nombre Completo *</label>
                            <input type="text" x-model="registerData.name" required placeholder="Ej. Juan Pérez"
                                   class="w-full bg-stone-50 border border-stone-300 rounded-xl px-4 py-2 text-sm focus:bg-white focus:border-[#C8A68B] outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Teléfono / WhatsApp</label>
                            <input type="tel" x-model="registerData.phone" placeholder="+51 987 654 321"
                                   class="w-full bg-stone-50 border border-stone-300 rounded-xl px-4 py-2 text-sm focus:bg-white focus:border-[#C8A68B] outline-none transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Correo Electrónico *</label>
                            <input type="email" x-model="registerData.email" required placeholder="correo@ejemplo.com"
                                   class="w-full bg-stone-50 border border-stone-300 rounded-xl px-4 py-2 text-sm focus:bg-white focus:border-[#C8A68B] outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Contraseña *</label>
                            <input type="password" x-model="registerData.password" required minlength="6" placeholder="Mínimo 6 caracteres"
                                   class="w-full bg-stone-50 border border-stone-300 rounded-xl px-4 py-2 text-sm focus:bg-white focus:border-[#C8A68B] outline-none transition">
                        </div>
                    </div>

                    {{-- Dirección opcional inicial --}}
                    <div class="p-3 bg-stone-50 rounded-2xl border border-stone-200/70 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-700">📍 Tu Dirección Principal (Opcional)</span>
                            <div class="flex gap-1">
                                <button type="button" @click="registerData.type = 'casa'"
                                       :class="registerData.type === 'casa' ? 'bg-[#1A1A1A] text-white' : 'bg-white text-gray-600 border border-stone-200'"
                                       class="px-2.5 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition">
                                    🏠 Casa
                                </button>
                                <button type="button" @click="registerData.type = 'trabajo'"
                                       :class="registerData.type === 'trabajo' ? 'bg-[#1A1A1A] text-white' : 'bg-white text-gray-600 border border-stone-200'"
                                       class="px-2.5 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition">
                                    💼 Trabajo
                                </button>
                                <button type="button" @click="registerData.type = 'otro'"
                                       :class="registerData.type === 'otro' ? 'bg-[#1A1A1A] text-white' : 'bg-white text-gray-600 border border-stone-200'"
                                       class="px-2.5 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition">
                                    📍 Otro
                                </button>
                            </div>
                        </div>

                        <input type="text" x-model="registerData.address" placeholder="Calle, Av., Número o Dpto"
                               class="w-full bg-white border border-stone-300 rounded-xl px-3.5 py-2 text-xs focus:border-[#C8A68B] outline-none">

                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" x-model="registerData.city" placeholder="Ciudad (Ej. Lima)"
                                   class="w-full bg-white border border-stone-300 rounded-xl px-3 py-2 text-xs focus:border-[#C8A68B] outline-none">
                            <input type="text" x-model="registerData.state" placeholder="Departamento / Estado"
                                   class="w-full bg-white border border-stone-300 rounded-xl px-3 py-2 text-xs focus:border-[#C8A68B] outline-none">
                        </div>
                    </div>

                    <button type="submit" :disabled="loading"
                            class="w-full py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm disabled:opacity-50">
                        <span x-show="loading" class="animate-spin">⏳</span>
                        <span x-text="loading ? 'Creando cuenta...' : 'Completar Registro Universal'"></span>
                    </button>

                    <div class="text-center">
                        <p class="text-xs text-gray-500">¿Ya estás registrado? 
                            <button type="button" @click="activeTab = 'login'" class="font-bold text-[#C8A68B] hover:underline">Iniciar sesión</button>
                        </p>
                    </div>
                </form>
            </template>

            {{-- 3. TAB: MIS PEDIDOS / ESTADO DE PAQUETES (LOGGED IN) --}}
            <template x-if="isLoggedIn && activeTab === 'orders'">
                <div class="space-y-4">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg">Historial de Compras & Envíos</h3>
                            <p class="text-xs text-gray-500">Monitorea el progreso de despacho de tus paquetes en tiempo real.</p>
                        </div>
                        <button @click="loadOrders()" class="text-xs text-gray-500 hover:text-gray-800 flex items-center gap-1 bg-stone-100 hover:bg-stone-200 px-3 py-1.5 rounded-xl transition">
                            🔄 Actualizar
                        </button>
                    </div>

                    <template x-if="loading">
                        <div class="py-12 text-center text-gray-400">
                            <div class="inline-block animate-spin text-2xl mb-2">⏳</div>
                            <p class="text-xs font-semibold">Cargando tus pedidos...</p>
                        </div>
                    </template>

                    <template x-if="!loading && orders.length === 0">
                        <div class="py-12 px-4 text-center bg-stone-50 rounded-2xl border border-dashed border-stone-200">
                            <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center text-2xl mx-auto shadow-sm mb-3">🛍️</div>
                            <h4 class="font-bold text-gray-800 text-sm mb-1">Aún no tienes pedidos registrados</h4>
                            <p class="text-xs text-gray-500 max-w-sm mx-auto mb-4">Cuando realices compras en esta tienda o en la red Tribio, aparecerán automáticamente aquí con su seguimiento en vivo.</p>
                            <button @click="isOpen = false" class="px-5 py-2 bg-[#1A1A1A] text-white text-xs font-bold rounded-xl hover:bg-[#C8A68B] transition">
                                Explorar Catálogo
                            </button>
                        </div>
                    </template>

                    <template x-if="!loading && orders.length > 0">
                        <div class="space-y-4">
                            <template x-for="order in orders" :key="order.id">
                                <div class="bg-stone-50 hover:bg-stone-50/80 rounded-2xl p-4 sm:p-5 border border-stone-200/80 transition-all shadow-sm">
                                    
                                    {{-- Order Header --}}
                                    <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-stone-200/60">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-sm text-gray-900" x-text="order.order_number"></span>
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
                                            <p class="text-[11px] text-gray-500 mt-0.5" x-text="order.store_name + ' • ' + order.created_at"></p>
                                        </div>

                                        <div class="text-right">
                                            <div class="font-black text-base text-gray-900" x-text="order.currency_symbol + ' ' + order.total.toFixed(2)"></div>
                                            <span class="text-[10px] text-gray-400" x-text="order.items_count + (order.items_count === 1 ? ' producto' : ' productos')"></span>
                                        </div>
                                    </div>

                                    {{-- Visual 5-Step Tracker Bar --}}
                                    <div class="py-4">
                                        <div class="text-[11px] font-bold text-gray-600 mb-2 flex items-center justify-between">
                                            <span>Estado del envío:</span>
                                            <span class="text-indigo-600 font-extrabold" x-text="order.status_label"></span>
                                        </div>
                                        
                                        <div class="relative flex items-center justify-between">
                                            {{-- Connecting Line --}}
                                            <div class="absolute left-3 right-3 top-3 h-1 bg-stone-200 -z-0"></div>
                                            <div class="absolute left-3 top-3 h-1 bg-emerald-500 transition-all duration-500 -z-0"
                                                 :style="'width: ' + ((order.status_step - 1) / 4 * 100) + '%'"></div>

                                            {{-- Step 1 --}}
                                            <div class="flex flex-col items-center relative z-10">
                                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shadow-sm transition-colors"
                                                     :class="order.status_step >= 1 ? 'bg-emerald-600 text-white' : 'bg-stone-200 text-stone-500'">
                                                    ✓
                                                </div>
                                                <span class="text-[10px] mt-1 font-semibold text-gray-600 text-center">Recibido</span>
                                            </div>

                                            {{-- Step 2 --}}
                                            <div class="flex flex-col items-center relative z-10">
                                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shadow-sm transition-colors"
                                                     :class="order.status_step >= 2 ? 'bg-emerald-600 text-white' : 'bg-stone-200 text-stone-500'">
                                                    <span x-show="order.status_step > 2">✓</span>
                                                    <span x-show="order.status_step <= 2">2</span>
                                                </div>
                                                <span class="text-[10px] mt-1 font-semibold text-gray-600 text-center">Confirmado</span>
                                            </div>

                                            {{-- Step 3 --}}
                                            <div class="flex flex-col items-center relative z-10">
                                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shadow-sm transition-colors"
                                                     :class="order.status_step >= 3 ? 'bg-emerald-600 text-white' : 'bg-stone-200 text-stone-500'">
                                                    <span x-show="order.status_step > 3">✓</span>
                                                    <span x-show="order.status_step <= 3">3</span>
                                                </div>
                                                <span class="text-[10px] mt-1 font-semibold text-gray-600 text-center">Preparando</span>
                                            </div>

                                            {{-- Step 4 --}}
                                            <div class="flex flex-col items-center relative z-10">
                                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shadow-sm transition-colors"
                                                     :class="order.status_step >= 4 ? 'bg-emerald-600 text-white' : 'bg-stone-200 text-stone-500'">
                                                    <span x-show="order.status_step > 4">✓</span>
                                                    <span x-show="order.status_step <= 4">🚚</span>
                                                </div>
                                                <span class="text-[10px] mt-1 font-semibold text-gray-600 text-center">En camino</span>
                                            </div>

                                            {{-- Step 5 --}}
                                            <div class="flex flex-col items-center relative z-10">
                                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shadow-sm transition-colors"
                                                     :class="order.status_step >= 5 ? 'bg-emerald-600 text-white' : 'bg-stone-200 text-stone-500'">
                                                    📦
                                                </div>
                                                <span class="text-[10px] mt-1 font-semibold text-gray-600 text-center">Entregado</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Items Accordion --}}
                                    <div x-data="{ expanded: false }" class="mt-2 pt-2 border-t border-stone-200/50">
                                        <button @click="expanded = !expanded" class="text-xs font-bold text-gray-500 hover:text-gray-800 flex items-center justify-between w-full">
                                            <span x-text="expanded ? 'Ocultar productos ▲' : 'Ver detalle de productos ▼'"></span>
                                            <span class="text-[11px] text-gray-400 font-normal" x-text="order.shipping_address ? 'Destino: ' + order.shipping_address : ''"></span>
                                        </button>
                                        <div x-show="expanded" class="mt-2 space-y-1.5 pt-1">
                                            <template x-for="item in order.items" :key="item.name">
                                                <div class="flex justify-between items-center text-xs bg-white p-2 rounded-lg border border-stone-100">
                                                    <span class="font-medium text-gray-800" x-text="item.quantity + 'x ' + item.name"></span>
                                                    <span class="font-bold text-gray-600" x-text="order.currency_symbol + ' ' + item.subtotal.toFixed(2)"></span>
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

            {{-- 4. TAB: MIS DIRECCIONES (LOGGED IN) --}}
            <template x-if="isLoggedIn && activeTab === 'addresses'">
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg">Direcciones Guardadas</h3>
                            <p class="text-xs text-gray-500">Agiliza tus compras guardando tus lugares frecuentes de entrega.</p>
                        </div>
                        <button @click="showAddressForm = !showAddressForm; newAddress = { id: null, type: 'casa', address: '', city: '', state: '', country: 'PE', zipcode: '', reference: '', phone: '', is_default: false }"
                                class="text-xs font-bold bg-[#1A1A1A] hover:bg-[#C8A68B] text-white px-3.5 py-2 rounded-xl transition shadow-sm flex items-center gap-1">
                            <span x-text="showAddressForm ? 'Cancelar' : '+ Nueva Dirección'"></span>
                        </button>
                    </div>

                    {{-- Add/Edit Address Form --}}
                    <div x-show="showAddressForm" class="p-4 bg-stone-50 border border-stone-200 rounded-2xl space-y-3">
                        <h4 class="font-bold text-xs text-gray-700 uppercase tracking-wider">Guardar Dirección</h4>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Tipo de Dirección</label>
                            <div class="flex gap-2">
                                <button type="button" @click="newAddress.type = 'casa'"
                                        :class="newAddress.type === 'casa' ? 'bg-[#1A1A1A] text-white' : 'bg-white text-gray-700 border border-stone-300'"
                                        class="flex-1 py-2 rounded-xl text-xs font-bold transition">
                                    🏠 Casa
                                </button>
                                <button type="button" @click="newAddress.type = 'trabajo'"
                                        :class="newAddress.type === 'trabajo' ? 'bg-[#1A1A1A] text-white' : 'bg-white text-gray-700 border border-stone-300'"
                                        class="flex-1 py-2 rounded-xl text-xs font-bold transition">
                                    💼 Trabajo
                                </button>
                                <button type="button" @click="newAddress.type = 'otro'"
                                        :class="newAddress.type === 'otro' ? 'bg-[#1A1A1A] text-white' : 'bg-white text-gray-700 border border-stone-300'"
                                        class="flex-1 py-2 rounded-xl text-xs font-bold transition">
                                    📍 Otro
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Dirección Exacta *</label>
                            <input type="text" x-model="newAddress.address" placeholder="Av., Calle, Número, Piso, Dpto" required
                                   class="w-full bg-white border border-stone-300 rounded-xl px-3 py-2 text-xs focus:border-[#C8A68B] outline-none">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Ciudad</label>
                                <input type="text" x-model="newAddress.city" placeholder="Lima, Trujillo, etc."
                                       class="w-full bg-white border border-stone-300 rounded-xl px-3 py-2 text-xs focus:border-[#C8A68B] outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Departamento / Estado</label>
                                <input type="text" x-model="newAddress.state" placeholder="Lima, Cusco, etc."
                                       class="w-full bg-white border border-stone-300 rounded-xl px-3 py-2 text-xs focus:border-[#C8A68B] outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Referencia</label>
                            <input type="text" x-model="newAddress.reference" placeholder="Frente al parque, portón verde..."
                                   class="w-full bg-white border border-stone-300 rounded-xl px-3 py-2 text-xs focus:border-[#C8A68B] outline-none">
                        </div>

                        <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer pt-1">
                            <input type="checkbox" x-model="newAddress.is_default" class="rounded text-stone-900">
                            <span>Marcar como dirección predeterminada</span>
                        </label>

                        <button type="button" @click="saveAddress()" :disabled="loading"
                                class="w-full py-2.5 bg-[#1A1A1A] hover:bg-[#C8A68B] text-white text-xs font-bold rounded-xl transition shadow-sm">
                            Guardar Dirección
                        </button>
                    </div>

                    {{-- Addresses List --}}
                    <template x-if="addresses.length === 0">
                        <div class="py-8 text-center bg-stone-50 rounded-2xl border border-dashed border-stone-200">
                            <p class="text-xs text-gray-400">No tienes direcciones guardadas aún.</p>
                        </div>
                    </template>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <template x-for="addr in addresses" :key="addr.id">
                            <div class="p-4 bg-stone-50 rounded-2xl border border-stone-200/80 relative flex flex-col justify-between group">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm">
                                                <template x-if="addr.type === 'casa'"><span>🏠</span></template>
                                                <template x-if="addr.type === 'trabajo'"><span>💼</span></template>
                                                <template x-if="addr.type === 'otro'"><span>📍</span></template>
                                            </span>
                                            <span class="font-bold text-xs text-gray-900 capitalize" x-text="addr.type"></span>
                                        </div>
                                        <template x-if="addr.is_default">
                                            <span class="text-[9px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">
                                                Predeterminada
                                            </span>
                                        </template>
                                    </div>
                                    <p class="text-xs font-semibold text-gray-800" x-text="addr.address"></p>
                                    <p class="text-[11px] text-gray-500" x-text="(addr.city || '') + (addr.state ? ', ' + addr.state : '')"></p>
                                    <template x-if="addr.reference">
                                        <p class="text-[10px] text-gray-400 italic mt-1" x-text="'Ref: ' + addr.reference"></p>
                                    </template>
                                </div>

                                <div class="mt-3 pt-2 border-t border-stone-200/50 flex justify-end">
                                    <button @click="deleteAddress(addr.id)" class="text-[11px] text-red-500 hover:text-red-700 font-semibold">
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- 5. TAB: RASTREAR PEDIDO EXPRESS (PUBLIC O LOGGED IN) --}}
            <template x-if="activeTab === 'track'">
                <div class="space-y-4 max-w-md mx-auto py-2">
                    <div class="text-center mb-4">
                        <h3 class="font-bold text-gray-900 text-xl">Rastreo de Paquete</h3>
                        <p class="text-xs text-gray-500 mt-1">Ingresa el código único de tu orden para ver el avance del despacho en tiempo real.</p>
                    </div>

                    <form @submit.prevent="doTrack" class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Número de Pedido *</label>
                            <input type="text" x-model="trackData.order_number" required placeholder="Ej. PED-998822"
                                   class="w-full bg-stone-50 border border-stone-300 rounded-xl px-4 py-2.5 text-sm font-mono uppercase focus:bg-white focus:border-[#C8A68B] outline-none">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Correo de compra (Opcional)</label>
                            <input type="email" x-model="trackData.email" placeholder="tu@correo.com"
                                   class="w-full bg-stone-50 border border-stone-300 rounded-xl px-4 py-2.5 text-sm focus:bg-white focus:border-[#C8A68B] outline-none">
                        </div>

                        <button type="submit" :disabled="trackLoading"
                                class="w-full py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm disabled:opacity-50">
                            <span x-show="trackLoading" class="animate-spin">⏳</span>
                            <span x-text="trackLoading ? 'Consultando...' : 'Consultar Estado del Paquete'"></span>
                        </button>
                    </form>

                    <div x-show="trackError" class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl">
                        <span x-text="trackError"></span>
                    </div>

                    {{-- Track Result Card --}}
                    <template x-if="trackResult">
                        <div class="mt-4 p-4 bg-stone-50 rounded-2xl border border-stone-200 space-y-3">
                            <div class="flex justify-between items-start border-b border-stone-200 pb-2">
                                <div>
                                    <span class="font-mono font-bold text-sm text-gray-900" x-text="trackResult.order_number"></span>
                                    <p class="text-[11px] text-gray-500" x-text="trackResult.store_name + ' • ' + trackResult.created_at"></p>
                                </div>
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800" x-text="trackResult.status_label"></span>
                            </div>

                            {{-- Mini Stepper --}}
                            <div class="py-2">
                                <div class="flex items-center justify-between text-[10px] font-bold text-gray-500">
                                    <span :class="trackResult.status_step >= 1 ? 'text-emerald-700' : ''">1. Recibido</span>
                                    <span>➔</span>
                                    <span :class="trackResult.status_step >= 2 ? 'text-emerald-700' : ''">2. Confirmado</span>
                                    <span>➔</span>
                                    <span :class="trackResult.status_step >= 3 ? 'text-emerald-700' : ''">3. Preparando</span>
                                    <span>➔</span>
                                    <span :class="trackResult.status_step >= 4 ? 'text-emerald-700' : ''">4. En camino</span>
                                    <span>➔</span>
                                    <span :class="trackResult.status_step >= 5 ? 'text-emerald-700' : ''">5. Entregado</span>
                                </div>
                            </div>

                            <div class="text-xs text-gray-600 pt-1">
                                <p class="font-semibold mb-1">Productos:</p>
                                <template x-for="it in trackResult.items" :key="it.name">
                                    <div class="flex justify-between text-[11px] text-gray-500">
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

        {{-- Modal Footer --}}
        <div class="px-6 py-3 bg-stone-50 border-t border-stone-100 flex items-center justify-between text-[11px] text-gray-400">
            <span class="flex items-center gap-1 font-medium">
                🛡️ Compras protegidas por <strong class="text-gray-700">Tribio</strong>
            </span>
            <span class="text-[10px]">Cuenta Universal de Comprador</span>
        </div>

    </div>
</div>
