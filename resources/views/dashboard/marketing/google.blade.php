@extends('layouts.dashboard')
@section('title', 'Marketing · Google')
@section('page_title', '📣 Marketing')
@section('content')
<div class="w-full max-w-5xl mx-auto space-y-5 sm:space-y-6">
    <form method="POST" action="{{ route('dashboard.marketing.google.update') }}" class="space-y-5 sm:space-y-6"
          x-data="{ copied: false, mode: @js(old('shopping_mode', $integration->shopping_mode ?? 'off')) }">
        @csrf
        @method('PUT')

        {{-- Barra de acción: pegada arriba, no tapa el contenido al hacer scroll --}}
        <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
            <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
                <a href="{{ route('dashboard.marketing.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver a Marketing" title="Volver a Marketing">←</a>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Marketing · Google</p>
                    <p class="text-white font-bold text-sm truncate">Google Shopping, Analytics y Ads</p>
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
                    <h3 class="text-white font-bold text-sm">Conexión con Google</h3>
                    <p class="text-xs text-white/50 mt-1">Si la pausas, tu catálogo deja de estar disponible para Google y tu tienda deja de medir visitas y ventas. Nada se borra.</p>
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer flex-shrink-0">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="w-5 h-5 rounded" @checked(old('is_active', $integration->is_active))>
                    <span class="text-sm text-white font-semibold">Activa</span>
                </label>
            </div>
        </div>

        {{-- 1. Google Shopping --}}
        <div class="glass-card p-5 sm:p-6 space-y-4">
            <div>
                <h3 class="text-white font-bold text-sm">1. Google Shopping <span class="badge badge-green align-middle">Gratis</span></h3>
                <p class="text-xs text-white/50 mt-1">Tus productos aparecen en la pestaña Shopping, en Imágenes y en los resultados de Google, con foto y precio, sin pagar anuncios. Google recibe tu catálogo todos los días: si cambias un precio o el stock en Tribio, se actualiza solo.</p>
            </div>

            @php($chipOn = 'bg-tribio-cyan/10 border-tribio-cyan/40')
            <fieldset class="space-y-2">
                <legend class="input-label">¿Cómo quieres aparecer en Google Shopping?</legend>
                <label class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3 cursor-pointer" :class="mode === 'tribio' && @js($chipOn)">
                    <input type="radio" name="shopping_mode" value="tribio" x-model="mode" class="mt-1 flex-shrink-0">
                    <span class="min-w-0">
                        <span class="text-sm text-white font-semibold">Tribio publica mis productos</span> <span class="badge badge-blue align-middle">Recomendado</span>
                        <span class="block text-xs text-white/50 mt-0.5">Automático: no creas cuentas en Google ni necesitas dominio propio. Tus productos llevan a tu tienda en Tribio.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3 {{ $hasCustomDomain ? 'cursor-pointer' : 'opacity-60' }}" :class="mode === 'own' && @js($chipOn)">
                    <input type="radio" name="shopping_mode" value="own" x-model="mode" class="mt-1 flex-shrink-0" @disabled(!$hasCustomDomain)>
                    <span class="min-w-0">
                        <span class="text-sm text-white font-semibold">Uso mi propia cuenta de Google Merchant Center</span>
                        <span class="block text-xs text-white/50 mt-0.5">
                            @if($hasCustomDomain) Para manejar tú mismo tu cuenta y tus anuncios de Shopping con tu dominio <strong class="text-white/70">{{ $store->custom_domain }}</strong>.
                            @else Necesita un dominio propio. Puedes conectarlo en <a href="{{ route('dashboard.store.edit') }}" class="text-tribio-cyan underline">Mi tienda</a> cuando lo tengas.
                            @endif
                        </span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3 cursor-pointer" :class="mode === 'off' && @js($chipOn)">
                    <input type="radio" name="shopping_mode" value="off" x-model="mode" class="mt-1 flex-shrink-0">
                    <span class="text-sm text-white font-semibold">No mostrar mis productos en Google</span>
                </label>
            </fieldset>

            {{-- Tribio publica: no hay nada que configurar --}}
            <div x-show="mode === 'tribio'" x-cloak class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/60">
                @if($marketplaceEnabled)
                    <p class="text-white/80 font-semibold">✅ Todo listo, no tienes que hacer nada más</p>
                    <p class="mt-1">Tribio envía tus productos a Google todos los días. Google los revisa antes de mostrarlos (puede tardar unos días).</p>
                @else
                    <p class="text-white/80 font-semibold">⏳ Tribio está terminando su alta en Google</p>
                    <p class="mt-1">Deja esta opción elegida y guarda: tus productos empezarán a aparecer apenas esté lista, sin que hagas nada.</p>
                @endif
                <p class="mt-1">Si más adelante compras tu dominio, no tienes que cambiar nada aquí.</p>
            </div>

            {{-- Cuenta propia: verificación + dirección del catálogo --}}
            <div x-show="mode === 'own'" x-cloak class="space-y-4">
            @if($hasCustomDomain)
            <div>
                <label class="input-label" for="domain_verification">Código de verificación de Google</label>
                <input id="domain_verification" type="text" name="domain_verification" autocomplete="off" value="{{ old('domain_verification', $integration->domain_verification) }}" class="input-field font-mono text-xs" placeholder='Pega la etiqueta &lt;meta name="google-site-verification" …&gt;'>
                <p class="text-[11px] text-white/40 mt-1">Demuestra a Google que <strong class="text-white/60">{{ $store->custom_domain }}</strong> es tuyo. Pega la etiqueta completa: nosotros sacamos el código.</p>
            </div>
            @endif

            <div>
                <label class="input-label" for="feed_url">Dirección de tu catálogo</label>
                <div class="flex gap-2">
                    <input id="feed_url" type="text" readonly value="{{ $feedUrl }}" class="input-field font-mono text-xs flex-1 min-w-0" @focus="$event.target.select()">
                    <button type="button" class="btn-secondary flex-shrink-0 text-xs"
                            @click="navigator.clipboard.writeText(@js($feedUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="!copied">Copiar</span><span x-show="copied" x-cloak>¡Copiado!</span>
                    </button>
                </div>
                @unless($saved?->usesOwnMerchantCenter())
                    <p class="text-[11px] text-white/40 mt-1">Esta dirección empieza a funcionar cuando guardas esta página con esta opción elegida.</p>
                @endunless
            </div>

            <details class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/60">
                <summary class="cursor-pointer text-white/80 font-semibold">Cómo conectarlo en Google Merchant Center</summary>
                <ol class="list-decimal list-inside space-y-1 mt-2">
                    <li>Entra a <a href="https://merchants.google.com" target="_blank" rel="noopener" class="text-tribio-cyan underline">Google Merchant Center ↗</a> y crea tu cuenta (país: <strong>Perú</strong>).</li>
                    <li>Cuando te pida tu sitio web, escribe tu dominio y elige verificar con <strong>etiqueta HTML</strong>. Pégala arriba, guarda aquí y vuelve a Google para pulsar <strong>Verificar</strong>.</li>
                    <li>Completa <strong>Envíos</strong> y <strong>Devoluciones</strong> en la configuración: Google no muestra productos sin esos datos.</li>
                    <li>En <strong>Productos → Agregar productos</strong>, elige <strong>desde un archivo</strong> → <strong>vínculo (URL)</strong>, pega la dirección de tu catálogo y prográmalo <strong>todos los días</strong>.</li>
                    <li>Google revisa los productos (puede tardar unos días). Los aprobados aparecen gratis en Google.</li>
                </ol>
            </details>
            </div>

            <div x-show="mode !== 'off'" x-cloak>
                <label class="input-label" for="default_condition">Condición de tus productos</label>
                <select id="default_condition" name="default_condition" class="input-field">
                    @foreach(\App\Models\StoreMarketingIntegration::CONDITIONS as $value => $label)
                        <option value="{{ $value }}" @selected(old('default_condition', $integration->default_condition) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] text-white/40 mt-1">Google lo exige. Puedes cambiarlo en un producto puntual desde su ficha (sección Publicidad).</p>
            </div>

            @if($catalog)
            <div x-show="mode !== 'off'" x-cloak class="rounded-xl border border-white/10 bg-white/5 p-3">
                <p class="text-sm text-white font-semibold">
                    {{ $catalog['products_included'] }} de {{ $catalog['products_total'] }} productos van a Google
                    @if($catalog['items'] !== $catalog['products_included'])<span class="text-white/40 font-normal">({{ $catalog['items'] }} artículos contando variantes)</span>@endif
                </p>
                @if(count($catalog['excluded']))
                    <p class="text-[11px] text-white/40 mt-1">Estos no aparecen en Google:</p>
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

        {{-- 2. Google Analytics --}}
        <div class="glass-card p-5 sm:p-6 space-y-4">
            <div>
                <h3 class="text-white font-bold text-sm">2. Google Analytics <span class="text-white/40 font-normal">(opcional)</span></h3>
                <p class="text-xs text-white/50 mt-1">Mide cuántas personas visitan tu tienda, de dónde llegan, qué productos miran y cuánto venden.</p>
            </div>
            <div>
                <label class="input-label" for="measurement_id">ID de medición</label>
                <input id="measurement_id" type="text" name="measurement_id" autocomplete="off" value="{{ old('measurement_id', $integration->measurement_id) }}" class="input-field font-mono text-sm" placeholder="Ej.: G-AB12CD34EF">
            </div>
            <details class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/60">
                <summary class="cursor-pointer text-white/80 font-semibold">¿Dónde lo encuentro?</summary>
                <ol class="list-decimal list-inside space-y-1 mt-2">
                    <li>Entra a <a href="https://analytics.google.com" target="_blank" rel="noopener" class="text-tribio-cyan underline">Google Analytics ↗</a> y crea una propiedad para tu tienda.</li>
                    <li>Ve a <strong>Administrar → Flujos de datos → Web</strong> y agrega la dirección de tu tienda.</li>
                    <li>Copia el <strong>ID de medición</strong> (empieza con G-). También puedes pegar el código completo de la etiqueta.</li>
                </ol>
            </details>
        </div>

        {{-- 3. Google Ads --}}
        <div class="glass-card p-5 sm:p-6 space-y-4">
            <div>
                <h3 class="text-white font-bold text-sm">3. Google Ads <span class="text-white/40 font-normal">(opcional)</span></h3>
                <p class="text-xs text-white/50 mt-1">Si haces anuncios en Google, esto le avisa a Google Ads cada venta, para que sepas qué anuncios venden y Google los optimice.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="input-label" for="ads_conversion_id">ID de conversión</label>
                    <input id="ads_conversion_id" type="text" name="ads_conversion_id" autocomplete="off" value="{{ old('ads_conversion_id', $integration->ads_conversion_id) }}" class="input-field font-mono text-sm" placeholder="Ej.: AW-123456789">
                </div>
                <div>
                    <label class="input-label" for="ads_conversion_label">Etiqueta de conversión</label>
                    <input id="ads_conversion_label" type="text" name="ads_conversion_label" autocomplete="off" value="{{ old('ads_conversion_label', $integration->ads_conversion_label) }}" class="input-field font-mono text-sm" placeholder="Ej.: AbC-D_efG-h12">
                </div>
            </div>
            <p class="text-[11px] text-white/40">Puedes pegar en el primer campo el fragmento del evento completo (el que dice <code>send_to: 'AW-…/…'</code>): nosotros separamos el ID y la etiqueta.</p>
            <details class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-white/60">
                <summary class="cursor-pointer text-white/80 font-semibold">¿Dónde los encuentro?</summary>
                <ol class="list-decimal list-inside space-y-1 mt-2">
                    <li>En <a href="https://ads.google.com" target="_blank" rel="noopener" class="text-tribio-cyan underline">Google Ads ↗</a>, ve a <strong>Objetivos → Conversiones → Nueva acción de conversión → Sitio web</strong>.</li>
                    <li>Elige la categoría <strong>Compra</strong> y la opción de <strong>configurar el código manualmente</strong>.</li>
                    <li>Copia el <strong>ID de conversión</strong> (AW-…) y la <strong>etiqueta de conversión</strong>.</li>
                    <li>Si además vinculas Merchant Center con Google Ads, tus anuncios pueden mostrar a cada persona los productos que miró en tu tienda.</li>
                </ol>
            </details>
        </div>

        <p class="text-[11px] text-white/40 px-1">🍪 Tu tienda muestra un aviso de cookies: Google Analytics y Google Ads solo se activan para los compradores que lo aceptan, igual que el Píxel de Meta.</p>
    </form>
</div>
@endsection
