@extends('layouts.dashboard')
@section('title','Cambiar Contraseña') @section('page_title','🔒 Cambiar Contraseña')
@section('content')
<div class="w-full max-w-lg mx-auto space-y-5 sm:space-y-6">

    @if($googleReady)
    <div class="glass-card p-5 sm:p-6 space-y-3">
        <h3 class="text-white font-bold text-sm flex items-center gap-2">
            <svg class="w-4 h-4" viewBox="0 0 48 48">
                <path fill="#FFC107" d="M43.6 20.5h-1.9V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.8-.4-4.5z"/>
                <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 16.3 3 9.7 7.3 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 45c5.5 0 10.4-1.9 14.1-5.1l-6.5-5.5C29.6 36 26.9 37 24 37c-5.2 0-9.6-3.3-11.2-8l-6.6 5C9.6 40.6 16.3 45 24 45z"/>
                <path fill="#1976D2" d="M43.6 20.5h-1.9V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.6l6.5 5.5c-.5.4 7.4-5.4 7.4-15.6 0-1.4-.1-2.8-.4-4.5z"/>
            </svg>
            Conectar con Google
        </h3>

        @if(Auth::user()->google_id)
            <div class="flex items-center gap-2 text-emerald-400 text-xs font-bold">
                <span>✓</span><span>Tu cuenta está conectada con Google</span>
            </div>
            <p class="text-xs text-white/50">Ya inicias sesión únicamente con el botón de Google. Si quieres vincular una cuenta de Google distinta, puedes volver a conectar.</p>
            <a href="{{ route('dashboard.google.connect') }}"
               onclick="return confirm('¿Conectar una cuenta de Google distinta? Tu contraseña actual dejará de funcionar y solo podrás iniciar sesión con esa cuenta de Google (en la web). La app móvil todavía no tiene botón de Google, así que no podrás entrar ahí hasta que se habilite.');"
               class="btn-secondary !text-xs inline-flex items-center gap-2 w-fit">Conectar otra cuenta de Google</a>
        @else
            <p class="text-xs text-white/50">Une tu Tribio Pass a tu cuenta de Gmail para entrar más rápido, sin escribir contraseña. Importante: una vez conectada, tu contraseña actual deja de funcionar — solo podrás iniciar sesión con el botón de Google (en la web; la app móvil todavía no tiene este botón, así que no podrás entrar ahí hasta que se habilite).</p>
            <a href="{{ route('dashboard.google.connect') }}"
               onclick="return confirm('Al conectar tu cuenta con Google, tu contraseña actual dejará de funcionar. Solo podrás iniciar sesión con el botón de Google (en la web). La app móvil todavía no tiene ese botón, así que no podrás entrar ahí hasta que se habilite. ¿Deseas continuar?');"
               class="btn-primary !text-xs inline-flex items-center gap-2 w-fit">Conectar con Google</a>
        @endif
    </div>
    @endif

    <form id="password-form" method="POST" action="{{ route('dashboard.password.update') }}" class="space-y-5 sm:space-y-6" x-data="{ showCurrent: false, showNew: false, showConfirm: false }">
        @csrf

        {{-- Barra de acción: pegada arriba, no tapa el contenido al hacer scroll --}}
        <div class="sticky top-0 z-20 -mx-1 sm:mx-0">
            <div class="glass-card px-3.5 sm:px-5 py-2.5 sm:py-3 flex items-center gap-3">
                <a href="{{ route('dashboard.index') }}" class="btn-ghost !px-2.5 !py-2 flex-shrink-0" aria-label="Volver al panel" title="Volver al panel">←</a>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-white/40 uppercase tracking-wider leading-none mb-0.5">Mi Cuenta</p>
                    <p class="text-white font-bold text-sm truncate">Cambiar contraseña</p>
                </div>
                <button type="submit" class="btn-primary flex-shrink-0 !px-4 sm:!px-6 text-xs sm:text-sm">
                    <span class="hidden sm:inline">💾 Guardar</span><span class="sm:hidden">💾</span>
                </button>
            </div>
        </div>

        @if($errors->any())
        <div class="glass-card p-4 sm:p-5 border border-red-400/20 bg-red-500/5">
            <p class="text-red-400 font-bold text-xs sm:text-sm mb-1.5 flex items-center gap-2">⚠️ Revisa estos campos antes de guardar:</p>
            <ul class="text-xs text-red-400/90 space-y-1 list-disc list-inside">
                @foreach($errors->all() as $error_msg)
                    <li>{{ $error_msg }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="glass-card p-5 sm:p-6 space-y-4">
            <h3 class="text-white font-bold text-sm mb-1">🔒 Cambiar contraseña</h3>
            <p class="text-xs text-white/50 mb-2">Usa una contraseña de al menos 8 caracteres que no uses en otro lugar.</p>

            <div>
                <label class="input-label">Contraseña actual</label>
                <div class="relative">
                    <input :type="showCurrent ? 'text' : 'password'" name="current_password" class="input-field pr-11" autocomplete="current-password">
                    <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 text-sm" :aria-label="showCurrent ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <span x-show="!showCurrent">👁️</span><span x-show="showCurrent" x-cloak>🙈</span>
                    </button>
                </div>
            </div>

            <div>
                <label class="input-label">Nueva contraseña</label>
                <div class="relative">
                    <input :type="showNew ? 'text' : 'password'" name="password" class="input-field pr-11" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                    <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 text-sm" :aria-label="showNew ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <span x-show="!showNew">👁️</span><span x-show="showNew" x-cloak>🙈</span>
                    </button>
                </div>
            </div>

            <div>
                <label class="input-label">Confirmar nueva contraseña</label>
                <div class="relative">
                    <input :type="showConfirm ? 'text' : 'password'" name="password_confirmation" class="input-field pr-11" autocomplete="new-password" placeholder="Repite la nueva contraseña">
                    <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white/80 text-sm" :aria-label="showConfirm ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <span x-show="!showConfirm">👁️</span><span x-show="showConfirm" x-cloak>🙈</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
