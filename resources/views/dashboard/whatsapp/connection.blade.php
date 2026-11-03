@extends('layouts.dashboard')
@section('title', 'WhatsApp')
@section('page_title', '💬 Conexión de WhatsApp')
@section('content')
@php $connected = $connection && $connection->status !== 'disconnected'; @endphp
<div class="w-full max-w-3xl mx-auto space-y-5">
    <a href="{{ route('dashboard.whatsapp.index') }}" class="text-xs text-tribio-cyan underline">← Chats</a>

    @if(!$ready)
        <div class="glass-card p-6 text-center"><p class="text-white font-bold">⏳ Estamos activando WhatsApp en tu cuenta</p></div>
    @else
        @if($connected)
        <div class="glass-card p-4 sm:p-5 space-y-3">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="min-w-0">
                    <p class="text-white font-bold">{{ $connection->verified_name ?: 'Tu número' }} {{ $connection->display_phone_number ? '· ' . $connection->display_phone_number : '' }}</p>
                    <p class="text-xs text-white/50 mt-0.5">
                        @if($connection->quality_rating) Calidad según Meta: {{ ['GREEN' => 'alta', 'YELLOW' => 'media', 'RED' => 'baja'][$connection->quality_rating] ?? $connection->quality_rating }} · @endif
                        @if($connection->isConnected() && app(\App\Services\Whatsapp\WhatsappSchema::class)->broadcastsReady()) Escribir primero: {{ $connection->messaging_limit_label }} · @endif
                        {{ $connection->last_webhook_at ? 'Último aviso de Meta ' . $connection->last_webhook_at->locale('es')->diffForHumans() : 'Meta todavía no envió ningún aviso' }}
                    </p>
                </div>
                <span class="badge {{ ['connected' => 'badge-green', 'error' => 'badge-red'][$connection->status] ?? 'badge-gray' }}">{{ $connection->status_label }}</span>
            </div>
            @if($connection->last_error)<p class="text-xs text-red-300" role="alert">{{ $connection->last_error }}</p>@endif
            <div class="flex items-center gap-2 flex-wrap">
                <form method="POST" action="{{ route('dashboard.whatsapp.connection.test') }}">@csrf<button type="submit" class="btn-secondary text-xs">Probar conexión</button></form>
                <form method="POST" action="{{ route('dashboard.whatsapp.connection.destroy') }}" onsubmit="return confirm('¿Desconectar el número? Dejarás de recibir chats aquí; el historial se conserva.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost text-xs">Desconectar</button>
                </form>
            </div>
        </div>

        <div class="glass-card p-4 sm:p-5 space-y-3" x-data="{ copied: null, copy(text, key) { navigator.clipboard?.writeText(text); this.copied = key; setTimeout(() => this.copied = null, 1500); } }">
            <h3 class="text-white font-bold text-base">Último paso en Meta: el webhook</h3>
            <p class="text-xs text-white/60">En tu app de Meta → WhatsApp → Configuración → Webhook, pega estos dos datos, verifica y suscríbete a los campos <strong>messages</strong> (chats y avisos de entrega) y <strong>message_template_status_update</strong> (aprobación de tus plantillas).</p>
            @foreach(['url' => ['URL de devolución de llamada', $connection->webhookUrl()], 'token' => ['Token de verificación', $connection->verify_token]] as $key => [$label, $value])
                <div>
                    <p class="input-label">{{ $label }}</p>
                    <div class="flex items-center gap-2">
                        <code class="input-field !py-1 !text-xs flex-1 min-w-0 truncate select-all">{{ $value }}</code>
                        <button type="button" class="btn-ghost !py-1 text-xs flex-shrink-0" @click="copy(@js($value), '{{ $key }}')" x-text="copied === '{{ $key }}' ? 'Copiado' : 'Copiar'">Copiar</button>
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('dashboard.whatsapp.connection.update') }}" class="glass-card p-4 sm:p-5 space-y-4">
            @csrf @method('PUT')
            <div>
                <h3 class="text-white font-bold text-base">{{ $connected ? 'Datos de la conexión' : 'Conecta tu número (WhatsApp Cloud API)' }}</h3>
                <p class="text-xs text-white/60 mt-1">Lo encuentras en developers.facebook.com → tu app → WhatsApp → Configuración de la API. Usa un número que no esté en la app de WhatsApp del celular, y un <strong>token permanente</strong> de un usuario del sistema (el temporal vence en 24 h). Meta te cobra los mensajes directamente.</p>
            </div>
            @if($errors->any())
                <div role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="input-label" for="wa-waba">ID de la cuenta de WhatsApp Business</label>
                    <input id="wa-waba" name="waba_id" inputmode="numeric" class="input-field" value="{{ old('waba_id', $connection?->waba_id) }}" required>
                </div>
                <div>
                    <label class="input-label" for="wa-phone">Identificador del número de teléfono</label>
                    <input id="wa-phone" name="phone_number_id" inputmode="numeric" class="input-field" value="{{ old('phone_number_id', $connection?->phone_number_id) }}" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="input-label" for="wa-token">Token de acceso permanente @if($connected)<span class="text-white/40 font-normal">(déjalo vacío para no cambiarlo)</span>@endif</label>
                    <input id="wa-token" name="access_token" type="password" autocomplete="off" class="input-field" @unless($connected) required @endunless>
                </div>
                <div class="sm:col-span-2">
                    <label class="input-label" for="wa-secret">Clave secreta de la app @if($connected)<span class="text-white/40 font-normal">(déjala vacía para no cambiarla)</span>@endif</label>
                    <input id="wa-secret" name="app_secret" type="password" autocomplete="off" class="input-field" @unless($connected) required @endunless>
                    <p class="text-[11px] text-white/40 mt-1">Configuración de la app → Básica → Clave secreta. Con ella comprobamos que cada aviso viene de verdad de Meta.</p>
                </div>
            </div>
            <button type="submit" class="btn-primary text-xs sm:text-sm">{{ $connected ? 'Guardar y probar' : 'Conectar' }}</button>
        </form>
    @endif
</div>
@endsection
