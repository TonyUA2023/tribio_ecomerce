@extends('layouts.public')

@section('title', 'Tribio | Tu tienda, tus ventas, a tu manera')
@section('meta_description', 'Crea tu tienda online con catálogo, carrito, inventario y pedidos. Elige un plan Tribio y empieza a vender en Perú.')

@push('head')
<link rel="preload" as="image" href="{{ asset('images/tribio-commerce-3d.png') }}">
@endpush

@section('content')
<main class="tribio-home" x-data="tribioHome()" x-effect="document.body.style.overflow = (cartOpen || openModal) ? 'hidden' : ''" @keydown.escape.window="cartOpen = false; openModal = false">
    <section class="th-hero" id="inicio">
        <div class="th-shell th-hero-grid">
            <div class="th-hero-copy">
                <div class="th-status"><span class="th-status-dot"></span> Tu negocio merece una tienda que se mueva contigo</div>
                <h1>Tu tienda.<br>Tu forma de <span>vender.</span></h1>
                <p>Convierte lo que ofreces en una experiencia fácil de comprar: catálogo, carrito y pedidos organizados en un solo lugar.</p>
                <div class="th-hero-actions">
                    <a class="th-button th-button-dark" href="#precios">Explorar planes <span aria-hidden="true">↗</span></a>
                    <a class="th-text-link" href="#demo">Mira cómo funciona <span aria-hidden="true">↘</span></a>
                </div>
                <div class="th-hero-proof"><span class="th-proof-line"></span><span>Desde S/ {{ number_format(config('tribio.plans.basic.price'), 2) }} al mes</span><span class="th-proof-separator">/</span><span>Sin comisiones de Tribio por venta</span></div>
            </div>

            <div class="th-hero-stage" aria-label="Representación visual de una tienda, su carrito y sus pedidos">
                <div class="th-stage-grid" aria-hidden="true"></div>
                <div class="th-stage-orbit" aria-hidden="true"></div>
                <img class="th-hero-art" src="{{ asset('images/tribio-commerce-3d.png') }}" alt="Bolsa de compra 3D con productos, símbolo de una tienda Tribio" width="1120" height="1250" fetchpriority="high">
                <div class="th-float-card th-float-catalog"><span class="th-float-icon">▦</span><span><strong>Catálogo listo</strong><small>Productos que se entienden</small></span><span class="th-float-check">✓</span></div>
                <div class="th-float-card th-float-order"><span class="th-float-order-dot"></span><span><strong>Nuevo pedido</strong><small>Todo claro para preparar</small></span><span class="th-float-arrow">↗</span></div>
                <div class="th-stage-caption">Una compra fluida, de principio a fin.</div>
            </div>
        </div>
        <div class="th-shell th-hero-bottom"><span>Diseñado para negocios reales</span><span>Catálogo <b>→</b> carrito <b>→</b> pedido</span></div>
    </section>

    <section class="th-demo" id="demo">
        <div class="th-shell">
            <div class="th-section-head th-section-head-light"><div><p class="th-kicker">Una venta, vista de cerca</p><h2>Así se siente comprar<br>en tu tienda.</h2></div><p>Deja que tus clientes descubran, elijan y confirmen sin perderse en el camino.</p></div>
            <div class="th-demo-layout">
                <div class="th-demo-steps" role="tablist" aria-label="Etapas de una compra">
                    <button type="button" class="th-demo-step" role="tab" :aria-selected="demoStep === 1" :class="demoStep === 1 && 'is-active'" @click="demoStep = 1"><span class="th-step-index">01</span><span><strong>Descubre</strong><small>Fotos, detalles y precios en un catálogo pensado para decidir rápido.</small></span><span class="th-step-arrow">↗</span></button>
                    <button type="button" class="th-demo-step" role="tab" :aria-selected="demoStep === 2" :class="demoStep === 2 && 'is-active'" @click="demoStep = 2"><span class="th-step-index">02</span><span><strong>Agrega al carrito</strong><small>Reúne varios productos y revisa el total antes de continuar.</small></span><span class="th-step-arrow">↗</span></button>
                    <button type="button" class="th-demo-step" role="tab" :aria-selected="demoStep === 3" :class="demoStep === 3 && 'is-active'" @click="demoStep = 3"><span class="th-step-index">03</span><span><strong>Confirma el pedido</strong><small>El negocio recibe la información necesaria para atenderlo.</small></span><span class="th-step-arrow">↗</span></button>
                </div>
                <div class="th-demo-screen" role="tabpanel">
                    <div class="th-screen-top"><span class="th-screen-dots">● ● ●</span><span>tribio.pe/tienda/tu-negocio</span><span>↗</span></div>
                    <div class="th-screen-inner">
                        <div class="th-screen-brand"><span class="th-screen-brand-mark">T</span><span>Tu tienda <small>Compra simple, a tu ritmo</small></span><span class="th-screen-bag">▢</span></div>
                        <div x-show="demoStep === 1" class="th-screen-state"><div class="th-product-visual"><span>✦</span><div class="th-product-object"></div></div><div class="th-screen-row"><div><small>Lo más elegido</small><strong>Tu próximo favorito</strong></div><b>S/ 49.00</b></div><div class="th-screen-cta">Agregar al carrito <span>＋</span></div></div>
                        <div x-show="demoStep === 2" x-cloak class="th-screen-state"><p class="th-screen-label">Tu carrito</p><div class="th-cart-product"><div class="th-cart-product-art">✦</div><div><strong>Tu próximo favorito</strong><small>Cantidad: 1</small></div><b>S/ 49.00</b></div><div class="th-screen-total"><span>Total</span><strong>S/ 49.00</strong></div><div class="th-screen-cta">Continuar con mi pedido <span>↗</span></div></div>
                        <div x-show="demoStep === 3" x-cloak class="th-screen-state th-confirm-state"><div class="th-confirm-symbol">✓</div><p>Pedido recibido</p><strong>Todo en marcha.</strong><small>El negocio ya tiene los detalles para atenderte.</small><div class="th-confirm-track"><span class="is-done"></span><span class="is-done"></span><span></span></div></div>
                    </div>
                    <div class="th-screen-foot">Vista ilustrativa del recorrido de compra</div>
                </div>
            </div>
        </div>
    </section>

    <section class="th-capabilities" id="plataforma"><div class="th-shell">
        <div class="th-section-head"><div><p class="th-kicker">Detrás de cada venta</p><h2>Más que una vitrina.<br>Una operación en orden.</h2></div><p>Lo que ve tu cliente es simple. Lo que necesitas para trabajar también.</p></div>
        <div class="th-capability-grid">
            <article class="th-capability th-capability-wide"><div><span class="th-capability-number">A</span><h3>Una tienda que habla por tus productos.</h3><p>Presenta cada artículo con fotos, variantes y detalles para que comprar resulte natural.</p></div><div class="th-capability-image"><img src="{{ asset('images/store_builder_mockup.png') }}" alt="Vista del constructor de tiendas Tribio" loading="lazy"></div></article>
            <article class="th-capability th-capability-blue"><span class="th-capability-number">B</span><div class="th-capability-symbol" aria-hidden="true"><span>▢</span><span>✓</span></div><h3>Pedidos claros.</h3><p>Consulta qué compraron y prepara cada entrega con menos idas y vueltas.</p></article>
            <article class="th-capability th-capability-dark"><span class="th-capability-number">C</span><div class="th-inventory-visual" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div><h3>Inventario bajo control.</h3><p>Conoce tus existencias y organiza las variaciones de tus productos.</p></article>
        </div>
    </div></section>

    <section class="th-buy-guide" id="guia-compra"><div class="th-shell th-buy-guide-grid"><div><p class="th-kicker">Tu ruta para empezar</p><h2>Elige. Revisa.<br>Activa tu tienda.</h2><p>También hicimos sencilla tu compra de un plan. Siempre sabrás qué sigue y cuánto pagarás.</p><a href="#precios" class="th-button th-button-white">Ver planes <span aria-hidden="true">↗</span></a></div><ol class="th-buy-list"><li><span>1</span><div><strong>Elige el plan</strong><p>Compara los paquetes y agrega uno al carrito.</p></div></li><li><span>2</span><div><strong>Revisa tu carrito</strong><p>Confirma el plan y su precio mensual. Puedes cambiarlo o quitarlo.</p></div></li><li><span>3</span><div><strong>Configura tu tienda</strong><p>Crea o usa tu cuenta e indica el nombre y dirección de tu negocio.</p></div></li><li><span>4</span><div><strong>Confirma el pago</strong><p>En la siguiente pantalla revisas el resumen y pagas de forma segura.</p></div></li></ol></div></section>

    <section class="th-pricing" id="precios"><div class="th-shell"><div class="th-section-head"><div><p class="th-kicker">Planes Tribio</p><h2>Un plan para cada<br>momento del negocio.</h2></div><p>Precios mensuales transparentes. El cobro se confirma después de revisar tu carrito y crear la tienda.</p></div>
        <div class="th-price-grid">
            @foreach(config('tribio.plans') as $key => $plan)
            <article class="th-price-card {{ $plan['highlight'] ? 'th-price-featured' : '' }}">
                <div class="th-price-top"><span>{{ $loop->iteration < 10 ? '0'.$loop->iteration : $loop->iteration }} / PLAN</span>@if($plan['highlight'])<span class="th-price-tag">Más elegido</span>@endif</div>
                <h3>{{ $plan['label'] }}</h3><div class="th-price"><small>S/</small>{{ number_format($plan['price'], 2) }}<span>/mes</span></div>
                <p class="th-price-caption">Suscripción mensual. Revisa el detalle antes de pagar.</p>
                <button type="button" class="th-price-button" @click="addPlan('{{ $key }}')">Agregar al carrito <span aria-hidden="true">＋</span></button>
                <div class="th-price-includes">Incluye</div><ul>@foreach($plan['features'] as $feature)<li><span aria-hidden="true">✓</span>{{ $feature }}</li>@endforeach</ul>
            </article>
            @endforeach
        </div><p class="th-pricing-note">Cada tienda utiliza un plan. Puedes cambiar tu elección en el carrito antes de continuar.</p>
    </div></section>

    @if($featuredStores->count())
    <section class="th-stores" id="tiendas"><div class="th-shell"><div class="th-section-head"><div><p class="th-kicker">Tribio en acción</p><h2>Conoce tiendas<br>que ya están aquí.</h2></div><a href="{{ route('directory') }}" class="th-text-link">Ver directorio <span>↗</span></a></div><div class="th-store-grid">@foreach($featuredStores->take(3) as $store)<a href="{{ route('store.show', $store->slug) }}" class="th-store-card"><div class="th-store-cover">@if($store->cover_path)<img src="{{ $store->cover_url }}" alt="Portada de {{ $store->name }}" loading="lazy">@else<span>{{ config('tribio.business_categories')[$store->category]['icon'] ?? '▢' }}</span>@endif</div><div class="th-store-info"><div><small>Tienda en Tribio</small><strong>{{ $store->name }}</strong></div><span>↗</span></div></a>@endforeach</div></div></section>
    @endif

    <section class="th-faq" id="preguntas"><div class="th-shell th-faq-grid"><div><p class="th-kicker">Respuestas claras</p><h2>Antes de empezar.</h2><p>Soporte y consultas por WhatsApp: {{ config('tribio.support.whatsapp_display') }}.</p><a href="https://wa.me/{{ config('tribio.support.whatsapp_number') }}" target="_blank" rel="noopener noreferrer" class="th-text-link">Hablar con Tribio ↗</a></div><div class="th-faq-list">
        <details><summary>¿Qué pasa al agregar un plan al carrito?<span>＋</span></summary><p>Verás el plan y su precio mensual. Después podrás crear tu cuenta y tienda. El pago se solicita en la siguiente pantalla, luego de revisar el resumen.</p></details>
        <details><summary>¿Puedo cambiar de plan antes de pagar?<span>＋</span></summary><p>Sí. Vuelve a los planes y agrega el que prefieras; el carrito mostrará tu nueva elección.</p></details>
        <details><summary>¿Cómo compran mis clientes?<span>＋</span></summary><p>Exploran tu catálogo, agregan productos al carrito y completan el pedido según los métodos que configures para tu tienda.</p></details>
        <details><summary>¿Qué necesito para abrir mi tienda?<span>＋</span></summary><p>Los datos de tu cuenta, el nombre y la dirección de tu tienda, una categoría y un número para contactar a tus clientes.</p></details>
    </div></div></section>

    <section class="th-final"><div class="th-shell th-final-inner"><div class="th-final-mark" aria-hidden="true">✳</div><div><p class="th-kicker">Ahora te toca a ti</p><h2>Haz que tu negocio<br>sea fácil de elegir.</h2></div><a href="#precios" class="th-button th-button-white">Encuentra tu plan <span>↗</span></a></div></section>

    <button type="button" class="th-floating-cart" @click="cartOpen = true" aria-label="Abrir carrito de planes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 4h2l2 11h11l2-8H6M9 20h.01M18 20h.01" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Tu carrito</span><b x-text="cartPlan ? '1' : '0'"></b></button>

    <div class="th-cart-overlay" x-show="cartOpen" x-cloak @click="cartOpen = false"></div>
    <aside class="th-cart-drawer" x-show="cartOpen" x-cloak x-transition:enter="th-drawer-enter" x-transition:enter-start="th-drawer-start" x-transition:enter-end="th-drawer-end" x-transition:leave="th-drawer-enter" x-transition:leave-start="th-drawer-end" x-transition:leave-end="th-drawer-start" role="dialog" aria-modal="true" aria-label="Carrito de planes">
        <div class="th-drawer-head"><div><small>Tu elección</small><h2>Carrito</h2></div><button type="button" @click="cartOpen = false" aria-label="Cerrar carrito">×</button></div>
        <template x-if="!cartPlan"><div class="th-cart-empty"><div class="th-empty-icon">▢</div><h3>Tu carrito está esperando.</h3><p>Elige el plan que mejor acompaña a tu negocio. Podrás revisarlo aquí antes de continuar.</p><button type="button" class="th-button th-button-dark" @click="cartOpen = false; document.getElementById('precios').scrollIntoView({behavior:'smooth'})">Explorar planes ↗</button></div></template>
        <template x-if="cartPlan"><div class="th-cart-content"><div class="th-cart-item"><div class="th-cart-item-icon">✳</div><div><small>Suscripción mensual</small><h3 x-text="plans[cartPlan].label"></h3><p>1 tienda · 1 plan</p></div><strong x-text="money(plans[cartPlan].price)"></strong></div><button type="button" class="th-remove" @click="cartPlan = null">Quitar plan</button><div class="th-cart-guide"><strong>Lo que sigue</strong><ol><li><span>1</span> Datos de cuenta y tienda</li><li><span>2</span> Resumen y pago seguro</li><li><span>3</span> Publica tu tienda</li></ol></div><div class="th-cart-bottom"><div class="th-cart-total"><span>Total mensual</span><strong x-text="money(plans[cartPlan].price)"></strong></div><p>El cargo se realiza cuando confirmes el pago en la siguiente pantalla.</p><button type="button" class="th-button th-button-blue" @click="checkoutPlan()">Continuar con este plan <span>↗</span></button></div></div></template>
    </aside>

    @include('public.partials.plan-modal')
