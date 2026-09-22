@extends('layouts.public')
@section('title', 'Ingresar — Tribio')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-[#F6F6F6] pt-28 pb-16">
    <div class="w-full max-w-md px-6">
        <div class="glass-card p-10 bg-white shadow-xl rounded-3xl border border-slate-100">
            {{-- Logo --}}
            <div class="text-center mb-8">
                <div class="w-14 h-14 rounded-2xl bg-slate-900 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-slate-900/10">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-black text-slate-900">Bienvenido a <span class="text-sky-500">Tribio</span></h1>
                <p class="text-slate-500 text-sm mt-2">Ingresa a tu panel de control</p>
            </div>

            @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100 text-red-600 text-sm">
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="input-label">Correo electrónico</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           placeholder="tu@correo.com"
                           class="input-field" required autofocus>
                </div>
                <div>
                    <label class="input-label">Contraseña</label>
                    <input type="password" name="password"
                           placeholder="••••••••"
                           class="input-field" required>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="remember" id="remember" class="w-4 h-4 rounded accent-sky-500 cursor-pointer">
                    <label for="remember" class="text-slate-500 text-sm cursor-pointer select-none">Recordarme</label>
                </div>
                <button type="submit" class="btn-primary w-full justify-center py-3.5 text-base">
                    Ingresar al panel
                </button>
            </form>

            @if($googleReady)
            <div class="flex items-center gap-3 my-5">
                <div class="flex-1 h-px bg-slate-100"></div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">o</span>
                <div class="flex-1 h-px bg-slate-100"></div>
            </div>
            <a href="{{ route('auth.google.redirect') }}" class="w-full flex items-center justify-center gap-2.5 py-3 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-50 transition-all">
                <svg class="w-4 h-4" viewBox="0 0 48 48">
                    <path fill="#FFC107" d="M43.6 20.5h-1.9V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.8-.4-4.5z"/>
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.6 5.1 29.6 3 24 3 16.3 3 9.7 7.3 6.3 14.7z"/>
                    <path fill="#4CAF50" d="M24 45c5.5 0 10.4-1.9 14.1-5.1l-6.5-5.5C29.6 36 26.9 37 24 37c-5.2 0-9.6-3.3-11.2-8l-6.6 5C9.6 40.6 16.3 45 24 45z"/>
                    <path fill="#1976D2" d="M43.6 20.5h-1.9V20H24v8h11.3c-.8 2.3-2.3 4.3-4.2 5.6l6.5 5.5c-.5.4 7.4-5.4 7.4-15.6 0-1.4-.1-2.8-.4-4.5z"/>
                </svg>
                Continuar con Google
            </a>
            @endif

            <p class="text-center text-slate-400 text-sm mt-6">
                ¿No tienes cuenta?
                <a href="{{ route('home') }}#precios" class="text-sky-500 hover:text-sky-600 transition-colors font-semibold">Registrar mi negocio</a>
            </p>
        </div>
    </div>
</div>
@endsection
