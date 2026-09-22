<!-- Pricing Checkout Modal -->
    <div x-show="openModal"
         class="th-plan-modal fixed inset-0 z-50 overflow-y-auto"
         role="dialog" aria-modal="true" aria-labelledby="plan-modal-title"
         style="display: none;"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
         
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="openModal = false"></div>

        <!-- Modal Wrapper -->
        <div class="flex min-h-screen items-center justify-center p-4 text-center">
            <div class="relative w-full max-w-2xl transform overflow-hidden rounded-3xl bg-white p-6 sm:p-10 text-left shadow-2xl transition-all border border-slate-100"
                 @click.stop>
                
                <!-- Close Button -->
                <button type="button" @click="openModal = false" class="absolute top-6 right-6 text-slate-400 hover:text-slate-500 text-sm">
                    ✕
                </button>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                    <!-- Left: Plan Summary -->
                    <div class="md:col-span-5 bg-slate-50 rounded-2xl p-6 border border-slate-100 flex flex-col justify-between">
                        <div>
                            <span class="text-[9px] font-black text-sky-500 tracking-widest block mb-1">Tu plan</span>
                            <h3 class="text-xl font-extrabold text-slate-900 leading-tight" x-text="selectedPlanLabel"></h3>
                            
                            <div class="flex items-end gap-1 mt-4">
                                <span class="text-slate-400 text-xs font-bold">S/.</span>
                                <span class="text-3xl font-black text-slate-900 leading-none" x-text="selectedPlanPrice"></span>
                                <span class="text-slate-400 text-[10px] font-bold mb-0.5">/mes</span>
                            </div>

                            <ul class="space-y-2.5 mt-6 border-t border-slate-200/60 pt-4 text-[11px] text-slate-600 font-medium">
                                <template x-for="feat in selectedPlanFeatures">
                                    <li class="flex items-start gap-2">
                                        <span class="text-sky-500 font-bold">✓</span>
                                        <span x-text="feat" class="pt-0.5"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <!-- Trust Seal -->
                        <div class="mt-6 border-t border-slate-200/60 pt-4 flex items-center gap-2 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <span>Pago seguro con Culqi</span>
                        </div>
                    </div>

                    <!-- Right: Registration Form -->
                    <div class="md:col-span-7 space-y-4">
                        <div class="border-b border-slate-100 pb-3">
                            <h3 id="plan-modal-title" class="text-base font-extrabold text-slate-900" x-text="isAuthenticated ? 'Configura tu nueva tienda' : (showLoginForm ? 'Inicia sesión con tu Tribio Pass' : 'Configura tu cuenta y tienda')"></h3>
                            <p class="text-xs text-slate-400" x-text="isAuthenticated ? 'Tu Tribio Pass puede tener más de una tienda — esta se suma a tu cuenta.' : (showLoginForm ? 'Usa tu cuenta existente y continúa directo a configurar tu tienda.' : 'Ingresa tus datos de acceso y la dirección que tendrá tu tienda virtual.')"></p>
                        </div>

                        <!-- Laravel Errors Display in Modal -->
                        @if($errors->any())
                            <div class="p-3.5 rounded-2xl bg-red-500/10 border border-red-500/20 text-red-600 text-xs space-y-1">
                                @foreach($errors->all() as $e)
                                    <p>• {{ $e }}</p>
                                @endforeach
                            </div>
                        @endif

                        <!-- Login sub-form: guest who already has a Tribio Pass -->
                        <template x-if="!isAuthenticated && showLoginForm">
                            <div class="space-y-3.5">
                                <template x-if="loginError">
                                    <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-600 text-xs" x-text="loginError"></div>
                                </template>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Correo Electrónico</label>
                                    <input type="email" x-model="loginEmail" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="juan@correo.com">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Contraseña</label>
                                    <input type="password" x-model="loginPassword" @keydown.enter.prevent="doModalLogin()" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Tu contraseña">
                                </div>
                                <button type="button" @click="doModalLogin()" :disabled="loginBusy" class="w-full py-3 font-bold bg-sky-500 hover:bg-sky-600 disabled:opacity-60 text-white text-xs uppercase tracking-wider rounded-xl transition-all">
                                    <span x-text="loginBusy ? 'Ingresando...' : 'Iniciar sesión y continuar'"></span>
                                </button>
                                @if($googleReady)
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-px bg-slate-100"></div>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">o</span>
                                    <div class="flex-1 h-px bg-slate-100"></div>
                                </div>
                                <a :href="'{{ route('auth.google.redirect') }}?plan_key=' + selectedPlan" class="w-full flex items-center justify-center gap-2.5 py-3 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all">
                                    <svg class="w-4 h-4" viewBox="0 0 48 48">
                                        <path fill="#FFC107" d="M43.6 20.5h-1.9V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.8-.4-4.5z"/>
                                        <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 16.3 3 9.7 7.3 6.3 14.7z"/>
                                        <path fill="#4CAF50" d="M24 45c5.5 0 10.4-1.9 14.1-5.1l-6.5-5.5C29.6 36 26.9 37 24 37c-5.2 0-9.6-3.3-11.2-8l-6.6 5C9.6 40.6 16.3 45 24 45z"/>
                                        <path fill="#1976D2" d="M43.6 20.5h-1.9V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.6l6.5 5.5c-.5.4 7.4-5.4 7.4-15.6 0-1.4-.1-2.8-.4-4.5z"/>
                                    </svg>
                                    Continuar con Google
                                </a>
                                @endif
                                <button type="button" @click="showLoginForm = false" class="w-full text-center text-[11px] text-slate-400 hover:text-slate-600">← Prefiero crear una cuenta nueva</button>
                            </div>
                        </template>

                        <!-- Store purchase form: either already authenticated, or the guest registration path -->
                        <form x-show="isAuthenticated || !showLoginForm" method="POST" action="{{ route('plan.checkout') }}" class="space-y-3.5">
                            @csrf
                            <input type="hidden" name="plan_key" :value="selectedPlan">

                            <template x-if="isAuthenticated">
                                <div class="p-3 rounded-xl bg-sky-50 border border-sky-100 text-xs text-slate-700">
                                    Comprando como <strong x-text="authName"></strong> <span class="text-slate-400" x-text="'(' + authEmail + ')'"></span>
                                </div>
                            </template>

                            @if($googleReady)
                            <div x-show="!isAuthenticated">
                                <a :href="'{{ route('auth.google.redirect') }}?plan_key=' + selectedPlan" class="w-full flex items-center justify-center gap-2.5 py-3 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition-all">
                                    <svg class="w-4 h-4" viewBox="0 0 48 48">
                                        <path fill="#FFC107" d="M43.6 20.5h-1.9V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.8-.4-4.5z"/>
                                        <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 16.3 3 9.7 7.3 6.3 14.7z"/>
                                        <path fill="#4CAF50" d="M24 45c5.5 0 10.4-1.9 14.1-5.1l-6.5-5.5C29.6 36 26.9 37 24 37c-5.2 0-9.6-3.3-11.2-8l-6.6 5C9.6 40.6 16.3 45 24 45z"/>
                                        <path fill="#1976D2" d="M43.6 20.5h-1.9V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.6l6.5 5.5c-.5.4 7.4-5.4 7.4-15.6 0-1.4-.1-2.8-.4-4.5z"/>
                                    </svg>
                                    Continuar con Google
                                </a>
                                <div class="flex items-center gap-3 my-3.5">
                                    <div class="flex-1 h-px bg-slate-100"></div>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">o regístrate con tu correo</span>
                                    <div class="flex-1 h-px bg-slate-100"></div>
                                </div>
                            </div>
                            @endif

                            <div class="grid grid-cols-2 gap-3" x-show="!isAuthenticated">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre Completo *</label>
                                    <input type="text" name="name" value="{{ old('name') }}" :required="!isAuthenticated" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Ej: Juan Pérez">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Correo Electrónico *</label>
                                    <input type="email" name="email" value="{{ old('email') }}" :required="!isAuthenticated" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="juan@correo.com">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3" x-show="!isAuthenticated">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Contraseña *</label>
                                    <input type="password" name="password" :required="!isAuthenticated" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Mínimo 8 caracteres">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Confirmar Contraseña *</label>
                                    <input type="password" name="password_confirmation" :required="!isAuthenticated" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Repite la contraseña">
                                </div>
                            </div>

                            <template x-if="!isAuthenticated">
                                <button type="button" @click="showLoginForm = true" class="text-[11px] text-sky-600 hover:text-sky-700 font-semibold -mt-1">¿Ya tienes Tribio Pass? Inicia sesión</button>
                            </template>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nombre de Tienda *</label>
                                    <input type="text" name="store_name" value="{{ old('store_name') }}" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Ej: Repuestos El Sol">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Celular / WhatsApp *</label>
                                    <input type="text" name="whatsapp_phone" value="{{ old('whatsapp_phone') }}" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900" placeholder="Ej: 987654321">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Dirección URL de tu Tienda *</label>
                                    <div class="flex items-center">
                                        <span class="text-[10px] text-slate-400 font-bold bg-slate-50 border border-r-0 border-slate-200 rounded-l-xl px-2.5 py-2.5">tribio.pe/</span>
                                        <input type="text" name="store_slug" value="{{ old('store_slug') }}" required class="w-full px-3 py-2 text-xs rounded-r-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900 font-semibold" placeholder="mi-tienda">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Categoría del Negocio *</label>
                                    <select name="store_category" required class="w-full px-3 py-2.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:border-sky-500 text-slate-900 bg-white">
                                        <option value="">Seleccionar...</option>
                                        @foreach(config('tribio.business_categories') as $key => $cat)
                                            <option value="{{ $key }}" {{ old('store_category') === $key ? 'selected' : '' }}>{{ $cat['icon'] }} {{ $cat['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <button type="submit"
                                    class="w-full py-3.5 px-4 font-bold bg-sky-500 hover:bg-sky-600 text-white text-xs uppercase tracking-wider rounded-xl transition-all shadow-md shadow-sky-500/10 flex items-center justify-center gap-2.5 mt-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                Continuar → Ver resumen y pagar
                            </button>
                            <p class="text-[11px] leading-relaxed text-slate-500">Al continuar, confirmas que leíste los <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-sky-700 underline">Términos y Condiciones</a> y la <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-sky-700 underline">Política de Privacidad y Tratamiento de Datos</a>.</p>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

