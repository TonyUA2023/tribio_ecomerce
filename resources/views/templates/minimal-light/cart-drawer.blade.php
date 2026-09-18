{{-- Cart Drawer Component for Minimal-Light --}}
<script>window.tribioCsrfToken = '{{ csrf_token() }}';</script>
<div id="cartDrawer" x-data="{
         checkoutStep: 1,
         customer: { 
             _token: '{{ csrf_token() }}', 
             name: '', 
             email: '', 
             phone: '', 
             address: '', 
             address_type: 'casa',
             country: '{{ \App\Helpers\CurrencyHelper::currentCountry() }}', 
             state: '', 
             city: '', 
             zipcode: '', 
             notes: '', 
             express_shipping: false,
             create_account: false,
             password: '',
             payment_method: '{{ ($store->checkout_mode === 'card' || ($store->checkout_mode === 'mixed' && !empty($store->mp_access_token))) ? 'mercadopago' : 'whatsapp' }}'
         },
         customerLoggedIn: false,
         customerUser: null,
         customerAddresses: [],
         selectedAddressId: null,
         showInlineLogin: false,
         loginEmail: '',
         loginPassword: '',
         loginError: null,
         loggingIn: false,
         emailCheckStatus: null,
         emailTimer: null,
         storeSlug: '{{ $store->slug }}',
         isExpressEnabled: {{ $store->is_express_shipping_enabled ? 'true' : 'false' }},
         expressCost: {{ $store->express_shipping_cost ?? 0 }},
         currentCurrency: '{{ \App\Helpers\CurrencyHelper::currentCurrency() }}',
         currencySymbol: '{{ \App\Helpers\CurrencyHelper::symbol() }}',
         shippingCost: 0,
         hasMercadoPago: {{ (in_array($store->checkout_mode, ['card', 'mixed']) && (!empty($store->mp_access_token) || !empty($store->gateway_access_token))) ? 'true' : 'false' }},
         hasWhatsapp: {{ in_array($store->checkout_mode, ['whatsapp', 'mixed']) ? 'true' : 'false' }},
         paymentMethod: '{{ ($store->checkout_mode === 'card' || ($store->checkout_mode === 'mixed' && !empty($store->mp_access_token))) ? 'mercadopago' : 'whatsapp' }}',
         hasActiveToken: {{ (!empty($store->mp_access_token) || !empty($store->gateway_access_token)) ? 'true' : 'false' }},
         cartItems: window.TribioCart ? window.TribioCart.items : [],
         formatMoney(amount) {
             const isInt = ['COP', 'CLP', 'ARS'].includes(this.currentCurrency);
             const num = Number(amount || 0);
             return this.currencySymbol + ' ' + (isInt ? Math.round(num).toLocaleString('en-US') : num.toFixed(2));
         },
         init() {
             this.updateShipping();
             this.checkCurrentCustomer();
         },
         get cartTotal() { 
             let total = this.cartItems.reduce((acc, item) => acc + (item.price * item.quantity), 0);
             if (this.customer.express_shipping) total += this.expressCost;
             total += this.shippingCost;
             return total;
         },
         openTribioPass(tab = 'register') {
              const extra = {
                  email: this.customer.email || '',
                  name: this.customer.name || '',
                  phone: this.customer.phone || '',
                  address: this.customer.address || '',
                  city: this.customer.city || '',
                  state: this.customer.state || '',
                  country: this.customer.country || '{{ \App\Helpers\CurrencyHelper::currentCountry() }}',
                  fromCheckout: true
              };
              if (window.openCustomerModal) {
                  window.openCustomerModal(tab, extra);
              } else {
                  window.dispatchEvent(new CustomEvent('open-customer-modal', {
                      detail: { tab: tab, ...extra }
                  }));
              }
          },
         toggleInlineLogin() {
             this.showInlineLogin = !this.showInlineLogin;
             if (this.showInlineLogin && !this.loginEmail && this.customer.email) {
                 this.loginEmail = this.customer.email;
             }
             this.loginError = null;
         },
         async quickLogin() {
             this.loginError = null;
             if (!this.loginEmail || !this.loginPassword) {
                 this.loginError = 'Por favor ingresa tu correo y contraseña.';
                 return;
             }
             this.loggingIn = true;
             try {
                 const res = await fetch('/customer/login', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'Accept': 'application/json',
                         'X-CSRF-TOKEN': window.tribioCsrfToken
                     },
                     body: JSON.stringify({
                         email: this.loginEmail,
                         password: this.loginPassword
                     })
                 });
                 const data = await res.json();
                 if (res.ok && data.success && data.user) {
                     this.onCustomerAuth(data);
                     this.showInlineLogin = false;
                     this.loginPassword = '';
                     window.dispatchEvent(new CustomEvent('customer-authenticated', { detail: data }));
                 } else {
                     this.loginError = data.message || 'Correo o contraseña incorrectos.';
                 }
             } catch (e) {
                 this.loginError = 'Error de conexión con el servidor.';
             } finally {
                 this.loggingIn = false;
             }
         },
         async logoutCustomer() {
             try {
                 await fetch('/customer/logout', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'Accept': 'application/json',
                         'X-CSRF-TOKEN': window.tribioCsrfToken
                     }
                 });
             } catch (e) {
                 console.error(e);
             }
             this.onCustomerLogout();
             window.dispatchEvent(new CustomEvent('customer-logged-out'));
         },
         updateShipping() {
             if(!this.customer.country) return;
             fetch(`/api/shipping-cost/${this.storeSlug}?country=${this.customer.country}&state=${this.customer.state}`)
                 .then(res => res.json())
                 .then(data => {
                     this.shippingCost = parseFloat(data.cost) || 0;
                 }).catch(() => this.shippingCost = 0);
         },
         async checkCurrentCustomer() {
             try {
                 const res = await fetch('/customer/current', {
                     headers: { 'Accept': 'application/json' }
                 });
                 const data = await res.json();
                 if (data.authenticated && data.user) {
                     this.onCustomerAuth(data);
                 }
             } catch (e) {
                 console.error(e);
             }
         },
         onCustomerAuth(data) {
             this.customerLoggedIn = true;
             this.customerUser = data.user;
             this.customerAddresses = data.addresses || [];
             if (data.user) {
                 if (!this.customer.name || this.customer.name.trim() === '') this.customer.name = data.user.name || '';
                 if (!this.customer.email || this.customer.email.trim() === '') this.customer.email = data.user.email || '';
                 if (!this.customer.phone || this.customer.phone.trim() === '') this.customer.phone = data.user.phone || '';
             }
             if (this.customerAddresses.length > 0) {
                 const def = this.customerAddresses.find(a => a.is_default) || this.customerAddresses[0];
                 if (def) this.selectSavedAddress(def);
             }
         },
         onCustomerLogout() {
             this.customerLoggedIn = false;
             this.customerUser = null;
             this.customerAddresses = [];
             this.selectedAddressId = null;
             this.emailCheckStatus = null;
             this.showInlineLogin = false;
         },
         selectSavedAddress(addr) {
             this.selectedAddressId = addr.id;
             this.customer.address = addr.address || '';
             this.customer.city = addr.city || '';
             this.customer.state = addr.state || '';
             this.customer.zipcode = addr.zipcode || '';
             this.customer.address_type = addr.type || 'casa';
             // El país siempre permanece bloqueado a la navegación activa de la tienda
             this.customer.country = '{{ \App\Helpers\CurrencyHelper::currentCountry() }}';
             this.updateShipping();
         },
         selectNewAddress() {
             this.selectedAddressId = 'new';
             this.customer.address = '';
             this.customer.city = '';
             this.customer.state = '';
             this.customer.zipcode = '';
             this.customer.address_type = 'casa';
             this.customer.country = '{{ \App\Helpers\CurrencyHelper::currentCountry() }}';
             this.updateShipping();
         },
         onEmailInput() {
             clearTimeout(this.emailTimer);
             if (!this.customer.email || !this.customer.email.includes('@')) {
                 this.emailCheckStatus = null;
                 return;
             }
             this.emailCheckStatus = 'checking';
             this.emailTimer = setTimeout(async () => {
                 try {
                     const res = await fetch('/customer/check-email', {
                         method: 'POST',
                         headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                         body: JSON.stringify({ email: this.customer.email })
                     });
                     const data = await res.json();
                    if (data.exists) {
                        this.emailCheckStatus = data.is_cliente ? 'exists' : 'admin_exists';
                    } else {
                        this.emailCheckStatus = 'new';
                    }
                } catch (e) {
                    this.emailCheckStatus = null;
                }
            }, 450);
        },
         init() {
             this.customer.country = '{{ \App\Helpers\CurrencyHelper::currentCountry() }}';
             this.updateShipping();
             this.checkCurrentCustomer();
         },
         submitOrder() {
             if (!this.customerLoggedIn) {
                 this.openTribioPass('register');
                 return;
             }
             if(!this.customer.name || !this.customer.phone || !this.customer.email) {
                 alert('Por favor completa los campos obligatorios (Nombre, Teléfono y Correo).');
                 return;
             }
              if (this.paymentMethod === 'mercadopago' && !this.hasActiveToken) {
                  alert('La tienda tiene habilitado el pago con tarjeta pero aún no ha configurado sus credenciales de Mercado Pago en el panel. Por favor selecciona WhatsApp / Pago Directo o ingresa tus credenciales en Configurar Tienda.');
                  return;
              }
              this.customer.payment_method = this.paymentMethod;

             if (this.paymentMethod === 'mercadopago') {
                 this.checkoutStep = 3;
                 this.$nextTick(() => {
                     this.initPaymentBrick();
                 });
             } else {
                 if(window.TribioCart) {
                     const btn = document.getElementById('btnSubmitOrder');
                     if(btn) { btn.innerText = 'Procesando...'; btn.disabled = true; }
                     window.TribioCart.checkout(this.storeSlug, this.customer);
                 }
             }
         },
         async initPaymentBrick() {
            if (this.brickController) {
                this.brickController.unmount();
            }
            if (!window.MercadoPago) {
                alert('Error al cargar Mercado Pago SDK.');
                return;
            }
            const mp = new window.MercadoPago('{{ $store->mp_public_key ?? $store->gateway_public_key }}', { locale: 'es-PE' });
            const bricksBuilder = mp.bricks();
            const settings = {
                initialization: { amount: this.cartTotal },
                customization: {
                    visual: { style: { theme: 'default', customVariables: { formBackgroundColor: '#f9fafb' } } },
                    paymentMethods: { 
                        creditCard: 'all', 
                        debitCard: 'all', 
                        maxInstallments: 1 
                    }
                },
                callbacks: {
                    onReady: () => {},
                    onSubmit: (formData) => {
                        return new Promise((resolve, reject) => {
                            this.customer.payment_method = 'mercadopago';
                            this.customer.mp_form_data = formData;
                            window.TribioCart.checkout(this.storeSlug, this.customer)
                                .then(() => resolve())
                                .catch(() => reject());
                        });
                    },
                    onError: (error) => { console.error(error); }
                }
            };
            this.brickController = await bricksBuilder.create('payment', 'paymentBrick_container', settings);
         }
     }"
     @cart-updated.window="cartItems = $event.detail"
     @customer-authenticated.window="onCustomerAuth($event.detail)"
     @customer-logged-out.window="onCustomerLogout()"
     style="display:none; position: fixed; inset: 0; z-index: 999; justify-content: flex-end;">
    <div style="background: rgba(0,0,0,0.5);" class="absolute inset-0" onclick="document.getElementById('cartDrawer').style.display='none'"></div>
    <div class="relative w-full max-w-md h-full flex flex-col bg-white border-l border-gray-200 shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h3 class="text-gray-900 font-bold text-lg" x-text="checkoutStep === 1 ? '{{ \App\Helpers\TranslationHelper::trans('your_cart', '🛒 Mi carrito') }}' : '{{ \App\Helpers\TranslationHelper::trans('checkout', 'Finalizar Compra') }}'"></h3>
            <button onclick="document.getElementById('cartDrawer').style.display='none'" class="text-gray-400 hover:text-gray-700 text-xl font-bold">✕</button>
        </div>
        
        <div class="flex-1 p-5 overflow-y-auto">
            <template x-if="cartItems.length === 0">
                <p class="text-gray-400 text-sm text-center mt-8">{{ \App\Helpers\TranslationHelper::trans('empty_cart', 'Tu carrito está vacío.') }}</p>
            </template>

            <template x-if="cartItems.length > 0 && checkoutStep === 1">
                <div class="space-y-4">
                    {{-- Notice in Step 1 --}}
                    <template x-if="!customerLoggedIn">
                        <div class="p-3 bg-amber-50/90 border border-amber-200 rounded-xl flex items-center justify-between text-xs text-stone-800">
                            <div class="flex items-center gap-2">
                                <span class="text-base">🔑</span>
                                <span class="font-bold">Tribio Pass requerido para comprar</span>
                            </div>
                            <div class="flex items-center gap-1.5 font-bold">
                                <button type="button" @click="openTribioPass('login')" class="underline text-stone-900 hover:text-[#C8A68B] cursor-pointer">
                                    Ingresar
                                </button>
                                <span>/</span>
                                <button type="button" @click="openTribioPass('register')" class="underline text-stone-900 hover:text-[#C8A68B] cursor-pointer">
                                    Registrarse
                                </button>
                            </div>
                        </div>
                    </template>
                    <template x-if="customerLoggedIn">
                        <div class="p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs text-emerald-900">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span>Comprador: <strong x-text="customerUser?.name"></strong></span>
                            </div>
                            <button type="button" @click="logoutCustomer()" class="text-[10px] text-stone-500 underline hover:text-stone-800 cursor-pointer">Cerrar sesión</button>
                        </div>
                    </template>
                    <template x-for="(item, index) in cartItems" :key="index">
                        <div class="flex gap-4 p-3 bg-gray-50 rounded-xl border border-gray-100 items-center">
                            <template x-if="item.image">
                                <img :src="item.image" class="w-16 h-16 object-cover rounded-lg">
                            </template>
                            <template x-if="!item.image">
                                <div class="w-16 h-16 bg-white border border-gray-200 rounded-lg flex items-center justify-center text-xl shadow-sm">📦</div>
                            </template>
                            <div class="flex-1">
                                <h4 class="text-gray-800 font-semibold text-sm leading-tight" x-text="item.name"></h4>
                                <template x-if="item.variant_title">
                                    <p class="text-[11px] text-[#C8A68B] font-semibold mt-0.5" x-text="item.variant_title"></p>
                                </template>
                                <div class="flex justify-between items-center mt-2">
                                    <p class="text-[#C8A68B] font-bold text-sm" x-text="formatMoney(item.price * item.quantity)"></p>
                                    <div class="flex items-center gap-2 text-gray-600 text-xs bg-white rounded-full border border-gray-200 p-1">
                                        <button @click="window.TribioCart.updateQuantity(item.cartKey || item.id, item.quantity - 1)" class="w-5 h-5 rounded-full hover:bg-gray-100 flex items-center justify-center font-bold">-</button>
                                        <span x-text="item.quantity" class="w-4 text-center font-medium"></span>
                                        <button @click="window.TribioCart.updateQuantity(item.cartKey || item.id, item.quantity + 1)" class="w-5 h-5 rounded-full hover:bg-gray-100 flex items-center justify-center font-bold">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="cartItems.length > 0 && checkoutStep === 2">
                <div class="space-y-4 text-gray-700">
                    

                    {{-- Banner 2: Estado Autenticado del Comprador --}}
                    <template x-if="customerLoggedIn">
                        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center justify-between shadow-xs">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-black shadow-xs flex-shrink-0">
                                    ✓
                                </div>
                                <div>
                                    <p class="text-xs font-black text-emerald-950 flex items-center gap-1.5">
                                        <span>Conectado como:</span>
                                        <strong x-text="customerUser?.name"></strong>
                                    </p>
                                    <p class="text-[11px] text-emerald-700" x-text="customerUser?.email"></p>
                                </div>
                            </div>
                            <button type="button" @click="logoutCustomer()" class="text-[11px] text-stone-500 hover:text-stone-900 underline font-semibold cursor-pointer">
                                Cerrar sesión
                            </button>
                        </div>
                    </template>

                    {{-- Selector Visual de Direcciones Guardadas --}}
                    <template x-if="customerLoggedIn && customerAddresses.length > 0">
                        <div class="p-3.5 bg-stone-50 rounded-2xl border border-stone-200/90 shadow-xs space-y-2.5">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-black text-stone-900 uppercase tracking-wide flex items-center gap-1.5">
                                    <span>📍</span>
                                    <span>Selecciona dirección de entrega:</span>
                                </label>
                                <button type="button" @click="window.openCustomerModal ? window.openCustomerModal('addresses') : null" class="text-[11px] text-[#C8A68B] hover:text-[#b08e73] font-bold underline">
                                    + Administrar
                                </button>
                            </div>

                            <div class="space-y-2">
                                <template x-for="addr in customerAddresses" :key="addr.id">
                                    <div @click="selectSavedAddress(addr)"
                                         :class="(selectedAddressId === addr.id || (!selectedAddressId && customer.address === addr.address)) 
                                            ? 'border-2 border-[#1A1A1A] bg-white ring-2 ring-[#1A1A1A]/10 shadow-sm' 
                                            : 'border border-stone-200 bg-white hover:border-stone-400 hover:bg-stone-50/50'"
                                         class="p-3 rounded-xl transition cursor-pointer flex items-center justify-between">
                                        <div class="flex items-start gap-2.5">
                                            <span class="text-lg" x-text="addr.type === 'casa' ? '🏠' : (addr.type === 'trabajo' ? '💼' : '📍')"></span>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-black text-stone-900 uppercase" x-text="addr.title || addr.type"></span>
                                                    <template x-if="addr.is_default">
                                                        <span class="text-[9px] bg-stone-100 text-stone-600 px-1.5 py-0.2 rounded font-bold">Predeterminada</span>
                                                    </template>
                                                </div>
                                                <p class="text-xs text-stone-700 font-medium leading-snug mt-0.5" x-text="addr.address"></p>
                                                <p class="text-[11px] text-stone-500" x-text="[(addr.city || ''), (addr.state || ''), (addr.country || '')].filter(Boolean).join(', ')"></p>
                                            </div>
                                        </div>
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                             :class="(selectedAddressId === addr.id || (!selectedAddressId && customer.address === addr.address)) ? 'border-[#1A1A1A] bg-[#1A1A1A]' : 'border-stone-300'">
                                            <div x-show="(selectedAddressId === addr.id || (!selectedAddressId && customer.address === addr.address))" class="w-2 h-2 rounded-full bg-white"></div>
                                        </div>
                                    </div>
                                </template>

                                {{-- Opción para ingresar otra dirección distinta --}}
                                <div @click="selectNewAddress()"
                                     :class="selectedAddressId === 'new' ? 'border-2 border-[#1A1A1A] bg-white ring-2 ring-[#1A1A1A]/10 shadow-sm' : 'border border-dashed border-stone-300 bg-white/70 hover:border-stone-400'"
                                     class="p-2.5 rounded-xl transition cursor-pointer flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-xs font-bold text-stone-700">
                                        <span class="text-base">➕</span>
                                        <span>Enviar a una dirección diferente para este pedido</span>
                                    </div>
                                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                         :class="selectedAddressId === 'new' ? 'border-[#1A1A1A] bg-[#1A1A1A]' : 'border-stone-300'">
                                        <div x-show="selectedAddressId === 'new'" class="w-2 h-2 rounded-full bg-white"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Bloque Obligatorio: Crear Cuenta o Iniciar Sesión en Tribio Pass --}}
                    <template x-if="!customerLoggedIn">
                        <div class="p-4 bg-gradient-to-br from-[#1A1A1A] via-stone-900 to-[#1A1A1A] text-white rounded-2xl shadow-md mt-3 border border-stone-700/70 relative overflow-hidden">
                            <div class="absolute -right-3 -bottom-3 opacity-10 pointer-events-none text-7xl select-none">
                                🔑
                            </div>
                            <div class="relative z-10 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-full bg-[#C8A68B]/20 text-[#C8A68B] flex items-center justify-center text-xs font-black">✓</span>
                                        <span class="text-xs font-black uppercase tracking-wider text-[#C8A68B]">Tribio Pass Requerido</span>
                                    </div>
                                    <span class="text-[10px] bg-amber-400 text-stone-950 font-black px-2 py-0.5 rounded-full uppercase tracking-wider">Obligatorio</span>
                                </div>
                                <p class="text-xs text-stone-300 leading-snug">
                                    Para proceder al pago y gestionar tus direcciones de envío debes iniciar sesión o crear tu cuenta universal <strong>Tribio Pass</strong> en segundos.
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-0.5">
                                    <button type="button" @click="openTribioPass('register')"
                                            class="w-full py-2.5 px-3 bg-[#C8A68B] hover:bg-[#b08e73] text-stone-950 font-black rounded-xl text-xs transition shadow-sm flex items-center justify-center gap-1.5 cursor-pointer">
                                        <span>✨ Crear Cuenta</span>
                                    </button>
                                    <button type="button" @click="openTribioPass('login')"
                                            class="w-full py-2.5 px-3 bg-white/10 hover:bg-white/20 text-white border border-white/20 font-bold rounded-xl text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                                        <span>🔑 Iniciar Sesión</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="customerLoggedIn">
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 mb-1">Teléfono (WhatsApp) *</label>
                                    <input type="tel" name="tel" autocomplete="tel" x-model="customer.phone" class="w-full bg-stone-100 border border-stone-200 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition" required>
                                </div>
                            </div>

                            {{-- Formulario para nueva dirección solo si selecciona 'new' --}}
                            <template x-if="selectedAddressId === 'new'">
                                <div class="space-y-4 p-4 border border-stone-200 rounded-2xl bg-stone-50 mt-2">
                                    <h4 class="text-xs font-black text-stone-800 uppercase">Nueva Dirección de Entrega</h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <div class="flex items-center justify-between mb-1">
                                                <label class="block text-xs font-bold text-gray-700">País de Envío *</label>
                                            </div>
                                            @php
                                                $checkoutCountryCode = \App\Helpers\CurrencyHelper::currentCountry();
                                                $checkoutCountryData = \App\Helpers\CurrencyHelper::getCountryInfo($checkoutCountryCode);
                                            @endphp
                                            <div class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-sm flex items-center justify-between select-none" title="País autoseleccionado">
                                                <div class="flex items-center gap-2 overflow-hidden">
                                                    <div class="w-6 h-4 rounded-xs overflow-hidden border border-stone-300 shadow-2xs flex-shrink-0 bg-white">
                                                        <img src="{{ $checkoutCountryData['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($checkoutCountryCode) }}" 
                                                             alt="{{ $checkoutCountryData['name'] ?? $checkoutCountryCode }}" 
                                                             class="w-full h-full object-cover">
                                                    </div>
                                                    <span class="font-bold text-gray-900 text-xs truncate">{{ $checkoutCountryData['name'] ?? $checkoutCountryCode }}</span>
                                                </div>
                                            </div>
                                            <input type="hidden" name="country" :value="customer.country" value="{{ $checkoutCountryCode }}">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-700 mb-1">Estado / Departamento</label>
                                            <input type="text" name="address-level1" autocomplete="address-level1" x-model="customer.state" @blur="updateShipping" placeholder="Ej: Lima, CDMX..." class="w-full bg-white border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm focus:border-[#C8A68B] outline-none transition" required>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label class="block text-xs font-bold text-gray-500">Dirección de Envío *</label>
                                            <div class="flex gap-1">
                                                <button type="button" @click="customer.address_type = 'casa'"
                                                        :class="customer.address_type === 'casa' ? 'bg-[#1A1A1A] text-white' : 'bg-white text-gray-600 border border-stone-200'"
                                                        class="px-2 py-0.5 rounded text-[10px] font-bold transition">🏠 Casa</button>
                                                <button type="button" @click="customer.address_type = 'trabajo'"
                                                        :class="customer.address_type === 'trabajo' ? 'bg-[#1A1A1A] text-white' : 'bg-white text-gray-600 border border-stone-200'"
                                                        class="px-2 py-0.5 rounded text-[10px] font-bold transition">💼 Trabajo</button>
                                            </div>
                                        </div>
                                        <input type="text" name="street-address" autocomplete="street-address" x-model="customer.address" placeholder="Av., Calle, Número o Dpto" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] outline-none transition" required>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 mb-1">Ciudad</label>
                                            <input type="text" name="address-level2" autocomplete="address-level2" x-model="customer.city" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] outline-none transition" required>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-gray-500 mb-1">Código Postal</label>
                                            <input type="text" name="postal_code" autocomplete="postal-code" x-model="customer.zipcode" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] outline-none transition">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>


                    <template x-if="isExpressEnabled">
                        <div class="p-4 bg-green-50 border border-green-200 rounded-xl mt-4 relative overflow-hidden">
                            <div class="absolute right-0 top-0 bottom-0 opacity-10">
                                <svg class="w-16 h-16 text-green-600 transform translate-x-2 -translate-y-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <label class="flex items-start gap-3 cursor-pointer relative z-10">
                                <input type="checkbox" x-model="customer.express_shipping" class="mt-1 accent-green-600 w-4 h-4 rounded">
                                <div>
                                    <p class="font-bold text-sm text-green-700 flex items-center gap-1">🚀 ¡Quiero Envío Express!</p>
                                    <p class="text-xs text-green-600/80 mt-1">Llega más rápido a tu domicilio. <span x-show="expressCost > 0" x-text="'+ ' + currencySymbol + ' ' + expressCost.toFixed(2)"></span><span x-show="expressCost == 0">¡Es gratis!</span></p>
                                </div>
                            </label>
                        </div>
                    </template>
                    
                    {{-- Método de Pago --}}
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Método de Pago</label>
                        
                        <div class="space-y-2.5">
                            {{-- Tarjeta Débito / Crédito con Mercado Pago --}}
                            <template x-if="hasMercadoPago">
                                <label :class="paymentMethod === 'mercadopago' ? 'border-[#1A1A1A] bg-stone-50 ring-1 ring-[#1A1A1A]' : 'border-gray-200 bg-white hover:border-gray-300'"
                                       class="flex flex-col p-3.5 rounded-xl border cursor-pointer transition-all">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <input type="radio" name="payment_method" value="mercadopago" x-model="paymentMethod" class="accent-[#1A1A1A] w-4 h-4">
                                            <span class="font-bold text-sm text-gray-900 flex items-center gap-1.5">
                                                💳 Tarjeta Débito / Crédito
                                            </span>
                                        </div>
                                        <span class="text-[10px] bg-blue-50 text-blue-700 font-bold px-1.5 py-0.5 rounded border border-blue-200">Mercado Pago</span>
                                    </div>
                                    <div class="mt-2 text-xs text-gray-500 pl-6 flex flex-col gap-1">
                                        <p class="text-[11px] text-gray-600">Paga al instante con Visa, Mastercard, AMEX o saldo de Mercado Pago.</p>
                                        <div class="flex items-center gap-1.5 text-[10px] text-emerald-700 font-semibold mt-0.5">
                                            <svg class="w-3.5 h-3.5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            <span>Cifrado SSL 256 bits. Tus datos bancarios están seguros.</span>
                                        </div>
                                    </div>
                                </label>
                            </template>

                            {{-- WhatsApp / Pago Directo --}}
                            <template x-if="hasWhatsapp">
                                <label :class="paymentMethod === 'whatsapp' ? 'border-[#1A1A1A] bg-stone-50 ring-1 ring-[#1A1A1A]' : 'border-gray-200 bg-white hover:border-gray-300'"
                                       class="flex flex-col p-3.5 rounded-xl border cursor-pointer transition-all">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <input type="radio" name="payment_method" value="whatsapp" x-model="paymentMethod" class="accent-[#1A1A1A] w-4 h-4">
                                            <span class="font-bold text-sm text-gray-900 flex items-center gap-1.5">
                                                💬 WhatsApp / Pago Directo
                                            </span>
                                        </div>
                                        <span class="text-[10px] bg-emerald-50 text-emerald-700 font-bold px-1.5 py-0.5 rounded border border-emerald-200">Yape / Plin / Efectivo</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-gray-500 pl-6">
                                        Coordina tu compra directamente con el vendedor por WhatsApp.
                                    </p>
                                </label>
                            </template>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 mt-2">Notas adicionales</label>
                        <textarea x-model="customer.notes" rows="2" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition"></textarea>
                    </div>
                </div>
            </template>
            <template x-if="checkoutStep === 3">
                <div class="flex flex-col gap-4 pb-4">
                    <!-- Botón Volver -->
                    <button @click="checkoutStep = 2" class="self-start flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-[#C8A68B] transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Volver a Datos de Envío
                    </button>

                    <!-- Resumen del Pago -->
                    <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 shadow-sm">
                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Resumen de tu compra</h4>
                        
                        <div class="flex justify-between items-center mb-2 text-gray-600 text-sm">
                            <span>{{ \App\Helpers\TranslationHelper::trans('subtotal', 'Subtotal') }}:</span>
                            <span x-text="formatMoney(cartTotal - shippingCost - (customer.express_shipping ? expressCost : 0))"></span>
                        </div>
                        
                        <template x-if="shippingCost > 0">
                            <div class="flex justify-between items-center mb-2 text-gray-600 text-sm">
                                <span>{{ \App\Helpers\TranslationHelper::trans('shipping', 'Envío') }}:</span>
                                <span x-text="'+ ' + formatMoney(shippingCost)"></span>
                            </div>
                        </template>

                        <div class="flex justify-between items-center text-gray-900 border-t border-gray-200 pt-3 mt-1">
                            <span class="font-bold text-base">Total a pagar:</span>
                            <span class="font-black text-2xl text-[#1A1A1A]" x-text="formatMoney(cartTotal)"></span>
                        </div>
                    </div>

                    <!-- Contenedor del Brick de Mercado Pago -->
                    <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                        <div class="bg-gray-50 border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                            <span class="font-bold text-sm text-gray-700">💳 Detalles de Pago</span>
                            <img src="https://logospng.org/download/mercado-pago/logo-mercado-pago-icono-1024.png" class="h-4 opacity-80" alt="Mercado Pago" />
                        </div>
                        <div class="p-2">
                            <div id="paymentBrick_container" class="w-full"></div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-center gap-1.5 text-[11px] text-gray-400 mt-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Pagos 100% seguros y encriptados</span>
                    </div>
                </div>
            </template>
        </div>
        
        <template x-if="cartItems.length > 0">
            <div x-show="checkoutStep !== 3" class="p-5 border-t border-gray-100 bg-gray-50">
                <div class="flex justify-between items-center mb-2 text-gray-600 text-sm">
                    <span>{{ \App\Helpers\TranslationHelper::trans('subtotal', 'Subtotal') }}:</span>
                    <span x-text="formatMoney(cartTotal - shippingCost - (customer.express_shipping ? expressCost : 0))"></span>
                </div>
                
                <template x-if="shippingCost > 0">
                    <div class="flex justify-between items-center mb-2 text-gray-600 text-sm">
                        <span>{{ \App\Helpers\TranslationHelper::trans('shipping', 'Envío') }}:</span>
                        <span x-text="'+ ' + formatMoney(shippingCost)"></span>
                    </div>
                </template>

                <div class="flex justify-between items-center mb-4 text-gray-800 border-t border-gray-200 pt-2 mt-2">
                    <span class="font-bold text-sm">{{ \App\Helpers\TranslationHelper::isEn() ? 'Total to pay:' : 'Total a pagar:' }}</span>
                    <span class="font-black text-xl text-[#C8A68B]" x-text="formatMoney(cartTotal)"></span>
                </div>
                
                <template x-if="checkoutStep === 1">
                    <button @click="checkoutStep = 2" class="w-full py-3 rounded-xl font-bold text-white transition-all shadow-md text-center flex items-center justify-center gap-2 bg-[#1A1A1A] hover:bg-[#C8A68B]">
                        {{ \App\Helpers\TranslationHelper::isEn() ? 'Next Step →' : 'Siguiente Paso →' }}
                    </button>
                </template>
                
                <template x-if="checkoutStep === 2">
                    <div class="flex gap-2">
                        <button @click="checkoutStep = 1" class="px-4 py-3 bg-white border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">
                            ←
                        </button>
                        <template x-if="!customerLoggedIn">
                            <button type="button" @click="openTribioPass('register')" class="flex-1 py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] rounded-xl font-black text-white transition-all shadow-md text-center flex items-center justify-center gap-2 cursor-pointer">
                                <span>🔑 Inicia Sesión o Crea Cuenta para Pagar</span>
                            </button>
                        </template>
                        <template x-if="customerLoggedIn">
                            <button id="btnSubmitOrder" @click="submitOrder" class="flex-1 py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] rounded-xl font-bold text-white transition-colors shadow-md text-center flex items-center justify-center gap-2">
                                <span x-text="paymentMethod === 'mercadopago' ? '{{ \App\Helpers\TranslationHelper::isEn() ? '💳 Continue to Payment' : '💳 Continuar al Pago' }}' : '{{ \App\Helpers\TranslationHelper::isEn() ? 'Confirm Order' : 'Confirmar Pedido' }}'"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
<script src="https://sdk.mercadopago.com/js/v2"></script>