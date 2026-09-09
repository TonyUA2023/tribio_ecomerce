@extends('layouts.dashboard')
@section('title','Mi Tienda') @section('page_title','⚙️ Configurar Mi Tienda')
@section('content')
<div class="w-full max-w-7xl mx-auto space-y-6">
    <form method="POST" action="{{ route('dashboard.store.update') }}" class="grid grid-cols-1 lg:grid-cols-2 gap-6">@csrf
        
        <!-- Columna Izquierda: Info Básica y Contacto -->
        <div class="glass-card p-8 space-y-6">
            <div>
                <h3 class="text-white font-bold text-lg mb-1 flex items-center gap-2">📝 Información Básica</h3>
                <p class="text-xs text-white/50 mb-4">Los detalles principales de tu negocio.</p>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="input-label">Nombre del negocio *</label>
                            <input type="text" name="name" class="input-field" value="{{ old('name', $store?->name) }}" required>
                        </div>
                        <div>
                            <label class="input-label">Enlace de la tienda (Slug)</label>
                            <div class="flex items-center">
                                <span class="px-3 py-2 bg-black/20 border border-white/5 border-r-0 rounded-l-lg text-xs text-white/50">tienda/</span>
                                <input type="text" name="slug" class="input-field rounded-l-none" value="{{ old('slug', $store?->slug) }}" placeholder="mi-tienda">
                            </div>
                        </div>
                    </div>
                    
                    @if($store)
                        <p class="text-[11px] text-white/50 mt-1 flex items-center gap-1">
                            🔗 Enlace público: <a href="{{ $store->url }}" target="_blank" class="text-tribio-cyan hover:underline">{{ $store->url }}</a>
                        </p>
                    @endif
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="input-label">Categoría / Rubro *</label>
                            <select name="category" class="input-field" required>
                                <option value="" disabled>Selecciona una categoría</option>
                                @foreach(['moda' => 'Moda y Ropa', 'calzado' => 'Calzado', 'tecnologia' => 'Tecnología', 'alimentos' => 'Alimentos y Bebidas', 'joyeria' => 'Joyería y Accesorios', 'hogar' => 'Hogar y Decoración', 'deporte' => 'Deportes', 'salud' => 'Salud y Belleza', 'servicios' => 'Servicios', 'otros' => 'Otros'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('category', $store?->category) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label class="input-label">Eslogan / Tagline</label><input type="text" name="tagline" class="input-field" value="{{ old('tagline', $store?->tagline) }}" placeholder="Ej: El sabor que te enamora"></div>
                    </div>
                    
                    <div>
                        <label class="input-label">Modo de Construcción de Sitio *</label>
                        <select name="build_mode" class="input-field" required>
                            <option value="builder" {{ old('build_mode', $store?->build_mode) == 'builder' ? 'selected' : '' }}>Constructor Visual Tribio</option>
                            <option value="custom_code" {{ old('build_mode', $store?->build_mode) == 'custom_code' ? 'selected' : '' }}>Código a Medida (Desarrollador)</option>
                        </select>
                        <p class="text-[11px] text-white/40 mt-1">Si seleccionas "Código a Medida", se ignorará el constructor visual y se buscará una plantilla en <code>resources/views/clientes_custom/{{ $store?->slug ?? 'slug' }}/index.blade.php</code>.</p>
                    </div>

                    <div><label class="input-label">Descripción</label><textarea name="description" class="input-field" rows="3" placeholder="Breve descripción de tu negocio...">{{ old('description', $store?->description) }}</textarea></div>
                </div>
            </div>

            <div class="border-t border-white/10 pt-6">
                <h3 class="text-white font-bold text-lg mb-1 flex items-center gap-2">📱 Contacto y Redes</h3>
                <p class="text-xs text-white/50 mb-4">Dónde pueden encontrarte tus clientes.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                    <div><label class="input-label">Correo Electrónico de Contacto</label><input type="email" name="contact_email" class="input-field" value="{{ old('contact_email', $store?->contact_email) }}" placeholder="hola@tienda.com"></div>
                    <div><label class="input-label">Teléfono Fijo / Móvil Secundario</label><input type="text" name="contact_phone" class="input-field" value="{{ old('contact_phone', $store?->contact_phone) }}" placeholder="+51 01 2345678"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div><label class="input-label">WhatsApp (con código país)</label><input type="tel" name="whatsapp_phone" class="input-field" value="{{ old('whatsapp_phone', $store?->whatsapp_phone) }}" placeholder="51900000000"></div>
                    <div><label class="input-label">Ciudad</label><input type="text" name="city" class="input-field" value="{{ old('city', $store?->city) }}" placeholder="Lima"></div>
                    <div><label class="input-label">Facebook</label><input type="url" name="facebook_url" class="input-field" value="{{ old('facebook_url', $store?->facebook_url) }}" placeholder="https://facebook.com/..."></div>
                    <div><label class="input-label">Instagram</label><input type="url" name="instagram_url" class="input-field" value="{{ old('instagram_url', $store?->instagram_url) }}" placeholder="https://instagram.com/..."></div>
                </div>
            </div>

            @if($errors->any())
                <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm mt-4 space-y-1">
                    @foreach($errors->all() as $error_msg)
                        <p>• {{ $error_msg }}</p>
                    @endforeach
                </div>
            @endif
            
            <div class="pt-4 border-t border-white/10 mt-6">
                <button type="submit" class="btn-primary btn-primary-lg w-full justify-center">Guardar Todos los Cambios</button>
            </div>
        </div>

        <!-- Columna Derecha: Configuraciones Avanzadas -->
        <div class="space-y-6">
            <!-- Dominio Personalizado -->
            <div class="glass-card p-6">
                <h3 class="text-white font-bold text-sm mb-2">🌐 Dominio Personalizado</h3>
                <p class="text-xs text-white/50 mb-4">Configura tu propio dominio de internet para mostrar tu tienda de forma profesional e independiente.</p>
                
                <div class="space-y-4">
                    <div>
                        <label class="input-label">Tu Dominio Propio</label>
                        <input type="text" name="custom_domain" class="input-field" value="{{ old('custom_domain', $store?->custom_domain) }}" placeholder="ejemplo: mitienda.com">
                        <p class="text-[11px] text-white/40 mt-1">Ingresa el dominio limpio (ej. <code>mitienda.com</code> o <code>tienda.miweb.com</code>). Deja en blanco para usar la URL estándar.</p>
                    </div>

                    @if($store?->custom_domain)
                    <div class="p-4 rounded-xl border border-white/5 bg-white/3 space-y-2 text-xs">
                        <p class="text-white font-bold">⚙️ Configuración de DNS:</p>
                        <p class="text-white/60">Apunta tu dominio propio a la plataforma configurando estos registros en tu proveedor de dominios (GoDaddy, Namecheap, etc.):</p>
                        <div class="grid grid-cols-1 gap-2 mt-2 font-mono bg-black/30 p-3 rounded-lg border border-white/5">
                            <div>
                                <span class="text-tribio-purple font-bold">Tipo:</span> A | 
                                <span class="text-tribio-purple font-bold">Nombre:</span> @ | 
                                <span class="text-tribio-purple font-bold">Valor:</span> <code>{{ request()->server('SERVER_ADDR') && !in_array(request()->server('SERVER_ADDR'), ['127.0.0.1', '::1']) ? request()->server('SERVER_ADDR') : 'IP_DE_TU_SERVIDOR' }}</code>
                            </div>
                            <div class="border-t border-white/5 pt-2 mt-1">
                                <span class="text-tribio-purple font-bold">Tipo:</span> CNAME | 
                                <span class="text-tribio-purple font-bold">Nombre:</span> www | 
                                <span class="text-tribio-purple font-bold">Valor:</span> <code>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code>
                            </div>
                        </div>
                        <p class="text-[10px] text-white/40">Nota: Los cambios en el proveedor de dominio pueden tardar hasta 24-48 horas en propagarse.</p>
                    </div>
                    @endif
                </div>
            </div>
            <!-- Envíos Express -->
            <div class="glass-card p-6" x-data="{
                expressEnabled: {{ old('is_express_shipping_enabled', $store?->is_express_shipping_enabled) ? 'true' : 'false' }}
            }">
                <h3 class="text-white font-bold text-sm mb-2">🚀 Envíos Express</h3>
                <p class="text-xs text-white/50 mb-4">Habilita esta opción si tienes stock local para hacer envíos instantáneos.</p>
                
                <div class="space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" name="is_express_shipping_enabled" class="sr-only" x-model="expressEnabled" value="1">
                            <div class="block bg-white/10 w-10 h-6 rounded-full transition-colors" :class="{'bg-tribio-cyan': expressEnabled}"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="{'translate-x-4': expressEnabled}"></div>
                        </div>
                        <span class="text-sm font-medium text-white">Habilitar Opción de Envío Express</span>
                    </label>

                    <div x-show="expressEnabled" class="bg-white/3 border border-white/5 p-4 rounded-xl mt-3" style="display: none;">
                        <label class="input-label">Costo del Envío Express (S/.)</label>
                        <input type="number" step="0.01" min="0" name="express_shipping_cost" class="input-field" value="{{ old('express_shipping_cost', $store?->express_shipping_cost ?? '0.00') }}" placeholder="0.00">
                        <p class="text-[10px] text-white/40 mt-1">Si es gratis, déjalo en 0. Este monto se sumará al subtotal del pedido si el cliente lo elige.</p>
                    </div>
                </div>
            </div>

            <!-- Multi Idioma -->
            <div class="glass-card p-6" x-data="{
                langEnabled: {{ old('is_multilanguage_enabled', $store?->is_multilanguage_enabled) ? 'true' : 'false' }}
            }">
                <h3 class="text-white font-bold text-sm mb-2">🌍 Multi Idioma Dinámico</h3>
                <p class="text-xs text-white/50 mb-4">Habilita el selector de idiomas dinámico (Español / Inglés) en el menú principal de tu tienda.</p>
                
                <div class="space-y-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" name="is_multilanguage_enabled" class="sr-only" x-model="langEnabled" value="1">
                            <div class="block bg-white/10 w-10 h-6 rounded-full transition-colors" :class="{'bg-tribio-cyan': langEnabled}"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="{'translate-x-4': langEnabled}"></div>
                        </div>
                        <span class="text-sm font-medium text-white">Activar Multi Idioma (ES / EN)</span>
                    </label>
                </div>
            </div>

            <!-- Textos del Banner Principal (Hero) -->
            <div class="glass-card p-6">
                <h3 class="text-white font-bold text-sm mb-2">📢 Textos del Banner Principal (Hero)</h3>
                <p class="text-xs text-white/50 mb-4">Configura los mensajes y títulos que aparecen en la primera pantalla de tu tienda.</p>
                
                <div class="space-y-4">
                    <div>
                        <label class="input-label">Badge Promocional superior</label>
                        <input type="text" name="hero_badge" class="input-field" value="{{ old('hero_badge', $store?->hero_badge) }}" placeholder="Ej: ENVÍO EXPRESS">
                        <p class="text-[10px] text-white/40 mt-1">Texto pequeño resaltado arriba del título.</p>
                    </div>
                    <div>
                        <label class="input-label">Título Principal</label>
                        <input type="text" name="hero_title" class="input-field" value="{{ old('hero_title', $store?->hero_title) }}" placeholder="Ej: Envíos rápidos a todo el Perú">
                    </div>
                    <div>
                        <label class="input-label">Subtítulo secundario</label>
                        <textarea name="hero_subtitle" class="input-field" rows="2" placeholder="Ej: Compra hoy y recoge a partir de 60 min.">{{ old('hero_subtitle', $store?->hero_subtitle) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Pasarela de Pagos / Checkout Mode -->
            <div class="glass-card p-6" x-data="{
                checkoutMode: '{{ old('checkout_mode', $store?->checkout_mode ?? 'whatsapp') }}',
                gateway: '{{ old('payment_gateway', $store?->payment_gateway ?? '') }}'
            }">
                <h3 class="text-white font-bold text-sm mb-2">💳 Pasarela de Pagos & Métodos de Venta</h3>
                <p class="text-xs text-white/50 mb-4">Elige cómo concretar tus pedidos y configura tus credenciales para recibir pagos con tarjeta de débito/crédito.</p>
                
                <div class="space-y-4">
                    {{-- Método de Checkout --}}
                    <div>
                        <label class="input-label">Modo de Venta / Checkout</label>
                        <select name="checkout_mode" x-model="checkoutMode" class="input-field">
                            <option value="whatsapp">Vender por WhatsApp (Redirección Directa)</option>
                            <option value="card">Vender por Tarjeta (Pasarela de Pago)</option>
                        </select>
                    </div>

                    {{-- Opciones de Pasarela (Solo visibles si el checkout_mode es card) --}}
                    <div x-show="checkoutMode === 'card'" class="space-y-4 bg-white/3 border border-white/5 p-4 rounded-xl" style="display: none;">
                        <div>
                            <label class="input-label">Pasarela de Pago Habilitada</label>
                            <select name="payment_gateway" x-model="gateway" class="input-field">
                                <option value="">Selecciona una pasarela...</option>
                                <option value="culqi">Culqi</option>
                                <option value="mercado_pago">Mercado Pago</option>
                            </select>
                        </div>

                        {{-- Campos para Culqi --}}
                        <div x-show="gateway === 'culqi'" class="space-y-4" style="display: none;">
                            <p class="text-[10px] text-tribio-cyan font-bold uppercase tracking-wider">Configuración de Culqi (Perú)</p>
                            <div>
                                <label class="input-label">Llave Pública (Public Key) *</label>
                                <input type="text" name="gateway_public_key" value="{{ old('gateway_public_key', $store?->gateway_public_key) }}" class="input-field" placeholder="pk_live_...">
                            </div>
                            <div>
                                <label class="input-label">Llave Privada (Private Key) *</label>
                                <input type="text" name="gateway_private_key" value="{{ old('gateway_private_key', $store?->gateway_private_key) }}" class="input-field" placeholder="sk_live_...">
                            </div>
                        </div>

                        {{-- Campos para Mercado Pago --}}
                        <div x-show="gateway === 'mercado_pago'" class="space-y-4" style="display: none;">
                            <p class="text-[10px] text-tribio-cyan font-bold uppercase tracking-wider">Configuración de Mercado Pago</p>
                            <div>
                                <label class="input-label">Token de Acceso (Access Token) *</label>
                                <input type="text" name="mp_access_token" value="{{ old('mp_access_token', $store?->mp_access_token ?? $store?->gateway_access_token) }}" class="input-field" placeholder="APP_USR-...">
                            </div>
                            <div>
                                <label class="input-label">Llave Pública (Public Key) *</label>
                                <input type="text" name="mp_public_key" value="{{ old('mp_public_key', $store?->mp_public_key ?? $store?->gateway_public_key) }}" class="input-field" placeholder="APP_USR-...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distributors (Distribuidores) Section -->
            <div class="glass-card p-6" x-data="{
                regions: {{ json_encode(old('distributors', $store->distributors ?? [])) }} || [],
                addRegion() {
                    this.regions.push({ region: '', locations: [''] });
                },
                removeRegion(index) {
                    this.regions.splice(index, 1);
                },
                addLocation(regionIndex) {
                    this.regions[regionIndex].locations.push('');
                },
                removeLocation(regionIndex, locIndex) {
                    this.regions[regionIndex].locations.splice(locIndex, 1);
                    if (this.regions[regionIndex].locations.length === 0) {
                        this.regions[regionIndex].locations.push('');
                    }
                }
            }">
                <h3 class="text-white font-bold text-sm mb-4">🌍 Red de Distribuidores Globales</h3>
                <p class="text-xs text-white/50 mb-4">Agrega las regiones y ubicaciones de tus distribuidores para que aparezcan en tu página de inicio.</p>
                
                <div class="space-y-4">
                    <template x-for="(reg, rIdx) in regions" :key="rIdx">
                        <div class="p-4 rounded-xl border border-white/5 bg-white/3 space-y-3 relative">
                            <button type="button" @click="removeRegion(rIdx)" class="absolute top-4 right-4 text-xs text-red-400 hover:underline">Eliminar Región</button>
                            
                            <div>
                                <label class="input-label">Región (Ej: AMÉRICA DEL SUR)</label>
                                <input type="text" :name="'distributors[' + rIdx + '][region]'" x-model="reg.region" class="input-field" required placeholder="Región o Continente">
                            </div>
                            
                            <div class="space-y-2">
                                <label class="input-label">Ciudades / Sucursales</label>
                                <template x-for="(loc, lIdx) in reg.locations" :key="lIdx">
                                    <div class="flex gap-2">
                                        <input type="text" :name="'distributors[' + rIdx + '][locations][' + lIdx + ']'" x-model="reg.locations[lIdx]" class="input-field py-1.5" required placeholder="Ej: Perú (Chiclayo - Oficina Central B2B)">
                                        <button type="button" @click="removeLocation(rIdx, lIdx)" class="px-3 text-red-400 hover:text-red-300 font-bold">✕</button>
                                    </div>
                                </template>
                                <button type="button" @click="addLocation(rIdx)" class="text-[11px] text-[#8B5CF6] hover:underline font-bold mt-1">+ Agregar Ciudad/Sucursal</button>
                            </div>
                        </div>
                    </template>
                </div>
                
                <button type="button" @click="addRegion()" class="mt-4 px-4 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-xs font-bold transition-colors w-full border border-dashed border-white/20">
                    + Agregar Nueva Región
                </button>
            </div>
        </div>
    </form>

    {{-- Logo upload --}}
    <div class="glass-card p-8">
        <h3 class="text-white font-bold text-lg mb-1 flex items-center gap-2">🖼️ Logo y Portada</h3>
        <p class="text-xs text-white/50 mb-6">Personaliza la apariencia visual de tu tienda.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <form method="POST" action="{{ route('dashboard.store.logo') }}" enctype="multipart/form-data" class="space-y-4 bg-white/3 p-6 rounded-xl border border-white/5">@csrf
                <div class="flex items-center gap-4">
                    <div class="w-20 h-20 rounded-xl bg-black/30 border border-white/10 flex items-center justify-center overflow-hidden shrink-0">
                        @if($store?->logo_path)
                            <img src="{{ $store->logo_url }}" class="w-full h-full object-contain">
                        @else
                            <svg class="w-8 h-8 text-white/20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        @endif
                    </div>
                    <div class="flex-1">
                        <label class="input-label">Logo (PNG recomendado)</label>
                        <input type="file" name="logo" accept="image/*" class="input-field py-2 text-xs">
                    </div>
                </div>
                <button type="submit" class="btn-secondary w-full justify-center">Subir logo</button>
            </form>
            <form method="POST" action="{{ route('dashboard.store.cover') }}" enctype="multipart/form-data" class="space-y-4 bg-white/3 p-6 rounded-xl border border-white/5">@csrf
                <div class="space-y-3">
                    @if($store?->cover_path)
                        <img src="{{ $store->cover_url }}" class="h-24 w-full object-cover rounded-xl border border-white/10">
                    @endif
                    <label class="input-label">Imagen de portada</label>
                    <input type="file" name="cover" accept="image/*" class="input-field py-2 text-xs">
                </div>
                <button type="submit" class="btn-secondary w-full justify-center">Subir portada</button>
            </form>
        </div>
    </div>
</div>
@endsection
