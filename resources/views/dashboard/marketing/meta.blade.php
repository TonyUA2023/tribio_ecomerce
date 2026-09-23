@extends('layouts.dashboard')
@section('title', 'Marketing · Meta')
@section('page_title', '📣 Marketing')
@section('content')
@php
    $hasCustomDomain = $store->custom_domain && str_contains($store->custom_domain, '.');
    $tokenMask = $saved?->maskedToken();
@endphp
<div class="w-full max-w-5xl mx-auto space-y-5 sm:space-y-6">
    <form method="POST" action="{{ route('dashboard.marketing.meta.update') }}" class="space-y-5 sm:space-y-6" x-data="{ showToken: false, copied: false }">
        @csrf
        @method('PUT')

        {{-- Barra de acción: pegada arriba, no tapa el contenido al hacer scroll --}}
        <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
            <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
                <a href="{{ route('dashboard.marketing.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a Marketing" title="Volver a Marketing">←</a>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Marketing · Meta</p>
                    <p class="text-white font-bold text-sm truncate">Facebook e Instagram</p>
                </div>
                <button type="submit" class="btn-primary flex-shrink-0 !px-4 sm:!px-6 text-xs sm:text-sm" @disabled(!$available)>
                    <span class="hidden sm:inline">💾 Guardar cambios</span><span class="sm:hidden">💾 Guardar</span>
                </button>
            </div>
        </div>

        @unless($available)
        <div class="glass-card p-4 sm:p-5">
            <p class="text-white font-bold text-sm">⏳ Estamos activando este módulo</p>
            <p class="text-xs text-white/50 mt-1">Falta una actualización en el servidor de Tribio. Apenas esté lista podrás guardar esta configuración.</p>
        </div>
        @endunless

        @if($errors->any())
        <div class="glass-card p-4 sm:p-5 border border-red-400/20 bg-red-500/5">
            <p class="text-red-400 font-bold text-xs sm:text-sm mb-1.5">⚠️ Revisa estos campos antes de guardar:</p>
            <ul class="text-xs text-red-400/90 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Estado --}}
        <div class="glass-card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-white font-bold text-sm">Conexión con Meta</h3>
                    <p class="text-xs text-white/50 mt-1">Si la pausas, tu tienda deja de enviar datos a Meta y el catálogo deja de estar disponible. Nada se borra.</p>
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer flex-shrink-0">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="w-5 h-5 rounded" @checked(old('is_active', $integration->is_active))>
                    <span class="text-sm text-white font-semibold">Activa</span>
                </label>
            </div>
        </div>

        {{-- 1. Píxel --}}
        <div class="glass-card p-5 sm:p-6 space-y-4">
            <div>
                <h3 class="text-white font-bold text-sm">1. Tu Píxel de Meta</h3>
                <p class="text-xs text-white/50 mt-1">Mide quién visita tus productos y quién agrega al carrito, para que tus anuncios lleguen a personas con más ganas de comprar.</p>
            </div>
            <div>
                <label class="input-label" for="pixel_id">ID del Píxel</label>
                <input id="pixel_id" type="text" name="pixel_id" inputmode="numeric" autocomplete="off" value="{{ old('pixel_id', $integration->pixel_id) }}" class="input-field font-mono text-sm" placeholder="Ej.: 123456789012345">
                <p class="text-[11px] text-white/40 mt-1">Puedes pegar solo el número o el código completo del Píxel: nosotros sacamos el número.</p>
            </div>
            <details class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/60">
                <summary class="cursor-pointer text-white/80 font-semibold">¿Dónde lo encuentro?</summary>
                <ol class="list-decimal list-inside space-y-1 mt-2">
                    <li>Entra a <a href="https://business.facebook.com/events_manager2" target="_blank" rel="noopener" class="text-tribio-cyan underline">Administrador de eventos de Meta ↗</a>.</li>
                    <li>Si no tienes un Píxel, pulsa <strong>Conectar orígenes de datos → Web</strong> y créalo.</li>
                    <li>Elige tu Píxel y copia el número que aparece como <strong>Identificador</strong>.</li>
                </ol>
            </details>
            <p class="text-[11px] text-white/40">🍪 Tu tienda mostrará un aviso de cookies a tus compradores. El Píxel solo se activa para quienes lo aceptan, como pide la ley de protección de datos.</p>
        </div>

        {{-- 2. API de conversiones --}}
        <div class="glass-card p-5 sm:p-6 space-y-4">
            <div>
                <h3 class="text-white font-bold text-sm">2. API de conversiones <span class="badge badge-blue align-middle">Recomendado</span></h3>
                <p class="text-xs text-white/50 mt-1">Envía tus ventas a Meta directamente desde Tribio, aunque el navegador del comprador bloquee el Píxel. Así Meta aprende qué anuncios venden de verdad.</p>
            </div>
            <div>
                <label class="input-label" for="capi_token">Token de acceso</label>
                <div class="relative">
                    <input id="capi_token" :type="showToken ? 'text' : 'password'" name="capi_token" autocomplete="off" class="input-field font-mono text-xs pr-11"
                           placeholder="{{ $tokenMask ? 'Déjalo vacío para mantener el token guardado' : 'Pega aquí el token que genera Meta' }}">
                    <button type="button" @click="showToken = !showToken" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 text-sm" :aria-label="showToken ? 'Ocultar token' : 'Mostrar token'">
                        <span x-show="!showToken">👁️</span><span x-show="showToken" x-cloak>🙈</span>
                    </button>
                </div>
                @if($tokenMask)
                    <div class="flex items-center justify-between gap-2 flex-wrap mt-2">
                        <span class="badge badge-green">Token guardado · <span class="font-mono">{{ $tokenMask }}</span></span>
                        <label class="inline-flex items-center gap-2 text-xs text-white/60 cursor-pointer">
                            <input type="checkbox" name="remove_capi_token" value="1" class="rounded"> Quitar token
                        </label>
                    </div>
                @endif
                <p class="text-[11px] text-white/40 mt-1">Es secreto: no lo compartas. Tribio lo guarda cifrado y nunca lo vuelve a mostrar.</p>
            </div>
            <div>
                <label class="input-label" for="test_event_code">Código de prueba <span class="text-white/40 normal-case font-normal">(opcional)</span></label>
                <input id="test_event_code" type="text" name="test_event_code" autocomplete="off" value="{{ old('test_event_code', $integration->test_event_code) }}" class="input-field font-mono text-sm" placeholder="Ej.: TEST12345">
                <p class="text-[11px] text-white/40 mt-1">Solo sirve para el botón "Probar la conexión". No afecta tus anuncios.</p>
            </div>
            <details class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/60">
                <summary class="cursor-pointer text-white/80 font-semibold">¿Dónde los encuentro?</summary>
                <ol class="list-decimal list-inside space-y-1 mt-2">
                    <li>En el Administrador de eventos, elige tu Píxel y entra a <strong>Configuración</strong>.</li>
                    <li>En <strong>API de conversiones</strong>, pulsa <strong>Generar token de acceso</strong> y cópialo.</li>
                    <li>El código de prueba está en la pestaña <strong>Probar eventos</strong>.</li>
                </ol>
            </details>
        </div>

        {{-- 3. Catálogo --}}
        <div class="glass-card p-5 sm:p-6 space-y-4">
            <div>
                <h3 class="text-white font-bold text-sm">3. Catálogo de productos</h3>
                <p class="text-xs text-white/50 mt-1">Meta lee esta dirección cada hora: si cambias un precio, una foto o el stock en Tribio, tus anuncios se actualizan solos.</p>
            </div>
            <div>
                <label class="input-label" for="feed_url">Dirección de tu catálogo</label>
                <div class="flex gap-2">
                    <input id="feed_url" type="text" readonly value="{{ $feedUrl }}" class="input-field font-mono text-xs flex-1 min-w-0" @focus="$event.target.select()">
                    <button type="button" class="btn-secondary flex-shrink-0 text-xs"
                            @click="navigator.clipboard.writeText(@js($feedUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="!copied">Copiar</span><span x-show="copied" x-cloak>¡Copiado!</span>
                    </button>
                </div>
                @unless($saved)
                    <p class="text-[11px] text-white/40 mt-1">Esta dirección empieza a funcionar cuando guardas esta página.</p>
                @endunless
            </div>
            <div>
                <label class="input-label" for="default_condition">Condición de tus productos</label>
                <select id="default_condition" name="default_condition" class="input-field">
                    @foreach(\App\Models\StoreMarketingIntegration::CONDITIONS as $value => $label)
                        <option value="{{ $value }}" @selected(old('default_condition', $integration->default_condition) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-white/40 mt-1">Meta lo exige. Puedes cambiarlo en un producto puntual desde su ficha (sección Publicidad).</p>
            </div>
            <details class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/60">
                <summary class="cursor-pointer text-white/80 font-semibold">Cómo conectarlo en Meta</summary>
                <ol class="list-decimal list-inside space-y-1 mt-2">
                    <li>Entra a <a href="https://business.facebook.com/commerce" target="_blank" rel="noopener" class="text-tribio-cyan underline">Commerce Manager ↗</a> y crea un catálogo de tipo <strong>Comercio electrónico</strong>.</li>
                    <li>Ve a <strong>Catálogo → Orígenes de datos → Agregar artículos → Feed de datos</strong>.</li>
                    <li>Elige <strong>Usar una URL</strong>, pega la dirección de arriba y programa la actualización <strong>cada hora</strong>.</li>
                    <li>Moneda: <strong>PEN (soles)</strong>. Luego vincula el catálogo con tu Píxel en la configuración del catálogo.</li>
                </ol>
            </details>

            @if($catalog)
            <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                <p class="text-sm text-white font-semibold">
                    {{ $catalog['products_included'] }} de {{ $catalog['products_total'] }} productos van a Meta
                    @if($catalog['items'] !== $catalog['products_included'])<span class="text-white/40 font-normal">({{ $catalog['items'] }} artículos contando variantes)</span>@endif
                </p>
                @if(count($catalog['excluded']))
                    <p class="text-[11px] text-white/40 mt-1">Estos no aparecen en tus anuncios:</p>
                    <ul class="mt-2 divide-y divide-white/5">
                        @foreach(array_slice($catalog['excluded'], 0, 25) as $row)
                            <li class="py-2 flex items-center justify-between gap-3">
                                <span class="min-w-0">
                                    <span class="block text-xs text-white truncate">{{ $row['product']->name }}</span>
                                    <span class="block text-[11px] text-white/40">{{ $row['reason'] }}</span>
                                </span>
                                <a href="{{ route('dashboard.productos.edit', $row['product']) }}" class="text-[11px] text-tribio-cyan underline flex-shrink-0">Editar</a>
                            </li>
                        @endforeach
                    </ul>
                    @if(count($catalog['excluded']) > 25)
                        <p class="text-[11px] text-white/40 mt-1">y {{ count($catalog['excluded']) - 25 }} más.</p>
                    @endif
                @endif
            </div>
            @endif
        </div>

        {{-- 4. Verificación de dominio (solo con dominio propio) --}}
        @if($hasCustomDomain)
        <div class="glass-card p-5 sm:p-6 space-y-4">
            <div>
                <h3 class="text-white font-bold text-sm">4. Verificar tu dominio <span class="text-white/40 font-normal">(opcional)</span></h3>
                <p class="text-xs text-white/50 mt-1">Demuestra a Meta que <strong class="text-white/70">{{ $store->custom_domain }}</strong> es tuyo. Te da control total sobre los enlaces de tus anuncios.</p>
            </div>
            <div>
                <label class="input-label" for="domain_verification">Código de verificación</label>
                <input id="domain_verification" type="text" name="domain_verification" autocomplete="off" value="{{ old('domain_verification', $integration->domain_verification) }}" class="input-field font-mono text-xs" placeholder='Pega la etiqueta &lt;meta name="facebook-domain-verification" …&gt;'>
                <p class="text-[11px] text-white/40 mt-1">En Meta: Configuración del negocio → Seguridad de la marca → Dominios → Agregar → "Etiqueta meta". Pega la etiqueta aquí, guarda y luego pulsa "Verificar" en Meta.</p>
            </div>
        </div>
        @endif
    </form>

    {{-- Probar la conexión: formulario aparte (nunca dentro del de arriba) --}}
    @if($saved)
    <div class="glass-card p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                <h3 class="text-white font-bold text-sm">Probar la conexión</h3>
                <p class="text-xs text-white/50 mt-1">Envía un evento de prueba a Meta. Si todo está bien, lo verás en el Administrador de eventos → Probar eventos.</p>
            </div>
            <form method="POST" action="{{ route('dashboard.marketing.meta.test-event') }}">
                @csrf
                <button type="submit" class="btn-secondary text-xs sm:text-sm" @disabled(!$saved->hasConversionsApi() || !$saved->test_event_code)>Enviar evento de prueba</button>
            </form>
        </div>
        @if(!$saved->hasConversionsApi() || !$saved->test_event_code)
            <p class="text-[11px] text-white/40 mt-2">Para probar, guarda tu ID de Píxel, tu token y el código de prueba.</p>
        @endif
        @if($saved->last_error && $saved->last_error_at)
            <p class="text-xs text-red-300 mt-3" role="alert">Último error ({{ $saved->last_error_at->locale('es')->diffForHumans() }}): {{ $saved->last_error }}</p>
        @endif
    </div>
    @endif

    {{-- Actividad reciente --}}
    @if($recentEvents->isNotEmpty())
    <div class="glass-card p-5 sm:p-6">
        <h3 class="text-white font-bold text-sm">Actividad reciente</h3>
        <p class="text-xs text-white/50 mt-1">Ventas y pruebas que tu tienda envió a Meta.</p>
        <ul class="mt-3 divide-y divide-white/5">
            @foreach($recentEvents as $event)
                <li class="py-2.5 flex items-start justify-between gap-3">
                    <span class="min-w-0">
                        <span class="block text-xs text-white">
                            {{ $event->is_test ? 'Evento de prueba' : ($event->event_name === 'Purchase' ? 'Compra' : $event->event_name) }}
                            @if($event->order)<span class="text-white/40">· Pedido #{{ $event->order->order_number }}</span>@endif
                        </span>
                        <span class="block text-[11px] text-white/40">{{ $event->created_at->locale('es')->translatedFormat('d M Y · H:i') }}</span>
                        @if($event->error && $event->status !== \App\Models\MarketingEventLog::STATUS_SENT)
                            <span class="block text-[11px] text-white/50 mt-0.5">{{ $event->error }}</span>
                        @endif
                    </span>
                    <span class="badge {{ $event->status_badge }} flex-shrink-0">{{ $event->status_label }}</span>
                </li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Cómo crear tu primer anuncio --}}
    <details class="glass-card p-5 sm:p-6 text-xs text-white/60">
        <summary class="cursor-pointer text-white font-bold text-sm">Cómo crear tu primer anuncio con tu catálogo</summary>
        <ol class="list-decimal list-inside space-y-1.5 mt-3">
            <li>Entra a <a href="https://adsmanager.facebook.com" target="_blank" rel="noopener" class="text-tribio-cyan underline">Administrador de anuncios ↗</a> y pulsa <strong>Crear</strong>.</li>
            <li>Elige el objetivo <strong>Ventas</strong> y activa <strong>Anuncios del catálogo Advantage+</strong>.</li>
            <li>Selecciona el catálogo que conectaste en el paso 3 y tu Píxel como origen de las compras.</li>
            <li>Define tu presupuesto diario, el país (Perú) y publica. Meta mostrará a cada persona los productos que más le interesan.</li>
        </ol>
        <p class="mt-3 text-white/40">Las ventas que lleguen desde tus anuncios aparecerán en Marketing → Resumen.</p>
    </details>
</div>
@endsection
