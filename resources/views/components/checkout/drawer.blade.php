{{--
    Pasarela de pago estándar de Tribio.
    Un solo componente (carrito → envío/cuenta → pago) reutilizado por
    las 5 plantillas y por el constructor visual. Se abre disparando el
    evento global `open-cart-drawer` (igual patrón que `open-customer-modal`).
    Requiere: $store en el scope (todas las vistas de tienda ya lo tienen).
--}}
@php
    $payAccent = $store->accent_color ?: config("tribio.templates.{$store->template_name}.default_accent", '#1A1A1A');
    $paySecondary = $store->secondary_color ?: config("tribio.templates.{$store->template_name}.default_secondary", '#C8A68B');
    $hasMpCapable = in_array($store->checkout_mode, ['card', 'mixed']) && (!empty($store->mp_access_token) || !empty($store->gateway_access_token));
    $hasPaypalCapable = in_array($store->checkout_mode, ['card', 'mixed']) && !empty($store->paypal_client_id) && !empty($store->paypal_client_secret);
@endphp
<script>window.tribioCsrfToken = '{{ csrf_token() }}';</script>
<div id="cartDrawer"
     style="--pay-accent: {{ $payAccent }}; --pay-accent-soft: {{ $paySecondary }};"
     x-data="{
         cartOpen: false,
         checkoutStep: 1,
         submitting: false,
         errors: {},
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
             payment_method: '{{ ($store->checkout_mode === 'card' || ($store->checkout_mode === 'mixed' && !empty($store->mp_access_token))) ? 'card' : 'whatsapp' }}'
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
         hasMercadoPago: {{ $hasMpCapable ? 'true' : 'false' }},
         hasWhatsapp: {{ in_array($store->checkout_mode, ['whatsapp', 'mixed']) ? 'true' : 'false' }},
         hasPaypal: {{ $hasPaypalCapable ? 'true' : 'false' }},
         paypalClientId: '{{ $store->paypal_client_id }}',
         paypalSdkOrigin: '{{ ($store->paypal_mode ?? 'sandbox') === 'live' ? 'https://www.paypal.com' : 'https://www.sandbox.paypal.com' }}',
         paypalReady: false,
         paypalSession: null,
         paypalOrderNumber: null,
         paymentMethod: '{{ ($store->checkout_mode === 'card' || ($store->checkout_mode === 'mixed' && !empty($store->mp_access_token))) ? 'card' : 'whatsapp' }}',
         hasActiveToken: {{ (!empty($store->mp_access_token) || !empty($store->gateway_access_token)) ? 'true' : 'false' }},
         cartItems: window.TribioCart ? window.TribioCart.items : [],
         // Tarjeta embebida: se tokeniza con MercadoPago.js dentro del propio drawer, sin
         // redirigir a otra página. Yape/PagoEfectivo/banca no se pueden representar como
         // campos de formulario, así que esos siguen usando la redirección (payment_method
         // 'mercadopago_other', sin mp_form_data — ver Mercado-Pago-Checkout-Flow en la bóveda).
         cardForm: {
             number: '', name: '', expiry: '', cvv: '',
             idType: 'DNI', idNumber: '',
             paymentMethodId: null, issuerId: null, brandLabel: '',
             identificationTypes: [],
         },
         formatMoney(amount) {
             const isInt = ['COP', 'CLP', 'ARS'].includes(this.currentCurrency);
             const num = Number(amount || 0);
             return this.currencySymbol + ' ' + (isInt ? Math.round(num).toLocaleString('en-US') : num.toFixed(2));
         },
         init() {
             this.customer.country = '{{ \App\Helpers\CurrencyHelper::currentCountry() }}';
             this.updateShipping();
             this.checkCurrentCustomer();
             if (this.hasMercadoPago) this.ensureMercadoPagoJs();
             window.addEventListener('open-cart-drawer', () => { this.cartOpen = true; });
         },
         async ensureMercadoPagoJs() {
             if (window.mpInstance || typeof window.MercadoPago === 'undefined') return;
             window.mpInstance = new window.MercadoPago('{{ $store->mp_public_key ?? $store->gateway_public_key }}', { locale: 'es-PE' });
             try {
                 const types = await window.mpInstance.getIdentificationTypes();
                 this.cardForm.identificationTypes = types || [];
                 if (types && types.length && !types.find(t => t.id === this.cardForm.idType)) {
                     this.cardForm.idType = types[0].id;
                 }
             } catch (e) { console.error('MercadoPago getIdentificationTypes:', e); }
         },
         // PayPal: cobra siempre en USD (no soporta PEN — confirmado contra la referencia
         // de monedas de PayPal). Usa su Web SDK v6 (no el paypal.Buttons() clásico, que
         // está siendo reemplazado) — ver PayPal-Checkout-Flow en la bóveda del proyecto.
         async ensurePaypalSdk() {
             if (this.paypalReady || !this.hasPaypal) return;
             try {
                 if (!window.paypal) {
                     await new Promise((resolve, reject) => {
                         const s = document.createElement('script');
                         s.src = this.paypalSdkOrigin + '/web-sdk/v6/core';
                         s.onload = resolve;
                         s.onerror = () => reject(new Error('No se pudo cargar PayPal.'));
                         document.body.appendChild(s);
                     });
                 }
                 const sdkInstance = await window.paypal.createInstance({
                     clientId: this.paypalClientId,
                     components: ['paypal-payments'],
                     pageType: 'checkout',
                 });
                 const eligibility = await sdkInstance.findEligibleMethods({ currencyCode: 'USD' });
                 if (!eligibility.isEligible('paypal')) {
                     this.errors.payment = 'PayPal no está disponible en este momento. Elige otro método de pago.';
                     return;
                 }
                 const self = this;
                 this.paypalSession = sdkInstance.createPayPalOneTimePaymentSession({
                     async onApprove(data) { await self.capturePaypalOrder(data.orderId); },
                     onCancel() { self.submitting = false; },
                     onError(err) {
                         console.error('PayPal onError:', err);
                         self.errors.payment = 'Ocurrió un error con PayPal. Intenta de nuevo.';
                         self.submitting = false;
                     },
                 });
                 this.paypalReady = true;
                 this.$nextTick(() => this.bindPaypalButton());
             } catch (e) {
                 console.error('ensurePaypalSdk:', e);
                 this.errors.payment = 'No se pudo inicializar PayPal.';
             }
         },
         bindPaypalButton() {
             const btn = document.getElementById('paypalButtonEl');
             if (!btn || btn.dataset.bound || !this.paypalReady) return;
             btn.dataset.bound = '1';
             btn.removeAttribute('hidden');
             btn.addEventListener('click', async () => {
                 this.errors = {};
                 if (!this.validateStep2()) return;
                 this.submitting = true;
                 try {
                     // Se le pasa la promesa de createPaypalOrder() sin esperarla (sin
                     // await aquí): el SDK abre el popup de inmediato, dentro del mismo
                     // gesto de clic del usuario, y recién ahí espera a que la orden se
                     // resuelva — si se esperara antes de llamar a start(), el navegador
                     // podría bloquear el popup por no considerarlo ya una acción directa
                     // del usuario.
                     await this.paypalSession.start({ presentationMode: 'auto' }, this.createPaypalOrder());
                 } catch (e) {
                     console.error('PayPal start:', e);
                     this.submitting = false;
                 }
             });
         },
         async createPaypalOrder() {
             const token = this.customer._token;
             const payload = {
                 _token: token,
                 items: this.cartItems,
                 customer_name: this.customer.name,
                 customer_email: this.customer.email,
                 customer_phone: this.customer.phone,
                 customer_address: this.customer.address,
                 customer_country: this.customer.country,
                 customer_state: this.customer.state,
                 customer_city: this.customer.city,
                 customer_zipcode: this.customer.zipcode,
                 customer_notes: this.customer.notes,
                 express_shipping: !!this.customer.express_shipping,
                 payment_method: 'paypal',
             };
             const res = await fetch(`/tienda/${this.storeSlug}/checkout`, {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                 body: JSON.stringify(payload),
             });
             const data = await res.json();
             if (!res.ok || !data.success || !data.paypal_order_id) {
                 this.submitting = false;
                 this.errors.payment = data.error || 'No se pudo iniciar el pago con PayPal.';
                 throw new Error(this.errors.payment);
             }
             this.paypalOrderNumber = data.order_number;
             return data.paypal_order_id;
         },
         async capturePaypalOrder(paypalOrderId) {
             try {
                 const res = await fetch(`/tienda/${this.storeSlug}/checkout/paypal/capturar`, {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.customer._token },
                     body: JSON.stringify({ order_number: this.paypalOrderNumber, paypal_order_id: paypalOrderId }),
                 });
                 const data = await res.json();
                 if (res.ok && data.success) {
                     if (window.TribioCart) window.TribioCart.clear();
                     window.location.href = data.redirect_url;
                 } else {
                     this.submitting = false;
                     this.errors.payment = data.error || 'No se pudo completar el pago con PayPal.';
                 }
             } catch (e) {
                 this.submitting = false;
                 this.errors.payment = 'Error de conexión al confirmar el pago con PayPal.';
             }
         },
         formatCardNumber() {
             const digits = this.cardForm.number.replace(/\D/g, '').slice(0, 19);
             this.cardForm.number = digits.replace(/(.{4})/g, '$1 ').trim();
             this.detectCardBrand();
         },
         formatExpiry() {
             let v = this.cardForm.expiry.replace(/\D/g, '').slice(0, 4);
             if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2);
             this.cardForm.expiry = v;
         },
         async detectCardBrand() {
             const bin = this.cardForm.number.replace(/\s/g, '').slice(0, 6);
             this.cardForm.paymentMethodId = null;
             this.cardForm.brandLabel = '';
             if (bin.length < 6 || !window.mpInstance) return;
             try {
                 const result = await window.mpInstance.getPaymentMethods({ bin });
                 const pm = result?.results?.[0];
                 if (pm) {
                     this.cardForm.paymentMethodId = pm.id;
                     this.cardForm.issuerId = pm.issuer?.id || null;
                     this.cardForm.brandLabel = pm.name || '';
                 }
             } catch (e) { /* se revalida al enviar; no interrumpe la escritura */ }
         },
         async submitCardPayment() {
             this.errors = {};
             if (!this.customerLoggedIn) { this.openTribioPass('register'); return; }
             if (!this.validateStep2()) return;
             if (!window.mpInstance) {
                 this.errors.payment = 'No se pudo cargar Mercado Pago. Recarga la página e intenta de nuevo.';
                 return;
             }
             const cardNumber = this.cardForm.number.replace(/\s/g, '');
             const [expMonth, expYearRaw] = (this.cardForm.expiry || '').split('/');
             const expYear = expYearRaw && expYearRaw.length === 2 ? ('20' + expYearRaw) : expYearRaw;
             if (!cardNumber || !this.cardForm.name.trim() || !expMonth || !expYear || !this.cardForm.cvv || !this.cardForm.idNumber.trim()) {
                 this.errors.payment = 'Completa todos los datos de la tarjeta.';
                 return;
             }
             this.submitting = true;
             try {
                 if (!this.cardForm.paymentMethodId) { await this.detectCardBrand(); }
                 const tokenResp = await window.mpInstance.createCardToken({
                     cardNumber,
                     cardholderName: this.cardForm.name.trim(),
                     cardExpirationMonth: expMonth,
                     cardExpirationYear: expYear,
                     securityCode: this.cardForm.cvv,
                     identificationType: this.cardForm.idType,
                     identificationNumber: this.cardForm.idNumber.trim(),
                 });
                 if (!tokenResp || !tokenResp.id) throw new Error('No se pudo generar el token de la tarjeta.');
                 this.customer.payment_method = 'mercadopago';
                 this.customer.mp_form_data = {
                     token: tokenResp.id,
                     payment_method_id: this.cardForm.paymentMethodId,
                     issuer_id: this.cardForm.issuerId,
                     installments: 1,
                     payer: {
                         email: this.customer.email,
                         identification: { type: this.cardForm.idType, number: this.cardForm.idNumber.trim() }
                     }
                 };
                 await window.TribioCart.checkout(this.storeSlug, this.customer);
             } catch (e) {
                 console.error(e);
                 this.errors.payment = (e && e.message) ? e.message : 'No se pudo procesar tu tarjeta. Verifica los datos e intenta de nuevo.';
             } finally {
                 this.submitting = false;
             }
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
                 window.dispatchEvent(new CustomEvent('open-customer-modal', { detail: { tab: tab, ...extra } }));
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
                     body: JSON.stringify({ email: this.loginEmail, password: this.loginPassword })
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
                     headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.tribioCsrfToken }
                 });
             } catch (e) { console.error(e); }
             this.onCustomerLogout();
             window.dispatchEvent(new CustomEvent('customer-logged-out'));
         },
         updateShipping() {
             if (!this.customer.country) return;
             fetch(`/api/shipping-cost/${this.storeSlug}?country=${this.customer.country}&state=${this.customer.state}`)
                 .then(res => res.json())
                 .then(data => { this.shippingCost = parseFloat(data.cost) || 0; })
                 .catch(() => this.shippingCost = 0);
         },
         async checkCurrentCustomer() {
             try {
                 const res = await fetch('/customer/current', { headers: { 'Accept': 'application/json' } });
                 const data = await res.json();
                 if (data.authenticated && data.user) this.onCustomerAuth(data);
             } catch (e) { console.error(e); }
         },
         onCustomerAuth(data) {
             this.customerLoggedIn = true;
             this.customerUser = data.user;
             this.customerAddresses = data.addresses || [];
             if (data.user) {
                 if (!this.customer.name?.trim()) this.customer.name = data.user.name || '';
                 if (!this.customer.email?.trim()) this.customer.email = data.user.email || '';
                 if (!this.customer.phone?.trim()) this.customer.phone = data.user.phone || '';
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
                     this.emailCheckStatus = data.exists ? (data.is_cliente ? 'exists' : 'admin_exists') : 'new';
                 } catch (e) { this.emailCheckStatus = null; }
             }, 450);
         },
         validateStep2() {
             this.errors = {};
             if (!this.customer.phone || !this.customer.phone.trim()) this.errors.phone = 'Ingresa tu teléfono / WhatsApp.';
             if (this.selectedAddressId === 'new' || this.customerAddresses.length === 0) {
                 if (!this.customer.address || !this.customer.address.trim()) this.errors.address = 'Ingresa tu dirección de entrega.';
             }
             return Object.keys(this.errors).length === 0;
         },
         submitOrder() {
             if (!this.customerLoggedIn) {
                 this.openTribioPass('register');
                 return;
             }
             if (this.paymentMethod === 'paypal') {
                 // PayPal se paga desde su propio botón (bindPaypalButton()), nunca desde
                 // este botón genérico — TribioCart.checkout() no sabe abrir el popup de
                 // PayPal ni esperar su aprobación, así que dejarlo pasar dejaría la orden
                 // confirmada en pantalla sin que el cliente haya pagado de verdad.
                 return;
             }
             if (!this.validateStep2()) return;
             if ((this.paymentMethod === 'card' || this.paymentMethod === 'mercadopago_other') && !this.hasActiveToken) {
                 this.errors.payment = 'La tienda tiene habilitado el pago con Mercado Pago pero aún no ha configurado sus credenciales. Selecciona WhatsApp / Pago Directo.';
                 return;
             }

             if (this.paymentMethod === 'card') {
                 // Tarjeta embebida: se tokeniza y se cobra sin salir de la tienda.
                 this.submitCardPayment();
                 return;
             }

             // 'mercadopago_other' (Yape/PagoEfectivo/banca): el backend responde con
             // `payment_url` (Checkout Pro) y `TribioCart.checkout()` redirige ahí. 'whatsapp'
             // no toca Mercado Pago en absoluto.
             this.customer.payment_method = this.paymentMethod === 'mercadopago_other' ? 'mercadopago' : this.paymentMethod;
             if (window.TribioCart) {
                 this.submitting = true;
                 window.TribioCart.checkout(this.storeSlug, this.customer).finally(() => { this.submitting = false; });
             }
         }
     }"
     @cart-updated.window="cartItems = $event.detail"
     @customer-authenticated.window="onCustomerAuth($event.detail)"
     @customer-logged-out.window="onCustomerLogout()"
     x-show="cartOpen"
     style="display:none;"
     class="fixed inset-0 z-[999] flex justify-end">

    <div x-show="cartOpen" x-transition:enter="transition-opacity ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="absolute inset-0 bg-black/50" @click="cartOpen = false"></div>

    <div x-show="cartOpen"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full sm:translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="relative pay-drawer-panel h-full flex flex-col bg-white border-l border-[var(--pay-border)] shadow-2xl">

        {{-- Header --}}
        <div class="flex items-center justify-between p-4 sm:p-5 border-b border-[var(--pay-border)]">
            <h3 class="text-[var(--pay-text)] font-bold text-lg" x-text="checkoutStep === 1 ? '{{ \App\Helpers\TranslationHelper::trans('your_cart', 'Mi carrito') }}' : '{{ \App\Helpers\TranslationHelper::trans('checkout', 'Finalizar Compra') }}'"></h3>
            <button @click="cartOpen = false" aria-label="Cerrar" class="text-[var(--pay-text-muted)] hover:text-[var(--pay-text)] transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Indicador de pasos: el pago en sí ocurre en Mercado Pago (Checkout Pro),
             fuera del drawer, así que aquí solo hay 2 pasos propios. --}}
        <div class="px-5 pt-4" x-show="cartItems.length > 0">
            <div class="pay-step-track">
                <div class="pay-step-track-fill" :style="'width: ' + ((checkoutStep - 1) * 100) + '%'"></div>
                <div class="flex flex-col items-center" style="width:50%">
                    <div class="pay-step-dot" :class="checkoutStep > 1 ? 'is-done' : 'is-active'">
                        <svg x-show="checkoutStep > 1" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        <span x-show="checkoutStep <= 1">1</span>
                    </div>
                    <span class="pay-step-label" :class="checkoutStep === 1 ? 'is-active' : ''">Carrito</span>
                </div>
                <div class="flex flex-col items-center" style="width:50%">
                    <div class="pay-step-dot" :class="checkoutStep === 2 ? 'is-active' : ''">2</div>
                    <span class="pay-step-label" :class="checkoutStep === 2 ? 'is-active' : ''">Envío y pago</span>
                </div>
            </div>
        </div>

        <div class="flex-1 p-4 sm:p-5 overflow-y-auto">
            <template x-if="cartItems.length === 0">
                <div class="text-center mt-10">
                    <svg class="w-12 h-12 mx-auto text-[var(--pay-border)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <p class="text-[var(--pay-text-muted)] text-sm mt-3">{{ \App\Helpers\TranslationHelper::trans('empty_cart', 'Tu carrito está vacío.') }}</p>
                </div>
            </template>

            {{-- PASO 1: CARRITO --}}
            <template x-if="cartItems.length > 0 && checkoutStep === 1">
                <div class="space-y-3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                    <template x-for="(item, index) in cartItems" :key="index">
                        <div class="flex gap-3 p-3 bg-[var(--pay-surface-muted)] rounded-xl border border-[var(--pay-border)] items-center">
                            <template x-if="item.image">
                                <img :src="item.image" class="w-16 h-16 object-cover rounded-lg flex-shrink-0">
                            </template>
                            <template x-if="!item.image">
                                <div class="w-16 h-16 bg-white border border-[var(--pay-border)] rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-6 h-6 text-[var(--pay-text-muted)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                            </template>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-[var(--pay-text)] font-semibold text-sm leading-tight" x-text="item.name"></h4>
                                <template x-if="item.variant_title">
                                    <p class="text-[11px] text-[var(--pay-accent-soft)] font-semibold mt-0.5" x-text="item.variant_title"></p>
                                </template>
                                <div class="flex justify-between items-center mt-2">
                                    <p class="text-[var(--pay-text)] font-bold text-sm" x-text="formatMoney(item.price * item.quantity)"></p>
                                    <div class="flex items-center gap-2 text-[var(--pay-text-muted)] text-xs bg-white rounded-full border border-[var(--pay-border)] p-1">
                                        <button @click="window.TribioCart.updateQuantity(item.cartKey || item.id, item.quantity - 1)" class="w-5 h-5 rounded-full hover:bg-[var(--pay-surface-muted)] flex items-center justify-center font-bold">-</button>
                                        <span x-text="item.quantity" class="w-4 text-center font-medium"></span>
                                        <button @click="window.TribioCart.updateQuantity(item.cartKey || item.id, item.quantity + 1)" class="w-5 h-5 rounded-full hover:bg-[var(--pay-surface-muted)] flex items-center justify-center font-bold">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- PASO 2: ENVÍO Y CUENTA --}}
            <template x-if="cartItems.length > 0 && checkoutStep === 2">
                <div class="space-y-4 text-[var(--pay-text)]" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

                    <template x-if="customerLoggedIn">
                        <div class="p-3 bg-[var(--pay-success-bg)] border border-emerald-200 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full bg-[var(--pay-success)] text-white flex items-center justify-center flex-shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </div>
                                <div class="text-xs">
                                    <p class="font-bold text-emerald-950">Conectado como <strong x-text="customerUser?.name"></strong></p>
                                    <p class="text-emerald-700" x-text="customerUser?.email"></p>
                                </div>
                            </div>
                            <button type="button" @click="logoutCustomer()" class="text-[11px] text-[var(--pay-text-muted)] hover:text-[var(--pay-text)] underline font-semibold">Cerrar sesión</button>
                        </div>
                    </template>

                    <template x-if="!customerLoggedIn">
                        <div class="p-4 bg-[var(--pay-accent)] text-white rounded-2xl shadow-md border border-black/10">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-[var(--pay-accent-soft)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                <span class="text-xs font-black uppercase tracking-wider text-[var(--pay-accent-soft)]">Tribio Pass requerido</span>
                            </div>
                            <p class="text-xs text-white/70 leading-snug mb-3">Para pagar y guardar tus direcciones de envío, inicia sesión o crea tu cuenta Tribio Pass en segundos.</p>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" @click="openTribioPass('register')" class="w-full py-2.5 px-3 bg-[var(--pay-accent-soft)] text-black font-black rounded-xl text-xs">Crear cuenta</button>
                                <button type="button" @click="openTribioPass('login')" class="w-full py-2.5 px-3 bg-white/10 hover:bg-white/20 text-white border border-white/20 font-bold rounded-xl text-xs">Iniciar sesión</button>
                            </div>
                        </div>
                    </template>

                    <template x-if="customerLoggedIn && customerAddresses.length > 0">
                        <div class="p-3.5 bg-[var(--pay-surface-muted)] rounded-2xl border border-[var(--pay-border)] space-y-2.5">
                            <label class="text-xs font-black text-[var(--pay-text)] uppercase tracking-wide">Dirección de entrega</label>
                            <div class="space-y-2">
                                <template x-for="addr in customerAddresses" :key="addr.id">
                                    <div @click="selectSavedAddress(addr)"
                                         :class="(selectedAddressId === addr.id) ? 'border-2 border-[var(--pay-accent)] bg-white' : 'border border-[var(--pay-border)] bg-white hover:border-[var(--pay-text-muted)]'"
                                         class="p-3 rounded-xl transition cursor-pointer flex items-center justify-between">
                                        <div>
                                            <span class="text-xs font-black text-[var(--pay-text)] uppercase" x-text="addr.title || addr.type"></span>
                                            <p class="text-xs text-[var(--pay-text)] font-medium leading-snug mt-0.5" x-text="addr.address"></p>
                                            <p class="text-[11px] text-[var(--pay-text-muted)]" x-text="[(addr.city || ''), (addr.state || '')].filter(Boolean).join(', ')"></p>
                                        </div>
                                        <div class="w-4 h-4 rounded-full border-2 flex-shrink-0" :class="selectedAddressId === addr.id ? 'border-[var(--pay-accent)] bg-[var(--pay-accent)]' : 'border-[var(--pay-border)]'"></div>
                                    </div>
                                </template>
                                <div @click="selectNewAddress()"
                                     :class="selectedAddressId === 'new' ? 'border-2 border-[var(--pay-accent)] bg-white' : 'border border-dashed border-[var(--pay-border)] bg-white/70'"
                                     class="p-2.5 rounded-xl transition cursor-pointer text-xs font-bold text-[var(--pay-text-muted)]">
                                    + Usar otra dirección para este pedido
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-if="customerLoggedIn">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Teléfono (WhatsApp) *</label>
                                <input type="tel" x-model="customer.phone" @input="delete errors.phone"
                                       :class="errors.phone ? 'pay-field-error' : 'border-[var(--pay-border)]'"
                                       class="w-full bg-[var(--pay-surface-muted)] border rounded-lg px-4 py-2.5 text-sm outline-none transition">
                                <span x-show="errors.phone" x-text="errors.phone" class="pay-field-error-msg"></span>
                            </div>

                            <template x-if="selectedAddressId === 'new' || customerAddresses.length === 0">
                                <div class="space-y-3 p-3.5 border border-[var(--pay-border)] rounded-2xl bg-[var(--pay-surface-muted)]">
                                    <div>
                                        <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Dirección de envío *</label>
                                        <input type="text" x-model="customer.address" @input="delete errors.address" placeholder="Av., Calle, Número o Dpto"
                                               :class="errors.address ? 'pay-field-error' : 'border-[var(--pay-border)]'"
                                               class="w-full bg-white border rounded-lg px-4 py-2.5 text-sm outline-none transition">
                                        <span x-show="errors.address" x-text="errors.address" class="pay-field-error-msg"></span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Ciudad</label>
                                            <input type="text" x-model="customer.city" class="w-full bg-white border border-[var(--pay-border)] rounded-lg px-3 py-2.5 text-sm outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Estado / Depto.</label>
                                            <input type="text" x-model="customer.state" @blur="updateShipping" class="w-full bg-white border border-[var(--pay-border)] rounded-lg px-3 py-2.5 text-sm outline-none">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="isExpressEnabled">
                        <label class="flex items-start gap-3 cursor-pointer p-3.5 bg-[var(--pay-success-bg)] border border-emerald-200 rounded-xl">
                            <input type="checkbox" x-model="customer.express_shipping" class="mt-1 accent-emerald-600 w-4 h-4 rounded">
                            <div>
                                <p class="font-bold text-sm text-emerald-700 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    Envío Express
                                </p>
                                <p class="text-xs text-emerald-600/80 mt-0.5"><span x-show="expressCost > 0" x-text="'+ ' + currencySymbol + ' ' + expressCost.toFixed(2)"></span><span x-show="expressCost == 0">¡Es gratis!</span></p>
                            </div>
                        </label>
                    </template>

                    <div class="pt-1">
                        <label class="block text-xs font-bold text-[var(--pay-text)] uppercase tracking-wider mb-2">Método de pago</label>
                        <div class="space-y-2.5">
                            <template x-if="hasMercadoPago">
                                <label :class="paymentMethod === 'card' ? 'border-[var(--pay-accent)] bg-[var(--pay-surface-muted)] ring-1 ring-[var(--pay-accent)]' : 'border-[var(--pay-border)] bg-white'"
                                       class="flex flex-col p-3.5 rounded-xl border cursor-pointer transition-all">
                                    <div class="flex items-center gap-2.5">
                                        <input type="radio" name="payment_method" value="card" x-model="paymentMethod" @change="ensureMercadoPagoJs()" class="accent-[var(--pay-accent)] w-4 h-4">
                                        <svg class="w-4 h-4 text-[var(--pay-text)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-width="2" d="M2 10h20"/></svg>
                                        <span class="font-bold text-sm">Tarjeta débito / crédito</span>
                                    </div>
                                    <p class="mt-1.5 text-[11px] text-[var(--pay-text-muted)] pl-6">Visa, Mastercard, AMEX. Pagas aquí mismo, sin salir de la tienda.</p>
                                </label>
                            </template>

                            {{-- Formulario de tarjeta embebido: aparece solo al elegir "Tarjeta" --}}
                            <div x-show="paymentMethod === 'card'" x-cloak class="p-3.5 rounded-xl border border-[var(--pay-border)] bg-white space-y-3">
                                <div>
                                    <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Número de tarjeta</label>
                                    <div class="relative">
                                        <input type="text" inputmode="numeric" x-model="cardForm.number" @input="formatCardNumber()" maxlength="23" placeholder="1234 1234 1234 1234"
                                               class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-lg px-4 py-2.5 text-sm outline-none transition tracking-wider">
                                        <span x-show="cardForm.brandLabel" x-text="cardForm.brandLabel" class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-[var(--pay-text-muted)] uppercase"></span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Vencimiento</label>
                                        <input type="text" inputmode="numeric" x-model="cardForm.expiry" @input="formatExpiry()" maxlength="5" placeholder="MM/AA"
                                               class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-lg px-4 py-2.5 text-sm outline-none transition">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Código de seguridad</label>
                                        <input type="text" inputmode="numeric" x-model="cardForm.cvv" maxlength="4" placeholder="Ej: 123"
                                               class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-lg px-4 py-2.5 text-sm outline-none transition">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Nombre del titular (como aparece en la tarjeta)</label>
                                    <input type="text" x-model="cardForm.name" placeholder="María López"
                                           class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-lg px-4 py-2.5 text-sm outline-none transition">
                                </div>
                                <div class="grid grid-cols-[auto_1fr] gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Documento</label>
                                        <select x-model="cardForm.idType" class="h-[42px] bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-lg px-2 text-sm outline-none transition">
                                            <template x-for="t in (cardForm.identificationTypes.length ? cardForm.identificationTypes : [{id: 'DNI', name: 'DNI'}])" :key="t.id">
                                                <option :value="t.id" x-text="t.id"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Número de documento</label>
                                        <input type="text" inputmode="numeric" x-model="cardForm.idNumber" placeholder="99999999"
                                               class="w-full bg-[var(--pay-surface-muted)] border border-[var(--pay-border)] rounded-lg px-4 py-2.5 text-sm outline-none transition">
                                    </div>
                                </div>
                            </div>

                            <template x-if="hasMercadoPago">
                                <label :class="paymentMethod === 'mercadopago_other' ? 'border-[var(--pay-accent)] bg-[var(--pay-surface-muted)] ring-1 ring-[var(--pay-accent)]' : 'border-[var(--pay-border)] bg-white'"
                                       class="flex flex-col p-3.5 rounded-xl border cursor-pointer transition-all">
                                    <div class="flex items-center gap-2.5">
                                        <input type="radio" name="payment_method" value="mercadopago_other" x-model="paymentMethod" class="accent-[var(--pay-accent)] w-4 h-4">
                                        <svg class="w-4 h-4 text-[var(--pay-text)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        <span class="font-bold text-sm">Yape, PagoEfectivo, banca y más</span>
                                    </div>
                                    <p class="mt-1.5 text-[11px] text-[var(--pay-text-muted)] pl-6">Te llevamos a Mercado Pago para completar con estos medios.</p>
                                </label>
                            </template>

                            <template x-if="hasWhatsapp">
                                <label :class="paymentMethod === 'whatsapp' ? 'border-[var(--pay-accent)] bg-[var(--pay-surface-muted)] ring-1 ring-[var(--pay-accent)]' : 'border-[var(--pay-border)] bg-white'"
                                       class="flex flex-col p-3.5 rounded-xl border cursor-pointer transition-all">
                                    <div class="flex items-center gap-2.5">
                                        <input type="radio" name="payment_method" value="whatsapp" x-model="paymentMethod" class="accent-[var(--pay-accent)] w-4 h-4">
                                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1s-.7.9-.9 1.1-.4.2-.7.1a7.5 7.5 0 01-3.6-3.2c-.3-.4 0-.6.2-.8.2-.2.4-.5.6-.7.2-.2.2-.4.1-.6-.1-.3-.9-2.1-1.1-2.5s-.4-.3-.6-.3h-.6c-.2 0-.5.1-.8.4-.3.3-1.1 1.1-1.1 2.6s1.1 3 1.3 3.2c.2.2 2.2 3.4 5.3 4.6 2.6 1 2.6.7 3.1.6.5-.1 1.6-.7 1.8-1.3.2-.6.2-1.1.1-1.2-.1-.1-.2-.2-.4-.3z"/><path d="M12 2a10 10 0 00-8.6 15.1L2 22l4.9-1.3A10 10 0 1012 2zm0 18a8 8 0 01-4.1-1.1l-.3-.2-3 .8.8-3-.2-.3A8 8 0 1112 20z"/></svg>
                                        <span class="font-bold text-sm">WhatsApp / Pago directo</span>
                                    </div>
                                    <p class="mt-1.5 text-[11px] text-[var(--pay-text-muted)] pl-6">Coordina tu compra directamente con el vendedor.</p>
                                </label>
                            </template>

                            <template x-if="hasPaypal">
                                <label :class="paymentMethod === 'paypal' ? 'border-[var(--pay-accent)] bg-[var(--pay-surface-muted)] ring-1 ring-[var(--pay-accent)]' : 'border-[var(--pay-border)] bg-white'"
                                       class="flex flex-col p-3.5 rounded-xl border cursor-pointer transition-all">
                                    <div class="flex items-center gap-2.5">
                                        <input type="radio" name="payment_method" value="paypal" x-model="paymentMethod" @change="ensurePaypalSdk()" class="accent-[var(--pay-accent)] w-4 h-4">
                                        <svg class="w-4 h-4 text-[#003087]" fill="currentColor" viewBox="0 0 24 24"><path d="M7.5 21H4.2a.6.6 0 01-.59-.7L6.2 3.7A.9.9 0 017.1 3h6.4c2.9 0 5 1.6 4.6 4.3-.4 3.1-2.7 4.8-5.6 4.8h-2l-1 5.2a.9.9 0 01-.9.7H7.5zm7.8-13.4c.2-1.3-.8-1.9-2.2-1.9h-3.3l-.8 4.3h2.9c1.6 0 3.1-.7 3.4-2.4z"/></svg>
                                        <span class="font-bold text-sm">PayPal</span>
                                    </div>
                                    <p class="mt-1.5 text-[11px] text-[var(--pay-text-muted)] pl-6">Para clientes internacionales. Se cobra el equivalente en dólares (USD), no en soles.</p>
                                </label>
                            </template>
                            <span x-show="errors.payment" x-text="errors.payment" class="pay-field-error-msg"></span>
                        </div>
                    </div>

                    <div class="pay-trust-strip">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Tus datos viajan cifrados. Compra protegida por Tribio.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[var(--pay-text-muted)] mb-1">Notas adicionales</label>
                        <textarea x-model="customer.notes" rows="2" class="w-full bg-white border border-[var(--pay-border)] rounded-lg px-4 py-2 text-sm outline-none transition"></textarea>
                    </div>
                </div>
            </template>

        </div>

        <template x-if="cartItems.length > 0">
            <div class="p-4 sm:p-5 border-t border-[var(--pay-border)] bg-[var(--pay-surface-muted)]">
                <div class="flex justify-between items-center mb-2 text-[var(--pay-text-muted)] text-sm">
                    <span>Subtotal:</span>
                    <span x-text="formatMoney(cartTotal - shippingCost - (customer.express_shipping ? expressCost : 0))"></span>
                </div>
                <template x-if="shippingCost > 0">
                    <div class="flex justify-between items-center mb-2 text-[var(--pay-text-muted)] text-sm">
                        <span>Envío:</span>
                        <span x-text="'+ ' + formatMoney(shippingCost)"></span>
                    </div>
                </template>
                <div class="flex justify-between items-center text-[var(--pay-text)] border-t border-[var(--pay-border)] pt-2 mt-2"
                     :class="(checkoutStep === 2 && paymentMethod === 'paypal') ? 'mb-1' : 'mb-4'">
                    <span class="font-bold text-sm">Total a pagar:</span>
                    <span class="font-black text-xl" x-text="formatMoney(cartTotal)"></span>
                </div>
                <p x-show="checkoutStep === 2 && paymentMethod === 'paypal'" x-cloak class="text-[11px] text-[var(--pay-text-muted)] text-right mb-3">PayPal te cobrará el equivalente en USD, no en soles.</p>

                <template x-if="checkoutStep === 1">
                    <button @click="checkoutStep = 2" style="background: var(--pay-accent);" class="w-full py-3 rounded-xl font-bold text-white transition-all shadow-md flex items-center justify-center gap-2 hover:opacity-90">
                        Siguiente paso
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </template>

                <template x-if="checkoutStep === 2">
                    <div class="flex gap-2">
                        <button @click="checkoutStep = 1" class="px-4 py-3 bg-white border border-[var(--pay-border)] text-[var(--pay-text-muted)] font-bold rounded-xl hover:bg-[var(--pay-surface-muted)] transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <template x-if="!customerLoggedIn">
                            <button type="button" @click="openTribioPass('register')" style="background: var(--pay-accent);" class="flex-1 py-3 rounded-xl font-black text-white transition-all shadow-md flex items-center justify-center gap-2 hover:opacity-90">
                                Inicia sesión para pagar
                            </button>
                        </template>
                        <template x-if="customerLoggedIn && paymentMethod !== 'paypal'">
                            <button id="btnSubmitOrder" @click="submitOrder" :disabled="submitting" style="background: var(--pay-accent);" class="flex-1 py-3 rounded-xl font-bold text-white transition-colors shadow-md flex items-center justify-center gap-2 disabled:opacity-60 hover:opacity-90">
                                <span x-show="submitting" class="pay-spinner"></span>
                                <span x-text="submitting ? 'Procesando...' : (paymentMethod === 'card' ? 'Pagar ahora' : (paymentMethod === 'mercadopago_other' ? 'Continuar al pago' : 'Confirmar pedido'))"></span>
                            </button>
                        </template>
                        <template x-if="customerLoggedIn && paymentMethod === 'paypal'">
                            <div class="flex-1" x-init="$nextTick(() => bindPaypalButton())">
                                <paypal-button id="paypalButtonEl" hidden style="width:100%; display:block;"></paypal-button>
                                <p x-show="!paypalReady" class="text-center text-xs text-[var(--pay-text-muted)] py-3">Cargando PayPal...</p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
@if($hasMpCapable)
{{-- MercadoPago.js: solo para tokenizar la tarjeta en el navegador (createCardToken,
     getPaymentMethods, getIdentificationTypes) — el formulario en sí es nuestro, no un
     widget de Mercado Pago. Nunca enviamos el número/CVV en texto plano a nuestro servidor. --}}
<script src="https://sdk.mercadopago.com/js/v2"></script>
@endif
