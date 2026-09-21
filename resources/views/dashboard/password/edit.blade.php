@extends('layouts.dashboard')
@section('title','Cambiar Contraseña') @section('page_title','🔒 Cambiar Contraseña')
@section('content')
<div class="w-full max-w-lg mx-auto space-y-5 sm:space-y-6">
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
