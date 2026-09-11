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
             country: '{{ request()->cookie('user_country') ?? 'PE' }}', 
             state: '', 
             city: '', 
             zipcode: '', 
             notes: '', 
             express_shipping: false,
             create_account: false,
             password: '',
             payment_method: '{{ ($store->checkout_mode === 'card' && ($store->mp_access_token || $store->gateway_access_token)) ? 'mercadopago' : 'whatsapp' }}'
         },
         customerLoggedIn: false,
         customerUser: null,
         customerAddresses: [],
         emailCheckStatus: null,
         emailTimer: null,
         storeSlug: '{{ $store->slug }}',
         isExpressEnabled: {{ $store->is_express_shipping_enabled ? 'true' : 'false' }},
         expressCost: {{ $store->express_shipping_cost ?? 0 }},
         currencySymbol: '{{ request()->cookie('user_country') === 'US' ? '$' : 'S/' }}',
         shippingCost: 0,
         hasMercadoPago: {{ ($store->mp_access_token || $store->gateway_access_token) ? 'true' : 'false' }},
         paymentMethod: '{{ ($store->checkout_mode === 'card' && ($store->mp_access_token || $store->gateway_access_token)) ? 'mercadopago' : 'whatsapp' }}',
         cartItems: window.TribioCart ? window.TribioCart.items : [],
         get cartTotal() { 
             let total = this.cartItems.reduce((acc, item) => acc + (item.price * item.quantity), 0);
             if (this.customer.express_shipping) total += this.expressCost;
             total += this.shippingCost;
             return total;
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
                 if (!this.customer.name) this.customer.name = data.user.name || '';
                 if (!this.customer.email) this.customer.email = data.user.email || '';
                 if (!this.customer.phone) this.customer.phone = data.user.phone || '';
             }
             if (this.customerAddresses.length > 0 && !this.customer.address) {
                 const def = this.customerAddresses.find(a => a.is_default) || this.customerAddresses[0];
                 if (def) this.selectSavedAddress(def);
             }
         },
         onCustomerLogout() {
             this.customerLoggedIn = false;
             this.customerUser = null;
             this.customerAddresses = [];
             this.emailCheckStatus = null;
         },
         selectSavedAddress(addr) {
             this.customer.address = addr.address || '';
             this.customer.city = addr.city || '';
             this.customer.state = addr.state || '';
             this.customer.zipcode = addr.zipcode || '';
             this.customer.address_type = addr.type || 'casa';
             if (addr.country) this.customer.country = addr.country;
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
             this.updateShipping();
             this.checkCurrentCustomer();
         },
         submitOrder() {
             if(!this.customer.name || !this.customer.phone || !this.customer.email) {
                 alert('Por favor completa los campos obligatorios (Nombre, Teléfono y Correo).');
                 return;
             }
             if (this.customer.create_account && (!this.customer.password || this.customer.password.length < 6)) {
                 alert('Para crear tu cuenta, la contraseña debe tener al menos 6 caracteres.');
                 return;
             }
             this.customer.payment_method = this.paymentMethod;
             if(window.TribioCart) {
                 const btn = document.getElementById('btnSubmitOrder');
                 if(btn) {
                     btn.innerText = 'Procesando...';
                     btn.disabled = true;
                 }
                 window.TribioCart.checkout(this.storeSlug, this.customer);
             }
         }
     }"
     @cart-updated.window="cartItems = $event.detail"
     @customer-authenticated.window="onCustomerAuth($event.detail)"
     @customer-logged-out.window="onCustomerLogout()"
     style="display:none; position: fixed; inset: 0; z-index: 999; justify-content: flex-end;">
    <div style="background: rgba(0,0,0,0.5);" class="absolute inset-0" onclick="document.getElementById('cartDrawer').style.display='none'"></div>
    <div class="relative w-full max-w-md h-full flex flex-col bg-white border-l border-gray-200 shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h3 class="text-gray-900 font-bold text-lg" x-text="checkoutStep === 1 ? '🛒 Mi carrito' : 'Finalizar Compra'"></h3>
            <button onclick="document.getElementById('cartDrawer').style.display='none'" class="text-gray-400 hover:text-gray-700 text-xl font-bold">✕</button>
        </div>
        
        <div class="flex-1 p-5 overflow-y-auto">
            <template x-if="cartItems.length === 0">
                <p class="text-gray-400 text-sm text-center mt-8">Tu carrito está vacío.</p>
            </template>

            <template x-if="cartItems.length > 0 && checkoutStep === 1">
                <div class="space-y-4">
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
                                    <p class="text-[#C8A68B] font-bold text-sm" x-text="currencySymbol + ' ' + (item.price * item.quantity).toFixed(2)"></p>
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
                    
                    {{-- Saved addresses selector if logged in --}}
                    <template x-if="customerLoggedIn && customerAddresses.length > 0">
                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200/80">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-bold text-gray-600 uppercase tracking-wider">📍 Direcciones Guardadas:</span>
                                <button type="button" @click="window.openCustomerModal('addresses')" class="text-[10px] text-gray-500 hover:text-gray-800 underline font-semibold">Administrar</button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="addr in customerAddresses" :key="addr.id">
                                    <button type="button" @click="selectSavedAddress(addr)"
                                            :class="customer.address === addr.address ? 'bg-[#1A1A1A] text-white shadow-xs' : 'bg-white text-gray-700 border border-stone-200 hover:bg-stone-100'"
                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold flex items-center gap-1 transition">
                                        <span x-text="addr.type === 'casa' ? '🏠' : (addr.type === 'trabajo' ? '💼' : '📍')"></span>
                                        <span x-text="addr.title || addr.type"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Nombre Completo *</label>
                        <input type="text" x-model="customer.name" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition" required>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Correo Electrónico *</label>
                            <input type="email" x-model="customer.email" @input="onEmailInput" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition" required>
                            
                            {{-- State: registered / new customer --}}
                            <template x-if="emailCheckStatus === 'exists' && !customerLoggedIn">
                                <div class="mt-1.5 p-2 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-900 flex items-center justify-between">
                                    <span>👋 Ya tienes cuenta Tribio Pass.</span>
                                    <button type="button" @click="window.openCustomerModal('login')" class="font-bold underline text-amber-950">Ingresar</button>
                                </div>
                            </template>

                            <template x-if="emailCheckStatus === 'admin_exists' && !customerLoggedIn">
                                <div class="mt-1.5 p-2 bg-blue-50 border border-blue-200 rounded-lg text-[11px] text-blue-900">
                                    <span>🏪 Correo de administrador. Puedes comprar como invitado o usar otro correo de cliente.</span>
                                </div>
                            </template>

                            <template x-if="emailCheckStatus === 'new' && !customerLoggedIn">
                                <div class="mt-1.5 flex items-center gap-1.5 text-[11px] font-bold text-emerald-700">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span>✨ Cliente nuevo no registrado</span>
                                </div>
                            </template>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Teléfono (WhatsApp) *</label>
                            <input type="tel" x-model="customer.phone" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">País *</label>
                            <select x-model="customer.country" @change="updateShipping" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition">
                                <option value="PE">🇵🇪 Perú</option>
                                <option value="US">🇺🇸 Estados Unidos</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Estado / Departamento</label>
                            <input type="text" x-model="customer.state" @blur="updateShipping" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-gray-500">Dirección de Envío *</label>
                            <div class="flex gap-1">
                                <button type="button" @click="customer.address_type = 'casa'"
                                        :class="customer.address_type === 'casa' ? 'bg-[#1A1A1A] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold transition">🏠 Casa</button>
                                <button type="button" @click="customer.address_type = 'trabajo'"
                                        :class="customer.address_type === 'trabajo' ? 'bg-[#1A1A1A] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold transition">💼 Trabajo</button>
                                <button type="button" @click="customer.address_type = 'otro'"
                                        :class="customer.address_type === 'otro' ? 'bg-[#1A1A1A] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold transition">📍 Otro</button>
                            </div>
                        </div>
                        <input type="text" x-model="customer.address" placeholder="Av., Calle, Número o Dpto" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition" required>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Ciudad</label>
                            <input type="text" x-model="customer.city" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1">Código Postal</label>
                            <input type="text" x-model="customer.zipcode" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition">
                        </div>
                    </div>

                    {{-- Option: Crear cuenta para futuras compras --}}
                    <template x-if="!customerLoggedIn">
                        <div class="p-3 bg-stone-50 rounded-xl border border-stone-200 mt-2">
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" x-model="customer.create_account" class="mt-0.5 accent-[#1A1A1A] w-4 h-4 rounded">
                                <div class="flex-1">
                                    <p class="font-bold text-xs text-gray-900">Crear cuenta para futuras compras</p>
                                    <p class="text-[11px] text-gray-500 mt-0.5">Guarda tus direcciones y rastrea tus pedidos en todas las tiendas Tribio con una sola cuenta.</p>
                                </div>
                            </label>
                            
                            <div x-show="customer.create_account" class="mt-2.5 pt-2.5 border-t border-stone-200/80">
                                <label class="block text-[11px] font-bold text-gray-600 mb-1">Crea tu contraseña *</label>
                                <input type="password" x-model="customer.password" placeholder="Mínimo 6 caracteres" minlength="6"
                                       class="w-full bg-white border border-gray-300 rounded-lg px-3 py-2 text-xs focus:border-[#C8A68B] outline-none">
                            </div>
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
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1 mt-2">Notas adicionales</label>
                        <textarea x-model="customer.notes" rows="2" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-[#C8A68B] focus:ring-1 focus:ring-[#C8A68B] outline-none transition"></textarea>
                    </div>
                </div>
            </template>
        </div>
        
        <template x-if="cartItems.length > 0">
            <div class="p-5 border-t border-gray-100 bg-gray-50">
                <div class="flex justify-between items-center mb-2 text-gray-600 text-sm">
                    <span>Subtotal:</span>
                    <span x-text="currencySymbol + ' ' + (cartTotal - shippingCost - (customer.express_shipping ? expressCost : 0)).toFixed(2)"></span>
                </div>
                
                <template x-if="shippingCost > 0">
                    <div class="flex justify-between items-center mb-2 text-gray-600 text-sm">
                        <span>Envío:</span>
                        <span x-text="'+ ' + currencySymbol + ' ' + shippingCost.toFixed(2)"></span>
                    </div>
                </template>

                <div class="flex justify-between items-center mb-4 text-gray-800 border-t border-gray-200 pt-2 mt-2">
                    <span class="font-bold text-sm">Total a pagar:</span>
                    <span class="font-black text-xl text-[#C8A68B]" x-text="currencySymbol + ' ' + cartTotal.toFixed(2)"></span>
                </div>
                
                <template x-if="checkoutStep === 1">
                    <button @click="checkoutStep = 2" class="w-full py-3 rounded-xl font-bold text-white transition-all shadow-md text-center flex items-center justify-center gap-2 bg-[#1A1A1A] hover:bg-[#C8A68B]">
                        Siguiente Paso →
                    </button>
                </template>
                
                <template x-if="checkoutStep === 2">
                    <div class="flex gap-2">
                        <button @click="checkoutStep = 1" class="px-4 py-3 bg-white border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">
                            ←
                        </button>
                        <button id="btnSubmitOrder" @click="submitOrder" class="flex-1 py-3 bg-[#1A1A1A] hover:bg-[#C8A68B] rounded-xl font-bold text-white transition-colors shadow-md text-center flex items-center justify-center gap-2">
                            <span x-text="paymentMethod === 'mercadopago' ? '💳 Pagar con Tarjeta' : 'Confirmar Pedido'"></span>
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>