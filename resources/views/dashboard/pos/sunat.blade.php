@extends('layouts.dashboard')
@section('title', 'Facturación electrónica SUNAT')
@section('page_title', '🧾 Facturación electrónica')
@section('content')
@php $ready = $problems === []; @endphp
<div class="w-full max-w-4xl mx-auto space-y-5">
    @include('dashboard.pos._documents-tabs', ['current' => 'sunat'])

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    {{-- Estado --}}
    <section class="glass-card p-4 sm:p-5" aria-labelledby="sunat-status">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h2 id="sunat-status" class="text-white font-bold text-base">Estado</h2>
                <p class="text-xs text-white/60 mt-1">
                    @if($setting->enabled)
                        <span class="badge {{ $setting->isProduction() ? 'badge-green' : 'badge-gold' }}">{{ $setting->isProduction() ? 'Activa · producción' : 'Activa · modo pruebas' }}</span>
                        @if(!$setting->isProduction()) Los comprobantes de prueba no tienen valor tributario. @endif
                    @else
                        <span class="badge badge-gold">Desactivada</span> Hoy tus boletas y facturas se numeran pero no se envían a SUNAT.
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('dashboard.pos.documents.sunat.test') }}">@csrf<button type="submit" class="btn-secondary text-xs">Probar conexión</button></form>
        </div>
        @if($test)
            <p class="text-xs mt-3 {{ $test['ok'] ? 'text-emerald-300' : 'text-amber-300' }}" role="status">{{ $test['ok'] ? '✓' : '⚠' }} {{ $test['message'] }}</p>
        @endif
        <ul class="mt-4 space-y-2" aria-label="Requisitos">
            @foreach($checklist as $item)
                <li class="flex items-start gap-2 text-xs">
                    <span aria-hidden="true" class="{{ $item['ok'] === true ? 'text-emerald-300' : ($item['ok'] === false ? 'text-red-300' : 'text-amber-300') }}">{{ $item['ok'] === true ? '✓' : ($item['ok'] === false ? '✕' : '!') }}</span>
                    <span class="text-white/80">{{ $item['label'] }}@if($item['ok'] !== true && $item['hint'])<br><span class="text-white/50">{{ $item['hint'] }}</span>@endif</span>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Certificado --}}
    <section class="glass-card p-4 sm:p-5 space-y-3" aria-labelledby="sunat-cert">
        <h2 id="sunat-cert" class="text-white font-bold text-base">Certificado digital</h2>
        <p class="text-xs text-white/60">Es el archivo con el que firmas tus comprobantes (te lo da tu proveedor de certificados digitales). Se guarda <strong>cifrado</strong> y nunca se vuelve a mostrar.</p>
        @if($setting->hasCertificate())
            <div class="rounded-xl border border-white/10 bg-white/5 p-3 text-xs flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-white font-semibold">Certificado cargado @if($setting->certificateExpired())<span class="badge badge-red">Vencido</span>@else<span class="badge badge-green">Vigente</span>@endif</p>
                    <p class="text-white/60 mt-1 break-words">{{ $setting->certificate_subject }}</p>
                    <p class="text-white/60">Vence el {{ $setting->certificate_valid_to?->format('d/m/Y') }}</p>
                </div>
                <form method="POST" action="{{ route('dashboard.pos.documents.sunat.certificate.remove') }}" onsubmit="return confirm('¿Quitar el certificado? La facturación electrónica se desactivará.')">@csrf @method('DELETE')<button type="submit" class="btn-ghost text-xs">Quitar</button></form>
            </div>
        @endif
        <form method="POST" action="{{ route('dashboard.pos.documents.sunat.certificate') }}" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
            @csrf
            <div class="sm:col-span-2">
                <label class="input-label" for="cert-file">{{ $setting->hasCertificate() ? 'Reemplazar certificado' : 'Archivo del certificado' }} (.pfx, .p12 o .pem)</label>
                <input id="cert-file" type="file" name="certificate" accept=".pfx,.p12,.pem,.crt" class="input-field" required>
            </div>
            <div class="sm:col-span-2">
                <label class="input-label" for="cert-pass">Contraseña del certificado</label>
                <input id="cert-pass" type="password" name="certificate_password" class="input-field" autocomplete="new-password" data-1p-ignore="true" data-lpignore="true">
            </div>
            <button type="submit" class="btn-primary">Subir</button>
        </form>
    </section>

    {{-- Configuración --}}
    <form method="POST" action="{{ route('dashboard.pos.documents.sunat.update') }}" class="space-y-5">
        @csrf
        <section class="glass-card p-4 sm:p-5 space-y-4" aria-labelledby="sunat-conf">
            <h2 id="sunat-conf" class="text-white font-bold text-base">Qué se envía y dónde</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="input-label" for="s-env">Ambiente</label>
                    <select id="s-env" name="environment" class="input-field">
                        <option value="beta" @selected(old('environment', $setting->environment) === 'beta')>Pruebas (beta de SUNAT)</option>
                        <option value="production" @selected(old('environment', $setting->environment) === 'production')>Producción (comprobantes reales)</option>
                    </select>
                    <p class="text-[11px] text-white/50 mt-1">Empieza en pruebas: valida que todo funcione y recién entonces pasa a producción.</p>
                </div>
                <div>
                    <label class="input-label" for="s-fac">Facturas</label>
                    <select id="s-fac" name="factura_mode" class="input-field">
                        <option value="manual" @selected(old('factura_mode', $setting->factura_mode) === 'manual')>Las envío yo con el botón «Enviar a SUNAT»</option>
                        <option value="auto" @selected(old('factura_mode', $setting->factura_mode) === 'auto')>Se envían solas al cobrar</option>
                        <option value="never" @selected(old('factura_mode', $setting->factura_mode) === 'never')>No se envían (solo numeración interna)</option>
                    </select>
                </div>
                <div>
                    <label class="input-label" for="s-bol">Boletas</label>
                    <select id="s-bol" name="boleta_mode" class="input-field">
                        <option value="manual" @selected(old('boleta_mode', $setting->boleta_mode) === 'manual')>Las envío yo con el botón «Enviar a SUNAT»</option>
                        <option value="auto" @selected(old('boleta_mode', $setting->boleta_mode) === 'auto')>Se envían solas al cobrar</option>
                        <option value="never" @selected(old('boleta_mode', $setting->boleta_mode) === 'never')>No se envían (solo numeración interna)</option>
                    </select>
                </div>
                <p class="sm:col-span-2 text-[11px] text-white/50">Con el botón, cada boleta y factura sale numerada al cobrar y el comprobante electrónico se genera recién cuando la envías (desde el ticket, Ventas o la pestaña Envíos a SUNAT), con ese mismo número. Para que algunas boletas nunca se informen, usa una serie «solo interna» en Sedes y cajas.</p>
            </div>
        </section>

        <section class="glass-card p-4 sm:p-5 space-y-4" aria-labelledby="sunat-sol">
            <h2 id="sunat-sol" class="text-white font-bold text-base">Usuario SOL secundario</h2>
            <p class="text-xs text-white/60">Solo en producción. Créalo en SUNAT Operaciones en Línea → «Usuarios secundarios» con el perfil de emisión electrónica. No uses tu clave SOL principal. Se guarda cifrado y no se vuelve a mostrar.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="input-label" for="s-user">Usuario</label>
                    <input id="s-user" name="sol_user" class="input-field uppercase" autocomplete="off" maxlength="30" placeholder="{{ filled($setting->sol_user) ? '••••••••  (guardado)' : 'MODDATOS' }}" data-1p-ignore="true" data-lpignore="true">
                </div>
                <div>
                    <label class="input-label" for="s-pass">Clave</label>
                    <input id="s-pass" name="sol_password" type="password" class="input-field" autocomplete="new-password" maxlength="60" placeholder="{{ $setting->hasSolPassword() ? '••••••••  (guardada)' : '' }}" data-1p-ignore="true" data-lpignore="true">
                </div>
            </div>
        </section>

        <section class="glass-card p-4 sm:p-5 space-y-4" aria-labelledby="sunat-mail">
            <h2 id="sunat-mail" class="text-white font-bold text-base">Copia por correo</h2>
            <p class="text-xs text-white/60">Cuando SUNAT recibe un comprobante, te llega por correo el PDF, el XML firmado y la constancia (CDR).</p>
            <label class="flex items-center gap-3 text-sm text-white/80 min-h-[44px]"><input type="checkbox" name="email_company" value="1" class="w-5 h-5" @checked(old('email_company', $setting->email_company))> Enviar a mi correo</label>
            <div>
                <label class="input-label" for="s-mail">Correo de la empresa</label>
                <input id="s-mail" name="email_to" type="email" class="input-field" maxlength="160" value="{{ old('email_to', $setting->email_to) }}" placeholder="facturacion@tuempresa.com">
            </div>
            <label class="flex items-center gap-3 text-sm text-white/80 min-h-[44px]"><input type="checkbox" name="email_customer" value="1" class="w-5 h-5" @checked(old('email_customer', $setting->email_customer))> Enviar también al cliente si dejó su correo</label>
        </section>

        <details class="glass-card p-4 sm:p-5" @if($errors->has('endpoint') || filled($setting->endpoint)) open @endif>
            <summary class="text-white font-bold text-sm cursor-pointer min-h-[44px] flex items-center">Configuración avanzada</summary>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">
                <div class="sm:col-span-2">
                    <label class="input-label" for="s-endpoint">Servicio propio (OSE/PSE)</label>
                    <input id="s-endpoint" name="endpoint" class="input-field" maxlength="255" value="{{ old('endpoint', $setting->endpoint) }}" placeholder="https://… (déjalo vacío para usar SUNAT directo)">
                    <p class="text-[11px] text-white/50 mt-1">Solo si usas un proveedor que ofrece el mismo servicio «billService» de SUNAT. Pide siempre usuario y clave.</p>
                </div>
                <div>
                    <label class="input-label" for="s-tries">Intentos automáticos por comprobante</label>
                    <input id="s-tries" name="max_attempts" type="number" min="1" max="10" class="input-field" value="{{ old('max_attempts', $setting->max_attempts) }}">
                    <p class="text-[11px] text-white/50 mt-1">Si SUNAT no responde se reintenta solo, con esperas cada vez más largas. SUNAT recibe las facturas hasta 3 días después de la venta y las boletas hasta 7.</p>
                </div>
            </div>
        </details>

        <section class="glass-card p-4 sm:p-5 space-y-3">
            <label class="flex items-center gap-3 text-sm text-white font-semibold min-h-[44px]">
                <input type="checkbox" name="enabled" value="1" class="w-5 h-5" @checked(old('enabled', $setting->enabled))> Activar la facturación electrónica
            </label>
            @if(!$ready)
                <p class="text-xs text-amber-300">Para activarla falta: {{ implode(' ', $problems) }}</p>
            @endif
            <div class="flex justify-end"><button type="submit" class="btn-primary">Guardar configuración</button></div>
        </section>
    </form>
</div>
@endsection