</main>
@endsection

@push('scripts')
<script>
function tribioHome() {
    return {
        plans: @json(config('tribio.plans')),
        demoStep: 1, cartOpen: false, cartPlan: null, openModal: false,
        selectedPlan: '', selectedPlanLabel: '', selectedPlanPrice: '', selectedPlanFeatures: [],
        isAuthenticated: {{ Auth::check() && !Auth::user()->isSuperAdmin() ? 'true' : 'false' }},
        authName: @json(Auth::check() ? Auth::user()->name : ''),
        authEmail: @json(Auth::check() ? Auth::user()->email : ''),
        showLoginForm: false, loginEmail: '', loginPassword: '', loginBusy: false, loginError: '',
        init() {
            const pending = @json(old('plan_key') ?: request()->query('plan_key'));
            if (pending && this.plans[pending]) {
                this.cartPlan = pending;
                this.checkoutPlan();
            }
        },
        money(value) { return 'S/ ' + Number(value).toFixed(2); },
        addPlan(key) { this.cartPlan = key; this.cartOpen = true; },
        checkoutPlan() {
            if (!this.cartPlan || !this.plans[this.cartPlan]) return;
            const plan = this.plans[this.cartPlan];
            this.selectedPlan = this.cartPlan;
            this.selectedPlanLabel = plan.label;
            this.selectedPlanPrice = Number(plan.price).toFixed(2);
            this.selectedPlanFeatures = plan.features;
            this.cartOpen = false;
            this.openModal = true;
        },
        async doModalLogin() {
            this.loginError = '';
            if (!this.loginEmail || !this.loginPassword) { this.loginError = 'Ingresa tu correo y contraseña.'; return; }
            this.loginBusy = true;
            try {
                const res = await fetch('/customer/login', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ email: this.loginEmail, password: this.loginPassword }) });
                const data = await res.json();
                if (res.ok && data.success) { this.isAuthenticated = true; this.authName = data.user.name; this.authEmail = data.user.email; this.showLoginForm = false; }
                else this.loginError = data.message || 'Correo o contraseña incorrectos.';
            } catch (e) { this.loginError = 'Error de conexión. Intenta nuevamente.'; }
            finally { this.loginBusy = false; }
        },
    };
}
</script>
@endpush
