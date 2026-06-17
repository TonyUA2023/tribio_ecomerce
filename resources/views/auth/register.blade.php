@extends('layouts.public')
@section('title', 'Crear mi tienda — Tribio')

@section('content')
<div class="min-h-screen hero-bg grid-pattern pt-24 pb-16"
     x-data="registerWizard()" x-init="init()">

    <div class="container-tribio">
        <div class="max-w-2xl mx-auto">

            {{-- Header --}}
            <div class="text-center mb-10">
                <h1 class="text-3xl font-black text-white mb-2">
                    Crea tu <span class="gradient-text">Tienda Virtual</span>
                </h1>
                <p class="text-white/50">Configura tu negocio en 4 pasos rápidos</p>
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
                    <span class="text-xs font-medium hidden sm:block"
                          :class="currentStep >= {{ $i }} ? 'text-white' : 'text-white/30'">
                        {{ $stepLabels[$i - 1] }}
                    </span>
                    @if($i < 4)
                    <div class="flex-1 h-px" :class="currentStep > {{ $i }} ? 'bg-tribio-purple' : 'bg-white/10'"></div>
                    @endif
                </div>
                @endfor
            </div>

            {{-- Errores --}}
            @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
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
                        <h2 class="text-xl font-bold text-white">👤 Tu cuenta personal</h2>
                        <p class="text-white/40 text-sm mt-1">Estos son los datos de acceso a tu panel.</p>
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
                                   value="{{ old('email') }}"
                                   class="input-field">
                        </div>
                        <div>
                            <label class="input-label">Teléfono (opcional)</label>
                            <input type="tel" name="phone" id="phone"
                                   placeholder="+51 900 000 000"
                                   value="{{ old('phone') }}"
                                   class="input-field">
                        </div>
                        <div>
                            <label class="input-label">Contraseña *</label>
                            <input type="password" name="password" id="password"
                                   placeholder="Mínimo 8 caracteres"
                                   class="input-field">
                        </div>
                        <div>
                            <label class="input-label">Confirmar contraseña *</label>
                            <input type="password" name="password_confirmation"
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
                        <h2 class="text-xl font-bold text-white">🏪 Tu negocio</h2>
                        <p class="text-white/40 text-sm mt-1">Cuéntanos sobre tu empresa o emprendimiento.</p>
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
                            <span class="px-4 py-3 bg-white/5 border border-white/10 rounded-l-xl text-white/40 text-sm whitespace-nowrap border-r-0">
                                tribio.pe/tienda/
                            </span>
                            <input type="text" name="store_slug" id="store_slug"
                                   placeholder="mi-negocio"
                                   value="{{ old('store_slug') }}"
                                   class="input-field rounded-l-none border-l-0"
                                   style="border-radius: 0 0.875rem 0.875rem 0;">
                        </div>
                        <p class="text-white/30 text-xs mt-2">Solo letras minúsculas, números y guiones. Se genera automáticamente.</p>
                    </div>

                    <div>
                        <label class="input-label">Tipo de negocio *</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach(config('tribio.business_categories') as $key => $cat)
                            <label class="cursor-pointer">
                                <input type="radio" name="store_category" value="{{ $key }}"
                                       {{ old('store_category') === $key ? 'checked' : '' }}
                                       class="hidden peer">
                                <div class="p-3 rounded-xl border border-white/10 text-center transition-all peer-checked:border-tribio-purple peer-checked:bg-tribio-purple/20 hover:border-white/25 hover:bg-white/5">
                                    <span class="text-2xl block mb-1">{{ $cat['icon'] }}</span>
                                    <span class="text-xs text-white/70 font-medium">{{ $cat['label'] }}</span>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="input-label">WhatsApp del negocio</label>
                        <input type="tel" name="whatsapp_phone"
                               placeholder="51900000000 (con código de país)"
                               value="{{ old('whatsapp_phone') }}"
                               class="input-field">
                        <p class="text-white/30 text-xs mt-1">Aquí llegarán tus pedidos. Ej: 51902699916</p>
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
                        <h2 class="text-xl font-bold text-white">🖼️ El logo de tu negocio</h2>
                        <p class="text-white/40 text-sm mt-1">Sube tu logo en formato PNG (fondo transparente recomendado). Puedes omitir este paso.</p>
                    </div>

                    <div class="flex flex-col items-center gap-6">
                        {{-- Preview --}}
                        <div class="w-32 h-32 rounded-2xl bg-white/5 border-2 border-dashed border-white/20 flex items-center justify-center overflow-hidden relative group cursor-pointer"
                             onclick="document.getElementById('logo_input').click()">
                            <img id="logo_preview" src="" alt="" class="w-full h-full object-cover hidden">
                            <div id="logo_placeholder" class="text-center">
                                <div class="text-4xl mb-2">🏪</div>
                                <p class="text-white/30 text-xs">Click para subir</p>
                            </div>
                            <div class="absolute inset-0 bg-tribio-purple/20 opacity-0 group-hover:opacity-100 transition-opacity rounded-2xl flex items-center justify-center">
                                <span class="text-white text-xs font-medium">Cambiar</span>
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
                            <p class="text-white/30 text-xs mt-3">PNG, JPG o WebP • Máx. 2MB</p>
                        </div>

                        {{-- Color del acento --}}
                        <div class="w-full">
                            <label class="input-label text-center block">Color principal de tu tienda</label>
                            <div class="flex items-center gap-4 justify-center mt-3">
                                <input type="color" name="accent_color" id="accent_color_picker" value="#8B5CF6"
                                       class="w-12 h-12 rounded-xl cursor-pointer border-0 bg-transparent"
                                       onchange="document.getElementById('accent_hex').value = this.value">
                                <input type="text" id="accent_hex" value="#8B5CF6"
                                       placeholder="#8B5CF6"
                                       class="input-field w-36 text-center font-mono"
                                       onchange="document.getElementById('accent_color_picker').value = this.value">
                            </div>
                            {{-- Paletas predefinidas --}}
                            <div class="flex gap-3 justify-center mt-4 flex-wrap">
                                @foreach(['#8B5CF6','#EC4899','#06B6D4','#10B981','#F59E0B','#EF4444','#6366F1','#14B8A6'] as $color)
                                <button type="button" onclick="setColor('{{ $color }}')"
                                        class="w-8 h-8 rounded-lg transition-transform hover:scale-110 border-2 border-transparent hover:border-white/40"
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
                        <h2 class="text-xl font-bold text-white">🎨 Elige el diseño de tu tienda</h2>
                        <p class="text-white/40 text-sm mt-1">Cada plantilla puede ser personalizada después. Puedes cambiarla cuando quieras.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
                        @foreach(config('tribio.templates') as $key => $template)
                        <label class="cursor-pointer">
                            <input type="radio" name="template_name" value="{{ $key }}"
                                   {{ old('template_name', 'elegant-dark') === $key ? 'checked' : '' }}
                                   class="hidden peer">
                            <div class="template-card peer-checked:selected border-white/10 hover:border-tribio-purple/30 transition-all">
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
                                    <div class="absolute top-2 right-2 hidden peer-checked:flex w-6 h-6 rounded-full bg-tribio-purple items-center justify-center text-white text-xs">✓</div>
                                </div>
                                <div class="p-4 {{ $key !== 'minimal-light' ? 'bg-tribio-card' : 'bg-gray-50' }}">
                                    <p class="{{ $key !== 'minimal-light' ? 'text-white' : 'text-gray-900' }} font-bold text-sm">{{ $template['name'] }}</p>
                                    <p class="{{ $key !== 'minimal-light' ? 'text-white/40' : 'text-gray-500' }} text-xs mt-1 line-clamp-2">{{ $template['description'] }}</p>
                                    <div class="flex gap-1 mt-3 flex-wrap">
                                        @foreach($template['tags'] as $tag)
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-tribio-purple/20 text-tribio-cyan border border-tribio-purple/20">{{ $tag }}</span>
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
        form: { name: '', email: '', store_name: '' },

        init() {
            // Restaurar errores al paso correcto si hay validación fallida
            @if($errors->any())
                this.currentStep = {{ old('_step', 1) }};
            @endif
        },

        nextStep(step) {
            if (step === 1) {
                if (!this.form.name || !this.form.email) {
                    alert('Por favor completa tu nombre y correo.');
                    return;
                }
            }
            if (step === 2) {
                if (!this.form.store_name) {
                    alert('Por favor ingresa el nombre de tu negocio.');
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
