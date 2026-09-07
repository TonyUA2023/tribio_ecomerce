@extends('layouts.public')
@section('title', 'Crear mi tienda — Tribio')

@section('content')
<div class="min-h-screen bg-[#F6F6F6] pt-28 pb-16"
     x-data="registerWizard()" x-init="init()">

    <div class="container-tribio">
        <div class="max-w-2xl mx-auto">

            {{-- Header --}}
            <div class="text-center mb-10">
                <h1 class="text-3xl font-black text-slate-900 mb-2">
                    Crea tu <span class="text-sky-500">tienda virtual</span>
                </h1>
                <p class="text-slate-500">Configura tu negocio en 4 pasos rápidos</p>
            </div>

            {{-- Indicador de pasos --}}
            <div class="flex items-center gap-3 mb-10">
                @php $stepLabels = ['Tu cuenta', 'Tu negocio', 'Tu logo', 'Tu diseño']; @endphp
                @for($i = 1; $i <= 4; $i++)
                <div class="flex items-center gap-3 {{ $i < 4 ? 'flex-1' : '' }}">
                    <div class="step-circle"
                         :class="{
                            'active': currentStep === {{ $i }},
                            'done': currentStep > {{ $i }}
                         }">
                        <template x-if="currentStep > {{ $i }}">✓</template>
                        <template x-if="currentStep <= {{ $i }}">{{ $i }}</template>
                    </div>
                    <span class="text-xs font-semibold hidden sm:block"
                          :class="currentStep >= {{ $i }} ? 'text-slate-800' : 'text-slate-400'">
                        {{ $stepLabels[$i - 1] }}
                    </span>
                    @if($i < 4)
                    <div class="flex-1 h-px animate-fade-in" :class="currentStep > {{ $i }} ? 'bg-sky-500' : 'bg-slate-200'"></div>
                    @endif
                </div>
                @endfor
            </div>

            {{-- Errores --}}
            @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-550/10 border border-red-100 bg-red-50 text-red-650 text-sm">
                <ul class="space-y-1 list-disc list-inside">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" id="registerForm">
                @csrf

                {{-- ══════════ PASO 1: Cuenta ══════════ --}}
                <div x-show="currentStep === 1" x-transition class="glass-card p-8 space-y-5">
                    <div class="mb-6">
                        <h2 class="text-xl font-extrabold text-slate-900">👤 Tu cuenta personal</h2>
                        <p class="text-slate-450 text-sm mt-1">Estos son los datos de acceso a tu panel.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div class="sm:col-span-2">
                            <label class="input-label">Nombre completo *</label>
                            <input type="text" name="name" id="name" x-model="form.name"
                                   placeholder="Ej: María García"
                                   value="{{ old('name') }}"
                                   class="input-field">
                        </div>
                        <div>
                            <label class="input-label">Correo electrónico *</label>
                            <input type="email" name="email" id="email" x-model="form.email"
                                   placeholder="tu@correo.com"
                                   class="input-field">
                        </div>
                        <div>
                            <label class="input-label">Teléfono *</label>
                            <input type="tel" name="phone" id="phone" x-model="form.phone"
                                   placeholder="+51 900 000 000"
                                   value="{{ old('phone') }}"
                                   class="input-field">
                        </div>
                        <div>
                            <label class="input-label">Contraseña *</label>
                            <input type="password" name="password" id="password" x-model="form.password"
                                   placeholder="Mínimo 8 caracteres"
                                   class="input-field">
                        </div>
                        <div>
                            <label class="input-label">Confirmar contraseña *</label>
                            <input type="password" name="password_confirmation" x-model="form.password_confirmation"
                                   placeholder="Repite tu contraseña"
                                   class="input-field">
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="button" @click="nextStep(1)" class="btn-primary btn-primary-lg">
                            Continuar → Negocio
                        </button>
                    </div>
                </div>

                {{-- ══════════ PASO 2: Negocio ══════════ --}}
                <div x-show="currentStep === 2" x-transition class="glass-card p-8 space-y-5">
                    <div class="mb-6">
                        <h2 class="text-xl font-extrabold text-slate-900">🏪 Tu negocio</h2>
                        <p class="text-slate-455 text-sm mt-1">Cuéntanos sobre tu empresa o emprendimiento.</p>
                    </div>

                    <div>
                        <label class="input-label">Nombre de tu negocio *</label>
                        <input type="text" name="store_name" id="store_name" x-model="form.store_name"
                               placeholder="Ej: Calzados María"
                               value="{{ old('store_name') }}"
                               class="input-field">
                    </div>

                    <div>
                        <label class="input-label">Dirección web de tu tienda *</label>
                        <div class="flex items-center gap-0">
                            <span class="px-4 py-3 bg-slate-100 border border-slate-200 rounded-l-xl text-slate-500 text-sm whitespace-nowrap border-r-0 select-none">
                                tribio.pe/tienda/
                            </span>
                            <input type="text" name="store_slug" id="store_slug" x-model="form.store_slug"
                                   placeholder="mi-negocio"
                                   value="{{ old('store_slug') }}"
                                   class="input-field rounded-l-none border-l-0"
                                   style="border-radius: 0 0.875rem 0.875rem 0;">
                        </div>
                        <p class="text-slate-400 text-xs mt-2">Solo letras minúsculas, números y guiones. Se genera automáticamente.</p>
                    </div>

                    <div>
                        <label class="input-label">Tipo de negocio *</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach(config('tribio.business_categories') as $key => $cat)
                            <label class="cursor-pointer">
                                <input type="radio" name="store_category" value="{{ $key }}" x-model="form.store_category"
                                       {{ old('store_category') === $key ? 'checked' : '' }}
                                       class="hidden peer">
                                <div class="p-4 rounded-2xl border border-slate-200 text-center transition-all bg-white hover:bg-slate-50 peer-checked:border-sky-500 peer-checked:bg-sky-50/50 peer-checked:ring-2 peer-checked:ring-sky-100">
                                    <span class="text-2xl block mb-1">{{ $cat['icon'] }}</span>
                                    <span class="text-xs text-slate-700 font-bold block">{{ $cat['label'] }}</span>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="input-label">WhatsApp del negocio</label>
                        <input type="tel" name="whatsapp_phone" x-model="form.whatsapp_phone"
                               placeholder="51900000000 (con código de país)"
                               value="{{ old('whatsapp_phone') }}"
                               class="input-field">
                        <p class="text-slate-400 text-xs mt-1">Aquí llegarán tus pedidos. Ej: 51902699916</p>
                    </div>

                    <div class="flex justify-between pt-2">
                        <button type="button" @click="currentStep = 1" class="btn-ghost">← Atrás</button>
                        <button type="button" @click="nextStep(2)" class="btn-primary btn-primary-lg">
                            Continuar → Logo
                        </button>
                    </div>
                </div>

                {{-- ══════════ PASO 3: Logo ══════════ --}}
                <div x-show="currentStep === 3" x-transition class="glass-card p-8">
                    <div class="mb-8">
                        <h2 class="text-xl font-extrabold text-slate-900">🖼️ El logo de tu negocio</h2>
                        <p class="text-slate-450 text-sm mt-1">Sube tu logo en formato PNG (fondo transparente recomendado). Puedes omitir este paso.</p>
                    </div>

                    <div class="flex flex-col items-center gap-6">
                        {{-- Preview --}}
                        <div class="w-32 h-32 rounded-2xl bg-slate-50 border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden relative group cursor-pointer"
                             onclick="document.getElementById('logo_input').click()">
                            <img id="logo_preview" src="" alt="" class="w-full h-full object-cover hidden">
                            <div id="logo_placeholder" class="text-center">
                                <div class="text-4xl mb-2">🏪</div>
                                <p class="text-slate-400 text-xs font-semibold">Click para subir</p>
                            </div>
                            <div class="absolute inset-0 bg-sky-500/10 opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl flex items-center justify-center">
                                <span class="text-sky-600 text-xs font-bold bg-white px-2.5 py-1 rounded-full shadow-sm">Cambiar</span>
                            </div>
                        </div>

                        <input type="file" name="logo" id="logo_input" accept="image/png,image/jpg,image/jpeg,image/webp"
                               data-preview="logo_preview" class="hidden"
                               onchange="previewLogo(this)">

                        <div class="text-center">
                            <button type="button" onclick="document.getElementById('logo_input').click()"
                                    class="btn-secondary">
                                📁 Seleccionar imagen
                            </button>
                            <p class="text-slate-400 text-xs mt-3">PNG, JPG o WebP • Máx. 2MB</p>
                        </div>

                        {{-- Color del acento --}}
                        <div class="w-full">
                            <label class="input-label text-center block">Color principal de tu tienda</label>
                            <div class="flex items-center gap-4 justify-center mt-3">
                                <input type="color" name="accent_color" id="accent_color_picker" value="#0ea5e9"
                                       class="w-12 h-12 rounded-xl cursor-pointer border-0 bg-transparent"
                                       onchange="document.getElementById('accent_hex').value = this.value">
                                <input type="text" id="accent_hex" value="#0ea5e9"
                                       placeholder="#0ea5e9"
                                       class="input-field w-36 text-center font-mono"
                                       onchange="document.getElementById('accent_color_picker').value = this.value">
                            </div>
                            {{-- Paletas predefinidas --}}
                            <div class="flex gap-3 justify-center mt-4 flex-wrap">
                                @foreach(['#0ea5e9','#2563eb','#4f46e5','#10b981','#f59e0b','#ef4444','#ec4899','#14b8a6'] as $color)
                                <button type="button" onclick="setColor('{{ $color }}')"
                                        class="w-8 h-8 rounded-lg transition-transform hover:scale-110 border-2 border-transparent hover:border-slate-400 shadow-sm"
                                        style="background: {{ $color }};" title="{{ $color }}"></button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between pt-8">
                        <button type="button" @click="currentStep = 2" class="btn-ghost">← Atrás</button>
                        <div class="flex gap-3">
                            <button type="button" @click="nextStep(3)" class="btn-ghost">Omitir</button>
                            <button type="button" @click="nextStep(3)" class="btn-primary btn-primary-lg">
                                Continuar → Diseño
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ══════════ PASO 4: Plantilla ══════════ --}}
                <div x-show="currentStep === 4" x-transition class="glass-card p-8">
                    <div class="mb-8">
                        <h2 class="text-xl font-extrabold text-slate-900">🎨 Elige el diseño de tu tienda</h2>
                        <p class="text-slate-450 text-sm mt-1">Cada plantilla puede ser personalizada después. Puedes cambiarla cuando quieras.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                        @foreach(config('tribio.templates') as $key => $template)
                        <label class="cursor-pointer">
                            <input type="radio" name="template_name" value="{{ $key }}"
                                   {{ old('template_name', 'elegant-dark') === $key ? 'checked' : '' }}
                                   class="hidden peer">
                            <div class="template-card rounded-2xl overflow-hidden bg-white border border-slate-200 transition-all hover:shadow-md">
                                {{-- Preview simulado --}}
                                <div class="h-36 relative overflow-hidden
                                    {{ $key === 'elegant-dark' ? 'bg-gradient-to-br from-gray-900 via-purple-900/30 to-gray-900' :
                                       ($key === 'minimal-light' ? 'bg-gradient-to-br from-gray-50 to-white' :
                                       'bg-gradient-to-br from-pink-50 via-yellow-50 to-orange-50') }}">
                                    {{-- Elementos decorativos del preview --}}
                                    <div class="absolute inset-x-4 top-4 h-8 rounded-lg opacity-80
                                        {{ $key === 'elegant-dark' ? 'bg-gradient-to-r from-purple-600 to-pink-500' :
                                           ($key === 'minimal-light' ? 'bg-gray-800' :
                                           'bg-gradient-to-r from-pink-400 to-yellow-400') }}">
                                    </div>
                                    <div class="absolute inset-x-4 bottom-4 grid grid-cols-3 gap-1">
                                        @for($j = 0; $j < 3; $j++)
                                        <div class="h-10 rounded-lg
                                            {{ $key === 'elegant-dark' ? 'bg-white/10' :
                                               ($key === 'minimal-light' ? 'bg-gray-100' :
                                               'bg-white/70') }}">
                                        </div>
                                        @endfor
                                    </div>
                                    {{-- Selected checkmark --}}
                                    <div class="selected-checkmark absolute top-2 right-2 hidden w-6 h-6 rounded-full bg-sky-500 items-center justify-center text-white text-xs font-bold shadow-sm">✓</div>
                                </div>
                                <div class="p-4 bg-white">
                                    <p class="text-slate-800 font-extrabold text-sm">{{ $template['name'] }}</p>
                                    <p class="text-slate-400 text-xs mt-1 line-clamp-2 leading-relaxed">{{ $template['description'] }}</p>
                                    <div class="flex gap-1 mt-3 flex-wrap">
                                        @foreach($template['tags'] as $tag)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-50 text-sky-600 border border-sky-100">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </label>
                        @endforeach
                    </div>

                    <div class="flex justify-between">
                        <button type="button" @click="currentStep = 3" class="btn-ghost">← Atrás</button>
                        <button type="submit" class="btn-gold btn-primary-lg">
                            🚀 Crear mi tienda ahora
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function registerWizard() {
    return {
        currentStep: 1,
        form: { 
            name: '', 
            email: '{{ request('email', old('email', '')) }}', 
            phone: '{{ old('phone', '') }}',
            password: '',
            password_confirmation: '',
            store_name: '{{ old('store_name', '') }}',
            store_slug: '{{ old('store_slug', '') }}',
            store_category: '{{ old('store_category', '') }}',
            whatsapp_phone: '{{ old('whatsapp_phone', '') }}'
        },

        init() {
            // Restaurar errores al paso correcto si hay validación fallida
            @if($errors->any())
                this.currentStep = {{ old('_step', 1) }};
            @endif

            // Auto-generación de slug en base a store_name
            this.$watch('form.store_name', value => {
                this.form.store_slug = value
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9\s-]/g, '')
                    .trim()
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .slice(0, 60);
            });
        },

        nextStep(step) {
            if (step === 1) {
                if (!this.form.name || !this.form.email || !this.form.phone) {
                    alert('Por favor completa tu nombre, correo y teléfono.');
                    return;
                }
                if (!this.form.password) {
                    alert('Por favor ingresa una contraseña.');
                    return;
                }
                if (this.form.password.length < 8) {
                    alert('La contraseña debe tener al menos 8 caracteres.');
                    return;
                }
                if (this.form.password !== this.form.password_confirmation) {
                    alert('La confirmación de la contraseña no coincide.');
                    return;
                }
            }
            if (step === 2) {
                if (!this.form.store_name) {
                    alert('Por favor ingresa el nombre de tu negocio.');
                    return;
                }
                if (!this.form.store_slug) {
                    alert('Por favor ingresa la dirección web de tu tienda.');
                    return;
                }
                const slugPattern = /^[a-z0-9-]+$/;
                if (!slugPattern.test(this.form.store_slug)) {
                    alert('La dirección web solo puede contener letras minúsculas, números y guiones.');
                    return;
                }
                if (!this.form.store_category) {
                    alert('Por favor selecciona un tipo de negocio.');
                    return;
                }
            }
            this.currentStep = step + 1;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };
}

function previewLogo(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        const img = document.getElementById('logo_preview');
        const ph  = document.getElementById('logo_placeholder');
        img.src = e.target.result;
        img.classList.remove('hidden');
        ph.classList.add('hidden');
    };
    reader.readAsDataURL(file);
}

function setColor(hex) {
    document.getElementById('accent_color_picker').value = hex;
    document.getElementById('accent_hex').value = hex;
}
</script>
@endpush
@endsection
