@php
    $isEn = \App\Helpers\TranslationHelper::isEn();
    $enabledCountries = $store->getEnabledCountriesWithDetails();
    $footerCategories = $store->categories()->take(6)->get();
    $phoneClean = preg_replace('/[^0-9]/', '', $store->whatsapp_phone ?? $store->contact_phone ?? '51956183384');
    $waUrl = !empty($phoneClean) ? "https://wa.me/{$phoneClean}?text=" . urlencode($isEn ? "Hello, I would like more information" : "Hola, deseo más información sobre los productos de {$store->name}") : '#';
@endphp

<footer class="bg-[#1E1D1B] text-stone-300 font-sans border-t border-stone-800 selection:bg-[#C8A68B] selection:text-white"
        x-data="{
            reclamacionesOpen: false,
            faqOpen: false,
            shippingPolicyOpen: false,
            newsletterEmail: '',
            newsletterSent: false,
            reclamo: {
                tipo: 'Reclamo',
                nombres: '',
                documento_tipo: 'DNI',
                documento_num: '',
                telefono: '',
                email: '',
                direccion: '',
                bien: 'Producto',
                monto: '',
                descripcion_bien: '',
                detalle: '',
                pedido: '',
                enviado: false,
                folio: ''
            },
            submitNewsletter() {
                if (this.newsletterEmail && this.newsletterEmail.includes('@')) {
                    this.newsletterSent = true;
                    this.newsletterEmail = '';
                }
            },
            submitReclamo() {
                if (this.reclamo.nombres && this.reclamo.documento_num && this.reclamo.email && this.reclamo.detalle) {
                    this.reclamo.folio = 'LR-' + Math.floor(100000 + Math.random() * 900000);
                    this.reclamo.enviado = true;
                }
            },
            resetReclamo() {
                this.reclamo.enviado = false;
                this.reclamo.nombres = '';
                this.reclamo.documento_num = '';
                this.reclamo.telefono = '';
                this.reclamo.email = '';
                this.reclamo.direccion = '';
                this.reclamo.monto = '';
                this.reclamo.descripcion_bien = '';
                this.reclamo.detalle = '';
                this.reclamo.pedido = '';
            }
        }">

    {{-- ── 1. PRE-FOOTER / NEWSLETTER VIP CLUB ── --}}
    <div class="border-b border-white/10 bg-[#262421]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
                <div class="text-center lg:text-left max-w-xl">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#C8A68B]/20 text-[#E0C3AB] text-xs font-bold uppercase tracking-wider mb-2 border border-[#C8A68B]/30 font-brand">
                        {{ $isEn ? 'Join the Club' : 'Club Exclusivo' }}
                    </span>
                    <h3 class="text-xl sm:text-2xl font-bold text-white tracking-tight font-brand">
                        {{ $isEn ? 'Get 10% off your first purchase' : 'Recibe 10% de descuento en tu primera compra' }}
                    </h3>
                    <p class="text-xs sm:text-sm text-stone-400 mt-1">
                        {{ $isEn ? 'Subscribe to receive secret flash sales, product launches and member gifts.' : 'Suscríbete para enterarte antes de lanzamientos, ofertas relámpago y regalos exclusivos.' }}
                    </p>
                </div>

                {{-- Formulario Interactivo --}}
                <div class="w-full lg:w-auto flex-1 max-w-md">
                    <div x-show="!newsletterSent">
                        <form @submit.prevent="submitNewsletter()" class="flex flex-col sm:flex-row gap-2">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </span>
                                <input type="email" x-model="newsletterEmail" required
                                       placeholder="{{ $isEn ? 'Enter your email address...' : 'Ingresa tu correo electrónico...' }}"
                                       class="w-full bg-white/10 border border-white/20 text-white placeholder-stone-400 text-xs sm:text-sm rounded-xl py-3 pl-10 pr-4 outline-none focus:border-[#C8A68B] focus:ring-2 focus:ring-[#C8A68B]/30 transition">
                            </div>
                            <button type="submit"
                                    class="px-6 py-3 bg-[#C8A68B] hover:bg-[#B89578] text-[#1E1D1B] font-bold text-xs sm:text-sm rounded-xl transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-1.5 whitespace-nowrap cursor-pointer font-brand">
                                <span>{{ $isEn ? 'Subscribe' : '¡Suscribirme!' }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </button>
                        </form>
                        <p class="text-[11px] text-stone-400 mt-1.5 text-center sm:text-left flex items-center justify-center sm:justify-start gap-1">
                            {{ $isEn ? 'We respect your privacy. No spam ever.' : 'Respetamos tu privacidad. Cero spam garantizado.' }}
                        </p>
                    </div>

                    <div x-show="newsletterSent" style="display: none;"
                         class="p-3.5 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 text-xs flex items-center gap-2.5">
                        <div>
                            <p class="font-bold text-sm text-white font-brand">{{ $isEn ? 'Welcome to the Club!' : '¡Bienvenido a la comunidad!' }}</p>
                            <p class="text-[11px] text-emerald-300">{{ $isEn ? 'Check your inbox for your 10% welcome coupon.' : 'Revisa tu bandeja de entrada para canjear tu cupón de bienvenida.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 2. MAIN FOOTER CONTENT (4 Columnas Principales) ── --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10">

            {{-- Columna 1: Marca, Bio y Redes (lg:col-span-2) --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center gap-3">
                    @if($store->logo_path)
                        <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-10 w-auto object-contain">
                    @else
                        <div class="flex flex-col">
                            <span class="font-bold text-2xl md:text-3xl tracking-tight text-white font-brand">
                                {{ $store->name }}
                            </span>
                            <svg class="w-16 h-2 mt-0.5 text-[#C8A68B]" viewBox="0 0 100 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 3C30 11 70 11 96 3" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                            </svg>
                        </div>
                    @endif
                </div>

                <p class="text-xs sm:text-sm text-stone-400 leading-relaxed pr-4">
                    {{ $store->description ?: ($store->tagline ?: ($isEn ? 'Innovative and high-quality products crafted to make your everyday life easier, with certified nationwide and international delivery.' : 'Productos innovadores y de alta calidad diseñados para hacer tu vida más fácil. Envíos garantizados a todo el país y al extranjero.')) }}
                </p>

                {{-- Puntos de Contacto Directo --}}
                <div class="space-y-2 pt-2 text-xs">

                    {{-- Email --}}
                    <a href="mailto:{{ $store->contact_email ?: 'maetek.pe@gmail.com' }}"
                       class="flex items-center gap-2.5 text-gray-300 hover:text-[#C8A68B] transition">
                        <span class="w-7 h-7 rounded-full bg-sky-500/10 border border-sky-500/30 flex items-center justify-center text-sky-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </span>
                        <span class="truncate">{{ $store->contact_email ?: 'maetek.pe@gmail.com' }}</span>
                    </a>

                    {{-- Ubicación --}}
                    <div class="flex items-center gap-2.5 text-gray-400">
                        <span class="w-7 h-7 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </span>
                        <span>{{ $store->city ?: 'Lima, PE' }}</span>
                    </div>
                </div>

                {{-- Redes Sociales --}}
                <div class="pt-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-2">{{ $isEn ? 'Follow us' : 'Síguenos en redes' }}</span>
                    <div class="flex items-center gap-2.5">
                        {{-- Instagram Oficial --}}
                        @php
                            $igUrl = $store->instagram_url ?: 'https://www.instagram.com/maetek.pe?stkn=Nmk4NnhkMTBqcmxz&utm_source=qr';
                        @endphp
                        <a href="{{ $igUrl }}" target="_blank" rel="noopener" 
                           class="w-10 h-10 rounded-xl bg-gradient-to-tr from-[#f09433] via-[#dc2743] to-[#bc1888] flex items-center justify-center text-white shadow-md hover:scale-110 hover:shadow-lg transition-all duration-200" 
                           title="Instagram @maetek.pe">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>

                        {{-- TikTok Oficial --}}
                        @php
                            $ttUrl = $store->tiktok_url ?: 'https://www.tiktok.com/@maetek.pe?_r=1&_t=ZS-99lztEdVIJw';
                        @endphp
                        <a href="{{ $ttUrl }}" target="_blank" rel="noopener" 
                           class="w-10 h-10 rounded-xl bg-black border border-stone-700/80 flex items-center justify-center text-white shadow-md hover:scale-110 hover:border-stone-500 hover:shadow-lg transition-all duration-200" 
                           title="TikTok @maetek.pe">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.24 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                        </a>



                        {{-- Facebook (si existe) --}}
                        @if($store->facebook_url)
                            <a href="{{ $store->facebook_url }}" target="_blank" rel="noopener" 
                               class="w-10 h-10 rounded-xl bg-[#1877F2] flex items-center justify-center text-white shadow-md hover:scale-110 hover:shadow-lg transition-all duration-200" 
                               title="Facebook">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Columna 2: Catálogo & Colecciones --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4 border-l-2 border-[#C8A68B] pl-2.5">
                    {{ $isEn ? 'Collections' : 'Catálogo' }}
                </h4>
                <ul class="space-y-2.5 text-xs text-gray-400">
                    <li>
                        <a href="{{ route('store.catalog', $store->slug) }}" class="hover:text-[#C8A68B] transition flex items-center gap-1.5 font-semibold text-gray-200">
                            {{ $isEn ? 'All Products' : 'Todos los Productos' }}
                        </a>
                    </li>
                    @foreach($footerCategories as $cat)
                        <li>
                            <a href="{{ route('store.catalog', ['slug' => $store->slug, 'category' => $cat->slug]) }}" 
                               class="hover:text-white transition flex items-center justify-between group">
                                <span class="group-hover:translate-x-0.5 transition-transform">{{ $cat->getTranslatedName() }}</span>
                                <span class="text-[10px] text-gray-600 font-mono">{{ $cat->active_products_count ?? '' }}</span>
                            </a>
                        </li>
                    @endforeach
                    @if(method_exists($store, 'galleryItems') && $store->galleryItems()->exists())
                        <li class="pt-1 border-t border-white/5">
                            <a href="{{ route('gallery', $store->slug) }}" class="hover:text-[#C8A68B] transition flex items-center gap-1.5">
                                {{ $isEn ? 'Photo Gallery' : 'Galería de Fotos' }}
                            </a>
                        </li>
                    @endif
                </ul>
            </div>

            {{-- Columna 3: Atención al Cliente & Cuenta --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4 border-l-2 border-[#C8A68B] pl-2.5">
                    {{ $isEn ? 'Customer Care' : 'Atención al Cliente' }}
                </h4>
                <ul class="space-y-2.5 text-xs text-gray-400">
                    <li>
                        <button type="button" @click="window.openCustomerModal ? window.openCustomerModal('orders') : null"
                                class="hover:text-[#C8A68B] transition flex items-center gap-1.5 text-left cursor-pointer font-medium text-gray-300">
                            {{ $isEn ? 'Track My Order' : 'Rastrear mi Pedido' }}
                        </button>
                    </li>
                    <li>
                        <button type="button" @click="window.openCustomerModal ? window.openCustomerModal('login') : null"
                                class="hover:text-white transition flex items-center gap-1.5 text-left cursor-pointer">
                            {{ $isEn ? 'My Account / Login' : 'Mi Cuenta / Iniciar Sesión' }}
                        </button>
                    </li>
                    <li>
                        <button type="button" @click="faqOpen = true"
                                class="hover:text-white transition flex items-center gap-1.5 text-left cursor-pointer">
                            {{ $isEn ? 'Frequently Asked Questions' : 'Preguntas Frecuentes (FAQ)' }}
                        </button>
                    </li>
                    <li>
                        <button type="button" @click="shippingPolicyOpen = true"
                                class="hover:text-white transition flex items-center gap-1.5 text-left cursor-pointer">
                            {{ $isEn ? 'Shipping & Delivery' : 'Envíos y Tiempos de Entrega' }}
                        </button>
                    </li>
                    <li>
                        <a href="{{ route('store.contact', $store->slug) }}" class="hover:text-white transition flex items-center gap-1.5">
                            {{ $isEn ? 'Contact Us' : 'Escríbenos un Mensaje' }}
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Columna 4: Confianza, Libro de Reclamaciones & Pagos --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-white mb-4 border-l-2 border-[#C8A68B] pl-2.5">
                    {{ $isEn ? 'Trust & Security' : 'Garantía y Legal' }}
                </h4>
                
                {{-- Libro de Reclamaciones --}}
                <div class="mb-5">
                    <button type="button" @click="reclamacionesOpen = true" 
                            class="w-full p-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 hover:border-[#C8A68B]/40 transition text-left cursor-pointer group">
                        <div class="flex items-center gap-2.5">
                            <div>
                                <span class="text-xs font-bold text-white block group-hover:text-[#C8A68B] transition">Libro de Reclamaciones</span>
                                <span class="text-[10px] text-gray-400 block font-mono">D.S. 011-2011-PCM</span>
                            </div>
                        </div>
                    </button>
                </div>

                {{-- Insignias de Medios de Pago --}}
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-2">{{ $isEn ? 'Payment Methods' : 'Medios de Pago Aceptados' }}</span>
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- 1. Visa Oficial --}}
                        <div class="h-7 px-2.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="Visa">
                            <svg class="h-3 w-auto" viewBox="0 0 24 24" fill="#1434CB" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Visa">
                                <path d="M9.112 8.262L5.97 15.758H3.92L2.374 9.775c-.094-.368-.175-.503-.461-.658C1.447 8.864.677 8.627 0 8.479l.046-.217h3.3a.904.904 0 01.894.764l.817 4.338 2.018-5.102zm8.033 5.049c.008-1.979-2.736-2.088-2.717-2.972.006-.269.262-.555.822-.628a3.66 3.66 0 011.913.336l.34-1.59a5.207 5.207 0 00-1.814-.333c-1.917 0-3.266 1.02-3.278 2.479-.012 1.079.963 1.68 1.698 2.04.756.367 1.01.603 1.006.931-.005.504-.602.725-1.16.734-.975.015-1.54-.263-1.992-.473l-.351 1.642c.453.208 1.289.39 2.156.398 2.037 0 3.37-1.006 3.377-2.564m5.061 2.447H24l-1.565-7.496h-1.656a.883.883 0 00-.826.55l-2.909 6.946h2.036l.405-1.12h2.488zm-2.163-2.656l1.02-2.815.588 2.815zm-8.16-4.84l-1.603 7.496H8.34l1.605-7.496z"/>
                            </svg>
                        </div>

                        {{-- 2. Mastercard Oficial --}}
                        <div class="h-7 px-2.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="Mastercard">
                            <svg class="h-4.5 w-auto" viewBox="0 0 30 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="9" r="6.5" fill="#EB001B"/>
                                <circle cx="20" cy="9" r="6.5" fill="#F79E1B"/>
                                <path d="M15 4.3A6.5 6.5 0 0 0 12.6 9 6.5 6.5 0 0 0 15 13.7 6.5 6.5 0 0 0 17.4 9 6.5 6.5 0 0 0 15 4.3Z" fill="#FF5F00"/>
                            </svg>
                        </div>

                        {{-- 3. Apple Pay Oficial --}}
                        <div class="h-7 px-2.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="Apple Pay">
                            <svg class="h-4 w-auto" viewBox="0 0 24 24" fill="#000000" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Apple Pay">
                                <path d="M2.15 4.318a42.16 42.16 0 0 0-.454.003c-.15.005-.303.013-.452.04a1.44 1.44 0 0 0-1.06.772c-.07.138-.114.278-.14.43-.028.148-.037.3-.04.45A10.2 10.2 0 0 0 0 6.222v11.557c0 .07.002.138.003.207.004.15.013.303.04.452.027.15.072.291.142.429a1.436 1.436 0 0 0 .63.63c.138.07.278.115.43.142.148.027.3.036.45.04l.208.003h20.194l.207-.003c.15-.004.303-.013.452-.04.15-.027.291-.071.428-.141a1.432 1.432 0 0 0 .631-.631c.07-.138.115-.278.141-.43.027-.148.036-.3.04-.45.002-.07.003-.138.003-.208l.001-.246V6.221c0-.07-.002-.138-.004-.207a2.995 2.995 0 0 0-.04-.452 1.446 1.446 0 0 0-1.2-1.201 3.022 3.022 0 0 0-.452-.04 10.448 10.448 0 0 0-.453-.003zm0 .512h19.942c.066 0 .131.002.197.003.115.004.25.01.375.032.109.02.2.05.287.094a.927.927 0 0 1 .407.407.997.997 0 0 1 .094.288c.022.123.028.258.031.374.002.065.003.13.003.197v11.552c0 .065 0 .13-.003.196-.003.115-.009.25-.032.375a.927.927 0 0 1-.5.693 1.002 1.002 0 0 1-.286.094 2.598 2.598 0 0 1-.373.032l-.2.003H1.906c-.066 0-.133-.002-.196-.003a2.61 2.61 0 0 1-.375-.032c-.109-.02-.2-.05-.288-.094a.918.918 0 0 1-.406-.407 1.006 1.006 0 0 1-.094-.288 2.531 2.531 0 0 1-.032-.373 9.588 9.588 0 0 1-.002-.197V6.224c0-.065 0-.131.002-.197.004-.114.01-.248.032-.375.02-.108.05-.199.094-.287a.925.925 0 0 1 .407-.406 1.03 1.03 0 0 1 .287-.094c.125-.022.26-.029.375-.032.065-.002.131-.002.196-.003zm4.71 3.7c-.3.016-.668.199-.88.456-.191.22-.36.58-.316.918.338.03.675-.169.888-.418.205-.258.345-.603.308-.955zm2.207.42v5.493h.852v-1.877h1.18c1.078 0 1.835-.739 1.835-1.812 0-1.07-.742-1.805-1.808-1.805zm.852.719h.982c.739 0 1.161.396 1.161 1.089 0 .692-.422 1.092-1.164 1.092h-.979zm-3.154.3c-.45.01-.83.28-1.05.28-.235 0-.593-.264-.981-.257a1.446 1.446 0 0 0-1.23.747c-.527.908-.139 2.255.374 2.995.249.366.549.769.944.754.373-.014.52-.242.973-.242.454 0 .586.242.98.235.41-.007.667-.366.915-.733.286-.417.403-.82.41-.841-.007-.008-.79-.308-.797-1.209-.008-.754.615-1.113.644-1.135-.352-.52-.9-.578-1.09-.593a1.123 1.123 0 0 0-.092-.002zm8.204.397c-.99 0-1.606.533-1.652 1.256h.777c.072-.358.369-.586.845-.586.502 0 .803.266.803.711v.309l-1.097.064c-.951.054-1.488.484-1.488 1.184 0 .72.548 1.207 1.332 1.207.526 0 1.032-.281 1.264-.727h.019v.659h.788v-2.76c0-.803-.62-1.317-1.591-1.317zm1.94.072l1.446 4.009c0 .003-.073.24-.073.247-.125.41-.33.571-.711.571-.069 0-.206 0-.267-.015v.666c.06.011.267.019.335.019.83 0 1.226-.312 1.568-1.283l1.5-4.214h-.868l-1.012 3.259h-.015l-1.013-3.26zm-1.167 2.189v.316c0 .521-.45.917-1.024.917-.442 0-.731-.228-.731-.579 0-.342.278-.56.769-.593z"/>
                            </svg>
                        </div>

                        {{-- 4. Google Pay Oficial --}}
                        <div class="h-7 px-2.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="Google Pay">
                            <svg class="h-3.5 w-auto" viewBox="0 0 24 24" fill="#000000" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Google Pay">
                                <path d="M3.963 7.235A3.963 3.963 0 00.422 9.419a3.963 3.963 0 000 3.559 3.963 3.963 0 003.541 2.184c1.07 0 1.97-.352 2.627-.957.748-.69 1.18-1.71 1.18-2.916a4.722 4.722 0 00-.07-.806H3.964v1.526h2.14a1.835 1.835 0 01-.79 1.205c-.356.241-.814.379-1.35.379-1.034 0-1.911-.697-2.225-1.636a2.375 2.375 0 010-1.517c.314-.94 1.191-1.636 2.225-1.636a2.152 2.152 0 011.52.594l1.132-1.13a3.808 3.808 0 00-2.652-1.033zm6.501.55v6.9h.886V11.89h1.465c.603 0 1.11-.196 1.522-.588a1.911 1.911 0 00.635-1.464 1.92 1.92 0 00-.635-1.456 2.125 2.125 0 00-1.522-.598zm2.427.85a1.156 1.156 0 01.823.365 1.176 1.176 0 010 1.686 1.171 1.171 0 01-.877.357H11.35V8.635h1.487a1.156 1.156 0 01.054 0zm4.124 1.175c-.842 0-1.477.308-1.907.925l.781.491c.288-.417.68-.626 1.175-.626a1.255 1.255 0 01.856.323 1.009 1.009 0 01.366.785v.202c-.34-.193-.774-.289-1.3-.289-.617 0-1.11.145-1.479.434-.37.288-.554.677-.554 1.165a1.476 1.476 0 00.525 1.156c.35.308.785.463 1.305.463.61 0 1.098-.27 1.465-.81h.038v.655h.848v-2.909c0-.61-.19-1.09-.568-1.44-.38-.35-.896-.525-1.551-.525zm2.263.154l1.946 4.422-1.098 2.38h.915L24 9.963h-.965l-1.368 3.391h-.02l-1.406-3.39zm-2.146 2.368c.494 0 .88.11 1.156.33 0 .372-.147.696-.44.973a1.413 1.413 0 01-.997.414 1.081 1.081 0 01-.69-.232.708.708 0 01-.293-.578c0-.257.12-.47.363-.647.24-.173.54-.26.9-.26Z"/>
                            </svg>
                        </div>

                        {{-- 5. PayPal Oficial --}}
                        <div class="h-7 px-2.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="PayPal">
                            <svg class="h-4 w-auto" viewBox="0 0 24 24" fill="#003087" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="PayPal">
                                <path d="M15.607 4.653H8.941L6.645 19.251H1.82L4.862 0h7.995c3.754 0 6.375 2.294 6.473 5.513-.648-.478-2.105-.86-3.722-.86m6.57 5.546c0 3.41-3.01 6.853-6.958 6.853h-2.493L11.595 24H6.74l1.845-11.538h3.592c4.208 0 7.346-3.634 7.153-6.949a5.24 5.24 0 0 1 2.848 4.686M9.653 5.546h6.408c.907 0 1.942.222 2.363.541-.195 2.741-2.655 5.483-6.441 5.483H8.714Z"/>
                            </svg>
                        </div>

                        {{-- 6. Mercado Pago Oficial --}}
                        <div class="h-7 px-2.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="Mercado Pago">
                            <svg class="h-4 w-auto" viewBox="0 0 24 24" fill="#009EE3" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Mercado Pago">
                                <path d="M11.115 16.479a.93.927 0 0 1-.939-.886c-.002-.042-.006-.155-.103-.155-.04 0-.074.023-.113.059-.112.103-.254.206-.46.206a.816.814 0 0 1-.305-.066c-.535-.214-.542-.578-.521-.725.006-.038.007-.08-.02-.11l-.032-.03h-.034c-.027 0-.055.012-.093.039a.788.786 0 0 1-.454.16.7.699 0 0 1-.253-.05c-.708-.27-.65-.928-.617-1.126.005-.041-.005-.072-.03-.092l-.05-.04-.047.043a.728.726 0 0 1-.505.203.73.728 0 0 1-.732-.725c0-.4.328-.722.732-.722.364 0 .675.27.721.63l.026.195.11-.165c.01-.018.307-.46.852-.46.102 0 .21.016.316.05.434.13.508.52.519.68.008.094.075.1.09.1.037 0 .064-.024.083-.045a.746.744 0 0 1 .54-.225c.128 0 .263.03.402.09.69.293.379 1.158.374 1.167-.058.144-.061.207-.005.244l.027.013h.02c.03 0 .07-.014.134-.035.093-.032.235-.08.367-.08a.944.942 0 0 1 .94.93.936.934 0 0 1-.94.928zm7.302-4.171c-1.138-.98-3.768-3.24-4.481-3.77-.406-.302-.685-.462-.928-.533a1.559 1.554 0 0 0-.456-.07c-.182 0-.376.032-.58.095-.46.145-.918.505-1.362.854l-.023.018c-.414.324-.84.66-1.164.73a1.986 1.98 0 0 1-.43.049c-.362 0-.687-.104-.81-.258-.02-.025-.007-.066.04-.125l.008-.008 1-1.067c.783-.774 1.525-1.506 3.23-1.545h.085c1.062 0 2.12.469 2.24.524a7.03 7.03 0 0 0 3.056.724c1.076 0 2.188-.263 3.354-.795a9.135 9.11 0 0 0-.405-.317c-1.025.44-2.003.66-2.946.66-.962 0-1.925-.229-2.858-.68-.05-.022-1.22-.567-2.44-.57-.032 0-.065 0-.096.002-1.434.033-2.24.536-2.782.976-.528.013-.982.138-1.388.25-.361.1-.673.186-.979.185-.125 0-.35-.01-.37-.012-.35-.01-2.115-.437-3.518-.962-.143.1-.28.203-.415.31 1.466.593 3.25 1.053 3.812 1.089.157.01.323.027.491.027.372 0 .744-.103 1.104-.203.213-.059.446-.123.692-.17l-.196.194-1.017 1.087c-.08.08-.254.294-.14.557a.705.703 0 0 0 .268.292c.243.162.677.27 1.08.271.152 0 .297-.015.43-.044.427-.095.874-.448 1.349-.82.377-.296.913-.672 1.323-.782a1.494 1.49 0 0 1 .37-.05.611.611 0 0 1 .095.005c.27.034.533.125 1.003.472.835.62 4.531 3.815 4.566 3.846.002.002.238.203.22.537-.007.186-.11.352-.294.466a.902.9 0 0 1-.484.15.804.802 0 0 1-.428-.124c-.014-.01-1.28-1.157-1.746-1.543-.074-.06-.146-.115-.22-.115a.122.122 0 0 0-.096.045c-.073.09.01.212.105.294l1.48 1.47c.002 0 .184.17.204.395.012.244-.106.447-.35.606a.957.955 0 0 1-.526.171.766.764 0 0 1-.42-.127l-.214-.206a21.035 20.978 0 0 0-1.08-1.009c-.072-.058-.148-.112-.221-.112a.127.127 0 0 0-.094.038c-.033.037-.056.103.028.212a.698.696 0 0 0 .075.083l1.078 1.198c.01.01.222.26.024.511l-.038.048a1.18 1.178 0 0 1-.1.096c-.184.15-.43.164-.527.164a.8.798 0 0 1-.147-.012c-.106-.018-.178-.048-.212-.089l-.013-.013c-.06-.06-.602-.609-1.054-.98-.059-.05-.133-.11-.21-.11a.128.128 0 0 0-.096.042c-.09.096.044.24.1.293l.92 1.003a.204.204 0 0 1-.033.062c-.033.044-.144.155-.479.196a.91.907 0 0 1-.122.007c-.345 0-.712-.164-.902-.264a1.343 1.34 0 0 0 .13-.576 1.368 1.365 0 0 0-1.42-1.357c.024-.342-.025-.99-.697-1.274a1.455 1.452 0 0 0-.575-.125c-.146 0-.287.025-.42.075a1.153 1.15 0 0 0-.671-.564 1.52 1.515 0 0 0-.494-.085c-.28 0-.537.08-.767.242a1.168 1.165 0 0 0-.903-.43 1.173 1.17 0 0 0-.82.335c-.287-.217-1.425-.93-4.467-1.613a17.39 17.344 0 0 1-.692-.189 4.822 4.82 0 0 0-.077.494l.67.157c3.108.682 4.136 1.391 4.309 1.525a1.145 1.142 0 0 0-.09.442 1.16 1.158 0 0 0 1.378 1.132c.096.467.406.821.879 1.003a1.165 1.162 0 0 0 .415.08c.09 0 .179-.012.266-.034.086.22.282.493.722.668a1.233 1.23 0 0 0 .457.094c.122 0 .241-.022.355-.063a1.373 1.37 0 0 0 1.269.841c.37.002.726-.147.985-.41.221.121.688.341 1.163.341.06 0 .118-.002.175-.01.47-.059.689-.24.789-.382a.571.57 0 0 0 .048-.078c.11.032.234.058.373.058.255 0 .501-.086.75-.265.244-.174.418-.424.444-.637v-.01c.083.017.167.026.251.026.265 0 .527-.082.773-.242.48-.31.562-.715.554-.98a1.28 1.279 0 0 0 .978-.194 1.04 1.04 0 0 0 .502-.808 1.088 1.085 0 0 0-.16-.653c.804-.342 2.636-1.003 4.795-1.483a4.734 4.721 0 0 0-.067-.492 27.742 27.667 0 0 0-5.049 1.62zm5.123-.763c0 4.027-5.166 7.293-11.537 7.293-6.372 0-11.538-3.266-11.538-7.293 0-4.028 5.165-7.293 11.539-7.293 6.371 0 11.537 3.265 11.537 7.293zm.46.004c0-4.272-5.374-7.755-12-7.755S.002 7.277.002 11.55L0 12.004c0 4.533 4.695 8.203 11.999 8.203 7.347 0 12-3.67 12-8.204z"/>
                            </svg>
                        </div>

                        {{-- 7. Yape Oficial --}}
                        <div class="h-7 px-1.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default overflow-hidden" title="Yape">
                            <img src="{{ asset('images/payments/yape.svg') }}" alt="Yape" class="h-5 w-auto rounded-sm">
                        </div>

                        {{-- 8. Plin Oficial --}}
                        <div class="h-7 px-2.5 bg-[#002D72] rounded-md border border-[#002D72] flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="Plin">
                            <svg class="h-3.5 w-auto" viewBox="0 0 32 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <text x="2" y="11" fill="#00C4FE" font-family="'Plus Jakarta Sans', sans-serif" font-weight="900" font-size="11.5" letter-spacing="-0.5">plin</text>
                                <circle cx="27" cy="9.5" r="1.8" fill="#FF007A"/>
                            </svg>
                        </div>
                    </div>
                </div>

                {{-- Sello de Seguridad SSL --}}
                <div class="mt-4 pt-3 border-t border-white/5 flex items-center gap-2 text-[11px] text-gray-400">
                    <span class="text-emerald-400">🔒</span>
                    <span>Transacciones protegidas con encriptación SSL de 256 bits.</span>
                </div>
            </div>

        </div>
    </div>

    {{-- ── 3. FRANJA MULTI-PAÍS / COBERTURA INTERNACIONAL ── --}}
    @if(!empty($enabledCountries))
    <div class="border-t border-white/5 bg-[#101010] py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-400">
                <div class="flex items-center gap-2 flex-wrap justify-center sm:justify-start">
                    <span class="font-bold text-white flex items-center gap-1">
                        <span>🌍</span> {{ $isEn ? 'International Shipping Available to:' : 'Envíos disponibles a:' }}
                    </span>
                    @foreach($enabledCountries as $code => $country)
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-white/5 border border-white/10 text-[11px] text-gray-300">
                            <img src="{{ $country['flag_url'] ?? \App\Helpers\CurrencyHelper::flagUrl($code) }}" 
                                 alt="{{ $country['name'] }}" 
                                 class="w-4 h-2.5 object-cover rounded-2xs border border-white/20 inline-block shadow-2xs" />
                            <span class="font-medium">{{ $country['name'] }}</span>
                        </span>
                    @endforeach
                </div>
                <div class="text-[11px] text-gray-500 font-mono flex-shrink-0">
                    {{ $isEn ? 'Base Currency: PEN (S/)' : 'Moneda Base: Soles (PEN)' }}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── 4. BARRA DE COPYRIGHT Y VOLVER ARRIBA ── --}}
    <div class="border-t border-white/10 bg-[#0d0d0d] py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-gray-500">
                <div>
                    <p>© {{ date('Y') }} <span class="text-white font-semibold">{{ $store->name }}</span>. {{ $isEn ? 'All rights reserved.' : 'Todos los derechos reservados.' }}</p>
                    <p class="text-[11px] text-gray-600 mt-0.5">
                        {{ $isEn ? 'Powered by' : 'Impulsado por' }} 
                        <a href="https://tribio.com" target="_blank" rel="noopener" class="text-gray-400 hover:text-[#C8A68B] font-bold transition">Tribio eCommerce</a>
                    </p>
                </div>

                <button type="button" @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/10 transition text-xs font-semibold cursor-pointer">
                    <span>Volver arriba</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         MODALES INTERACTIVOS DEL FOOTER
       ═══════════════════════════════════════════════════════════════ --}}

    {{-- MODAL: LIBRO DE RECLAMACIONES --}}
    <div x-show="reclamacionesOpen" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[9999] overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.away="reclamacionesOpen = false"
             class="bg-[#1C1C1C] border border-white/20 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 sm:p-8 text-gray-200 shadow-2xl relative">
            
            {{-- Botón Cerrar --}}
            <button @click="reclamacionesOpen = false" class="absolute top-5 right-5 text-gray-400 hover:text-white p-1.5 rounded-lg hover:bg-white/10 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <div class="flex items-center gap-3 mb-4 pb-4 border-b border-white/10">
                <span class="text-3xl">📖</span>
                <div>
                    <h3 class="text-lg font-bold text-white font-brand">Libro de Reclamaciones Virtual</h3>
                    <p class="text-xs text-gray-400">Razón Comercial: <span class="text-white font-semibold">{{ $store->name }}</span> | Fecha: {{ date('d/m/Y') }}</p>
                </div>
            </div>

            <div x-show="!reclamo.enviado">
                <p class="text-xs text-gray-400 mb-5">
                    Conforme a lo establecido en el Código de Protección y Defensa del Consumidor, esta institución cuenta con un Libro de Reclamaciones a su disposición.
                </p>

                <form @submit.prevent="submitReclamo()" class="space-y-4 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-300 mb-1">Nombres y Apellidos *</label>
                            <input type="text" x-model="reclamo.nombres" required class="w-full bg-white/5 border border-white/10 rounded-lg p-2.5 text-white outline-none focus:border-[#C8A68B]">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-300 mb-1">Documento (DNI / CE / Pasaporte) *</label>
                            <input type="text" x-model="reclamo.documento_num" required class="w-full bg-white/5 border border-white/10 rounded-lg p-2.5 text-white outline-none focus:border-[#C8A68B]" placeholder="Ej: 74581234">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-gray-300 mb-1">Correo Electrónico *</label>
                            <input type="email" x-model="reclamo.email" required class="w-full bg-white/5 border border-white/10 rounded-lg p-2.5 text-white outline-none focus:border-[#C8A68B]" placeholder="tu@correo.com">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-300 mb-1">Teléfono / WhatsApp *</label>
                            <input type="text" x-model="reclamo.telefono" required class="w-full bg-white/5 border border-white/10 rounded-lg p-2.5 text-white outline-none focus:border-[#C8A68B]" placeholder="999 999 999">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-300 mb-1">Dirección / Ciudad</label>
                        <input type="text" x-model="reclamo.direccion" class="w-full bg-white/5 border border-white/10 rounded-lg p-2.5 text-white outline-none focus:border-[#C8A68B]">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-white/5">
                        <div>
                            <label class="block font-bold text-gray-300 mb-1">Tipo de Solicitud</label>
                            <div class="flex gap-4 pt-1">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="Reclamo" x-model="reclamo.tipo" class="text-[#C8A68B]">
                                    <span>Reclamo (Disconformidad con producto)</span>
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="Queja" x-model="reclamo.tipo" class="text-[#C8A68B]">
                                    <span>Queja (Atención al cliente)</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-300 mb-1">Monto o N° Pedido (Opcional)</label>
                            <input type="text" x-model="reclamo.monto" class="w-full bg-white/5 border border-white/10 rounded-lg p-2.5 text-white outline-none focus:border-[#C8A68B]" placeholder="Ej: Pedido #1024 o S/ 150.00">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-300 mb-1">Detalle del Reclamo o Queja *</label>
                        <textarea rows="3" x-model="reclamo.detalle" required class="w-full bg-white/5 border border-white/10 rounded-lg p-2.5 text-white outline-none focus:border-[#C8A68B]" placeholder="Describe claramente lo ocurrido..."></textarea>
                    </div>

                    <div>
                        <label class="block font-bold text-gray-300 mb-1">Pedido del Consumidor</label>
                        <textarea rows="2" x-model="reclamo.pedido" class="w-full bg-white/5 border border-white/10 rounded-lg p-2.5 text-white outline-none focus:border-[#C8A68B]" placeholder="Qué solución solicitas (ej: cambio de producto, reembolso, etc.)..."></textarea>
                    </div>

                    <div class="pt-3 flex justify-end gap-3">
                        <button type="button" @click="reclamacionesOpen = false" class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-gray-300 font-semibold cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#C8A68B] hover:bg-[#b89578] text-[#141414] font-bold transition shadow-lg cursor-pointer">
                            Enviar Hoja de Reclamación
                        </button>
                    </div>
                </form>
            </div>

            {{-- Confirmación de Envío --}}
            <div x-show="reclamo.enviado" style="display: none;" class="text-center py-8">
                <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 rounded-full flex items-center justify-center mx-auto text-3xl mb-4">
                    ✓
                </div>
                <h4 class="text-lg font-bold text-white mb-1">Reclamación Registrada con Éxito</h4>
                <p class="text-xs text-gray-400 mb-4">Se ha generado tu código de seguimiento de atención:</p>
                <div class="inline-block px-4 py-2 rounded-xl bg-white/5 border border-[#C8A68B]/40 font-mono text-[#C8A68B] font-bold text-base mb-4">
                    <span x-text="reclamo.folio"></span>
                </div>
                <p class="text-xs text-gray-400 max-w-md mx-auto mb-6">
                    Conforme a ley, el proveedor dará respuesta en un plazo no mayor a quince (15) días hábiles al correo ingresado (<strong class="text-white" x-text="reclamo.email"></strong>).
                </p>
                <button type="button" @click="reclamacionesOpen = false; resetReclamo();" class="px-6 py-2.5 rounded-xl bg-[#C8A68B] text-[#141414] font-bold text-xs cursor-pointer">
                    Entendido / Cerrar
                </button>
            </div>
        </div>
    </div>

    {{-- MODAL: PREGUNTAS FRECUENTES (FAQ) --}}
    <div x-show="faqOpen" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-[9999] overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.away="faqOpen = false"
             class="bg-[#1C1C1C] border border-white/20 rounded-2xl w-full max-w-2xl p-6 sm:p-8 text-gray-200 shadow-2xl relative">
            
            <button @click="faqOpen = false" class="absolute top-5 right-5 text-gray-400 hover:text-white p-1.5 rounded-lg hover:bg-white/10 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-white/10">
                <span class="text-3xl">❓</span>
                <div>
                    <h3 class="text-lg font-bold text-white font-brand">Preguntas Frecuentes</h3>
                    <p class="text-xs text-gray-400">Respuestas rápidas a las consultas más habituales en {{ $store->name }}</p>
                </div>
            </div>

            <div class="space-y-3 text-xs" x-data="{ activeAccordion: 1 }">
                {{-- FAQ 1 --}}
                <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                    <button type="button" @click="activeAccordion = activeAccordion === 1 ? null : 1" class="w-full flex items-center justify-between font-bold text-left text-white cursor-pointer">
                        <span>¿Cuánto tardan los envíos en llegar?</span>
                        <span x-text="activeAccordion === 1 ? '−' : '+'" class="text-base text-[#C8A68B]"></span>
                    </button>
                    <div x-show="activeAccordion === 1" class="mt-2.5 text-gray-400 leading-relaxed pt-2 border-t border-white/5">
                        Para envíos con stock en Perú las entregas son de 24 a 48 horas. Para provincias a nivel nacional, los envíos se despachan vía Shalom u Olva Courier y toman de 2 a 4 días hábiles dependiendo del destino. Envíos internacionales toman de 15 a 20 días.
                    </div>
                </div>

                {{-- FAQ 2 --}}
                <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                    <button type="button" @click="activeAccordion = activeAccordion === 2 ? null : 2" class="w-full flex items-center justify-between font-bold text-left text-white cursor-pointer">
                        <span>¿Cuáles son las formas de pago aceptadas?</span>
                        <span x-text="activeAccordion === 2 ? '−' : '+'" class="text-base text-[#C8A68B]"></span>
                    </button>
                    <div x-show="activeAccordion === 2" class="mt-2.5 text-gray-400 leading-relaxed pt-2 border-t border-white/5">
                        Aceptamos tarjetas de débito y crédito (Visa, Mastercard, Amex) a través de pasarelas 100% seguras y encriptadas, billeteras digitales como Yape y Plin, y transferencias bancarias directas (BCP, BBVA, Interbank).
                    </div>
                </div>

                {{-- FAQ 3 --}}
                <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                    <button type="button" @click="activeAccordion = activeAccordion === 3 ? null : 3" class="w-full flex items-center justify-between font-bold text-left text-white cursor-pointer">
                        <span>¿Cómo puedo hacer seguimiento a mi pedido?</span>
                        <span x-text="activeAccordion === 3 ? '−' : '+'" class="text-base text-[#C8A68B]"></span>
                    </button>
                    <div x-show="activeAccordion === 3" class="mt-2.5 text-gray-400 leading-relaxed pt-2 border-t border-white/5">
                        Una vez completada tu compra recibirás un correo y mensaje con tu código de seguimiento. Puedes ingresar en la sección "Rastrear mi Pedido" en este portal o escribirnos directamente a nuestro WhatsApp oficial con tu número de orden.
                    </div>
                </div>

                {{-- FAQ 4 --}}
                <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                    <button type="button" @click="activeAccordion = activeAccordion === 4 ? null : 4" class="w-full flex items-center justify-between font-bold text-left text-white cursor-pointer">
                        <span>¿Los productos tienen garantía?</span>
                        <span x-text="activeAccordion === 4 ? '−' : '+'" class="text-base text-[#C8A68B]"></span>
                    </button>
                    <div x-show="activeAccordion === 4" class="mt-2.5 text-gray-400 leading-relaxed pt-2 border-t border-white/5">
                        Sí, todos nuestros artículos cuentan con garantía directa contra defectos de fabricación. Si tu pedido llega con algún inconveniente, nos contactas dentro de los primeros días de recepción para gestionar el cambio inmediato o solución sin costo adicional.
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-white/10 text-center">
                <p class="text-xs text-gray-400 mb-2">¿Tienes alguna otra duda o consulta especial?</p>
                <a href="mailto:{{ $store->contact_email ?: 'maetek.pe@gmail.com' }}" class="inline-flex items-center justify-center px-6 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 text-white font-bold text-sm transition">
                    Escríbenos a: {{ $store->contact_email ?: 'maetek.pe@gmail.com' }}
                </a>
            </div>
        </div>
    </div>

    {{-- MODAL: POLÍTICA DE ENVÍOS Y DEVOLUCIONES --}}
    <div x-show="shippingPolicyOpen" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-[9999] overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
        
        <div @click.away="shippingPolicyOpen = false"
             class="bg-[#1C1C1C] border border-white/20 rounded-2xl w-full max-w-2xl p-6 sm:p-8 text-gray-200 shadow-2xl relative">
            
            <button @click="shippingPolicyOpen = false" class="absolute top-5 right-5 text-gray-400 hover:text-white p-1.5 rounded-lg hover:bg-white/10 cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>

            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-white/10">
                <span class="text-3xl">🚚</span>
                <div>
                    <h3 class="text-lg font-bold text-white font-brand">Políticas de Envío y Cobertura</h3>
                    <p class="text-xs text-gray-400">Información transparente sobre despachos y entregas</p>
                </div>
            </div>

            <div class="space-y-4 text-xs text-gray-300 leading-relaxed">
                <div>
                    <h4 class="font-bold text-white text-sm flex items-center gap-2 mb-1">
                        <span>🇵🇪</span> Envíos a Nivel Nacional (Perú)
                    </h4>
                    <p class="text-gray-400">
                        Realizamos despachos a todos los departamentos y provincias del Perú a través de agencias líderes como <strong>Shalom Empresarial</strong> y <strong>Olva Courier</strong>. Cada envío cuenta con número de remito / guía para monitoreo en tiempo real.
                    </p>
                </div>

                <div>
                    <h4 class="font-bold text-white text-sm flex items-center gap-2 mb-1">
                        <span>🌍</span> Envíos Internacionales
                    </h4>
                    <p class="text-gray-400">
                        Habilitamos envíos transfronterizos a países autorizados con cálculo automático de tarifas al momento del checkout. Los tiempos estimados de transporte varían entre 5 a 12 días útiles.
                    </p>
                </div>

                <div>
                    <h4 class="font-bold text-white text-sm flex items-center gap-2 mb-1">
                        <span>🛡️</span> Garantía de Entrega y Empaque Seguro
                    </h4>
                    <p class="text-gray-400">
                        Todos los productos son revisados y embalados con protección reforzada antes de ser despachados para asegurar que lleguen en perfectas condiciones a tus manos.
                    </p>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-white/10 text-right">
                <button type="button" @click="shippingPolicyOpen = false" class="px-5 py-2 rounded-xl bg-[#C8A68B] text-[#141414] font-bold text-xs cursor-pointer">
                    Entendido
                </button>
            </div>
        </div>
    </div>

</footer>