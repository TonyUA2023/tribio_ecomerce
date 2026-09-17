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
                        <span>✨</span> {{ $isEn ? 'Join the Club' : 'Club Exclusivo' }}
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
                            <span>🔒</span> {{ $isEn ? 'We respect your privacy. No spam ever.' : 'Respetamos tu privacidad. Cero spam garantizado.' }}
                        </p>
                    </div>

                    <div x-show="newsletterSent" style="display: none;"
                         class="p-3.5 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-300 text-xs flex items-center gap-2.5">
                        <span class="text-xl">🎉</span>
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
                    {{-- WhatsApp --}}
                    @php
                        $waDisplay = $store->whatsapp_phone ?? $store->contact_phone ?? '+51 956 183 384';
                    @endphp
                    <a href="{{ $waUrl }}" target="_blank" rel="noopener"
                       class="flex items-center gap-2.5 text-gray-300 hover:text-white transition group">
                        <span class="w-7 h-7 rounded-full bg-[#25D366]/15 border border-[#25D366]/40 flex items-center justify-center text-[#25D366] group-hover:scale-110 transition">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.541 1.961.824 2.796.824 3.183 0 5.77-2.586 5.77-5.766.001-3.181-2.586-5.767-5.77-5.767zm3.393 8.167c-.145.409-.844.757-1.171.808-.328.051-.734.072-2.374-.567-1.64-.639-2.716-2.28-2.798-2.389-.082-.109-.664-.883-.664-1.684 0-.802.422-1.195.572-1.356.15-.16.328-.2.438-.2.11 0 .219.002.315.007.102.005.239-.039.373.284.145.349.492 1.2.535 1.287.043.087.072.19.014.304-.058.114-.087.185-.174.286-.087.102-.183.228-.261.306-.087.087-.178.182-.077.355.101.173.45 1.002 1.328 1.782.721.641 1.332.84 1.52.923.188.083.298.073.409-.055.111-.128.475-.555.602-.745.127-.19.255-.16.429-.095.174.065 1.107.522 1.297.617.19.095.317.142.364.221.047.079.047.458-.098.867z"/></svg>
                        </span>
                        <span class="font-mono font-medium">{{ $waDisplay }}</span>
                        <span class="text-[10px] text-emerald-400 uppercase tracking-wider font-bold">● En línea</span>
                    </a>

                    {{-- Email --}}
                    <a href="mailto:{{ $store->contact_email ?: 'contacto@maetek.com' }}"
                       class="flex items-center gap-2.5 text-gray-300 hover:text-[#C8A68B] transition">
                        <span class="w-7 h-7 rounded-full bg-sky-500/10 border border-sky-500/30 flex items-center justify-center text-sky-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </span>
                        <span class="truncate">{{ $store->contact_email ?: 'contacto@maetek.com' }}</span>
                    </a>

                    {{-- Ubicación --}}
                    <div class="flex items-center gap-2.5 text-gray-400">
                        <span class="w-7 h-7 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </span>
                        <span>{{ $store->city ?: 'Lima' }}</span>
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

                        {{-- WhatsApp Oficial --}}
                        <a href="{{ $waUrl }}" target="_blank" rel="noopener" 
                           class="w-10 h-10 rounded-xl bg-[#25D366] flex items-center justify-center text-white shadow-md hover:scale-110 hover:bg-[#20bd5a] hover:shadow-lg transition-all duration-200" 
                           title="WhatsApp +51 956 183 384">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.969.541 1.961.824 2.796.824 3.183 0 5.77-2.586 5.77-5.766.001-3.181-2.586-5.767-5.77-5.767zm3.393 8.167c-.145.409-.844.757-1.171.808-.328.051-.734.072-2.374-.567-1.64-.639-2.716-2.28-2.798-2.389-.082-.109-.664-.883-.664-1.684 0-.802.422-1.195.572-1.356.15-.16.328-.2.438-.2.11 0 .219.002.315.007.102.005.239-.039.373.284.145.349.492 1.2.535 1.287.043.087.072.19.014.304-.058.114-.087.185-.174.286-.087.102-.183.228-.261.306-.087.087-.178.182-.077.355.101.173.45 1.002 1.328 1.782.721.641 1.332.84 1.52.923.188.083.298.073.409-.055.111-.128.475-.555.602-.745.127-.19.255-.16.429-.095.174.065 1.107.522 1.297.617.19.095.317.142.364.221.047.079.047.458-.098.867z"/></svg>
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
                            <span>🛍️</span> {{ $isEn ? 'All Products' : 'Todos los Productos' }}
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
                                <span>📸</span> {{ $isEn ? 'Photo Gallery' : 'Galería de Fotos' }}
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
                            <span>📦</span> {{ $isEn ? 'Track My Order' : 'Rastrear mi Pedido' }}
                        </button>
                    </li>
                    <li>
                        <button type="button" @click="window.openCustomerModal ? window.openCustomerModal('login') : null"
                                class="hover:text-white transition flex items-center gap-1.5 text-left cursor-pointer">
                            <span>👤</span> {{ $isEn ? 'My Account / Login' : 'Mi Cuenta / Iniciar Sesión' }}
                        </button>
                    </li>
                    <li>
                        <button type="button" @click="faqOpen = true"
                                class="hover:text-white transition flex items-center gap-1.5 text-left cursor-pointer">
                            <span>❓</span> {{ $isEn ? 'Frequently Asked Questions' : 'Preguntas Frecuentes (FAQ)' }}
                        </button>
                    </li>
                    <li>
                        <button type="button" @click="shippingPolicyOpen = true"
                                class="hover:text-white transition flex items-center gap-1.5 text-left cursor-pointer">
                            <span>🚚</span> {{ $isEn ? 'Shipping & Delivery' : 'Envíos y Tiempos de Entrega' }}
                        </button>
                    </li>
                    <li>
                        <a href="{{ route('store.contact', $store->slug) }}" class="hover:text-white transition flex items-center gap-1.5">
                            <span>✉️</span> {{ $isEn ? 'Contact Us' : 'Escríbenos un Mensaje' }}
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
                            <span class="text-2xl group-hover:scale-110 transition-transform">📖</span>
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
                            <svg class="h-3.5 w-auto" viewBox="0 0 48 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M19.1 0.5L12.5 15.5H8.3L5.1 3.3C4.9 2.5 4.7 2.2 4 1.8C3 1.3 1.4 0.8 0 0.5L0.1 0.1H6.9C7.8 0.1 8.6 0.7 8.8 1.8L10.5 10.8L14.7 0.5H19.1ZM35.8 10.6C35.8 6.6 30.3 6.3 30.4 4.5C30.5 4 31 3.4 32.1 3.3C32.7 3.2 34.3 3.2 35.9 3.9L36.6 0.7C35.6 0.3 34.3 0 32.6 0C28.6 0 25.9 2.2 25.8 5.3C25.6 7.6 27.6 8.9 29.2 9.7C30.8 10.5 31.4 11 31.4 11.7C31.3 12.8 30 13.3 28.8 13.3C26.9 13.3 25.7 13 24.8 12.6L24 16C25 16.4 26.7 16.8 28.4 16.8C32.8 16.8 35.7 14.5 35.8 10.6ZM46.3 15.5H50L46.8 0.5H43.3C42.5 0.5 41.8 0.9 41.5 1.7L35.5 15.5H39.8L40.7 13H45.9L46.3 15.5ZM41.9 9.8L43.9 4.1L45.1 9.8H41.9ZM24.7 0.5L21.4 15.5H17.3L20.6 0.5H24.7Z" fill="#1434CB"/>
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
                            <svg class="h-3.5 w-auto text-black" viewBox="0 0 42 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4.6 2.1C5 1.5 5.3 0.7 5.2 0C4.5 0.1 3.7 0.5 3.3 1.1C2.9 1.5 2.6 2.4 2.8 3C3.5 3.1 4.2 2.6 4.6 2.1ZM5.2 3.5C4.2 3.5 3.7 2.9 3 2.9C2.2 2.9 1.6 3.6 1.2 4.4C0.3 6 0.9 8.4 1.8 9.7C2.2 10.4 2.8 11.2 3.5 11.2C4.2 11.2 4.5 10.7 5.4 10.7C6.3 10.7 6.6 11.2 7.3 11.2C8 11.2 8.5 10.5 8.9 9.8C9.4 8.9 9.6 8.5 9.6 8.4C9.5 8.4 8.3 7.9 8.3 6.3C8.3 5 9.3 4.3 9.4 4.2C8.8 3.3 7.8 3.5 7.5 3.5C6.4 3.5 5.7 4.2 5.2 3.5Z"/>
                                <path d="M14.5 1.2H11.8V11.2H13.2V7.4H14.5C16.6 7.4 17.9 6.2 17.9 4.3C17.9 2.4 16.6 1.2 14.5 1.2ZM14.4 6.1H13.2V2.5H14.4C15.8 2.5 16.5 3.2 16.5 4.3C16.5 5.4 15.8 6.1 14.4 6.1ZM23 4.3C21.8 4.3 20.9 4.9 20.5 5.6V4.5H19.2V11.2H20.6V8C20.6 6.7 21.3 5.6 22.4 5.6C22.6 5.6 22.9 5.6 23.2 5.7V4.4C23.1 4.3 23.1 4.3 23 4.3ZM25.6 4.5L27.6 9.6L29.6 4.5H31.1L28.3 11.2L27.3 13.5H25.9L26.9 11.3L24.2 4.5H25.6Z"/>
                            </svg>
                        </div>

                        {{-- 4. Google Pay Oficial --}}
                        <div class="h-7 px-2.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="Google Pay">
                            <svg class="h-3.5 w-auto" viewBox="0 0 45 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8.5 7.7V5.4H16.4C16.5 5.9 16.6 6.4 16.6 7C16.6 8.9 16.1 11.2 14.4 12.8C12.8 14.5 10.9 15.5 8.5 15.5C3.9 15.5 0.2 11.7 0.2 7.1C0.2 2.5 3.9 0 8.5 0C11 0 12.8 0.9 14.1 2.2L12.4 3.9C11.4 3 10.2 2.3 8.5 2.3C5.3 2.3 2.8 4.8 2.8 8C2.8 11.2 5.3 13.7 8.5 13.7C10.5 13.7 11.7 12.9 12.4 12.1C13.1 11.5 13.5 10.4 13.6 9H8.5V7.7Z" fill="#4285F4"/>
                                <path d="M21.5 4.5H19.2V12H20.3V9.3H21.5C23.3 9.3 24.5 8.3 24.5 6.9C24.5 5.5 23.3 4.5 21.5 4.5ZM21.4 8.3H20.3V5.5H21.4C22.6 5.5 23.3 6.1 23.3 6.9C23.3 7.7 22.6 8.3 21.4 8.3ZM28.5 6.8C27.7 6.8 27.1 7.2 26.8 7.7V7H25.8V12H26.8V9.6C26.8 8.6 27.4 7.8 28.3 7.8C28.5 7.8 28.7 7.8 28.9 7.9V6.9C28.8 6.8 28.7 6.8 28.5 6.8ZM30.8 7L32.4 10.7L34 7H35.1L33 12L32.2 13.7H31.1L31.8 12.1L29.7 7H30.8Z" fill="#5F6368"/>
                            </svg>
                        </div>

                        {{-- 5. PayPal Oficial --}}
                        <div class="h-7 px-2.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="PayPal">
                            <svg class="h-3.5 w-auto" viewBox="0 0 54 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5.5 12.5L7.2 1.8H11.3C13.7 1.8 15.1 2.9 14.7 5.4C14.3 7.8 12.6 9.1 10.4 9.1H8.4L7.5 14.8H5.1L5.5 12.5Z" fill="#003087"/>
                                <path d="M7.9 12.5L9.6 1.8H13.7C16.1 1.8 17.5 2.9 17.1 5.4C16.7 7.8 15 9.1 12.8 9.1H10.8L9.9 14.8H7.5L7.9 12.5Z" fill="#0079C1"/>
                                <path d="M10.4 9.1H12.4C14.5 9.1 16.3 7.8 16.7 5.4C17.1 2.9 15.7 1.8 13.3 1.8H9.6L7.9 12.5H9.8L10.4 9.1Z" fill="#00457C"/>
                                <text x="19" y="11.5" fill="#003087" font-family="'Plus Jakarta Sans', sans-serif" font-weight="800" font-size="9.5" letter-spacing="-0.3">Pay<tspan fill="#0079C1">Pal</tspan></text>
                            </svg>
                        </div>

                        {{-- 6. Mercado Pago Oficial --}}
                        <div class="h-7 px-2.5 bg-white rounded-md border border-white/20 flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="Mercado Pago">
                            <svg class="h-4 w-auto" viewBox="0 0 68 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7.5 3C6.8 2.3 5.8 2 4.8 2C3.8 2.1 2.9 2.6 2.3 3.4C1.7 4.2 1.5 5.3 1.8 6.3L3.3 11.8C3.6 12.6 4.4 13.3 5.3 13.4C6.3 13.5 7.1 13 7.5 12.2L7.9 11.3C8.2 10.8 8.8 10.5 9.4 10.6H9.9C10.7 10.8 11.5 10.5 11.9 9.8L12.5 8.8C12.8 8.3 13.3 8 13.9 8H14.5" stroke="#009EE3" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M7.2 5.5L9.5 7.8" stroke="#009EE3" stroke-width="1.5" stroke-linecap="round"/>
                                <text x="17" y="11.5" fill="#009EE3" font-family="'Plus Jakarta Sans', sans-serif" font-weight="800" font-size="8.5" letter-spacing="-0.2">mercado pago</text>
                            </svg>
                        </div>

                        {{-- 7. Yape Oficial --}}
                        <div class="h-7 px-2 bg-[#742284] rounded-md border border-[#742284] flex items-center justify-center shadow-xs hover:scale-105 transition-transform cursor-default" title="Yape">
                            <svg class="h-3.5 w-auto" viewBox="0 0 36 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.5 2L8.8 6V11H10.4V6L12.7 2H10.9L9.6 4.5L8.3 2H6.5Z" fill="#FFFFFF"/>
                                <path d="M14 5.5C14 4.5 14.7 3.8 15.8 3.8C16.8 3.8 17.4 4.4 17.5 5.3H16C15.8 4.9 15.5 4.8 15.2 4.8C14.8 4.8 14.5 5.1 14.5 5.6C14.5 6.1 14.8 6.5 15.2 6.5C15.6 6.5 15.8 6.3 16 6H17.4C17.2 6.9 16.5 7.6 15.5 7.6C14.4 7.6 13.8 6.8 13.8 5.8V5.5Z" fill="#FFFFFF"/>
                                <path d="M18.8 4H20.1V4.7C20.4 4.2 21 3.9 21.6 3.9C22.8 3.9 23.5 4.7 23.5 5.9C23.5 7.1 22.7 7.9 21.6 7.9C21 7.9 20.4 7.6 20.1 7.2V10.5H18.8V4ZM20.1 5.9C20.1 6.4 20.4 6.8 20.9 6.8C21.4 6.8 21.9 6.4 21.9 5.9C21.9 5.4 21.4 5 20.9 5C20.4 5 20.1 5.4 20.1 5.9Z" fill="#FFFFFF"/>
                                <path d="M25 5.7C25 4.6 25.8 3.8 26.9 3.8C28 3.8 28.8 4.6 28.8 5.8V6.1H26.2C26.3 6.5 26.6 6.8 27.1 6.8C27.5 6.8 27.7 6.6 27.9 6.3H29.1C28.8 7.1 28.1 7.7 27 7.7C25.8 7.7 25 6.8 25 5.7ZM27.6 5.2C27.6 4.8 27.2 4.5 26.9 4.5C26.4 4.5 26.1 4.8 26.1 5.2H27.6Z" fill="#FFFFFF"/>
                                <circle cx="32" cy="3" r="1.5" fill="#00D4B2"/>
                            </svg>
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
                        En Lima metropolitana las entregas suelen realizarse entre 24 a 48 horas hábiles. Para provincias a nivel nacional, los envíos se despachan vía Shalom u Olva Courier y toman de 2 a 4 días hábiles dependiendo del destino. Envíos internacionales toman entre 5 a 10 días laborables.
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
                <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition">
                    <span>💬</span> Chatear con un Asesor por WhatsApp
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