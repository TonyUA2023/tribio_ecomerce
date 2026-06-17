@extends('layouts.public')
@section('title', 'Ingresar — Tribio')

@section('content')
<div class="min-h-screen flex items-center justify-center hero-bg grid-pattern pt-20 pb-10">
    <div class="w-full max-w-md px-6">
        <div class="glass-card p-10">
            {{-- Logo --}}
            <div class="text-center mb-8">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-tribio-purple to-tribio-pink flex items-center justify-center mx-auto mb-4 shadow-lg shadow-tribio-purple/40">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-black text-white">Bienvenido a <span class="gradient-text">Tribio</span></h1>
                <p class="text-white/50 text-sm mt-2">Ingresa a tu panel de control</p>
            </div>

            @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
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
                    <input type="checkbox" name="remember" id="remember" class="w-4 h-4 rounded accent-tribio-purple">
                    <label for="remember" class="text-white/50 text-sm">Recordarme</label>
                </div>
                <button type="submit" class="btn-primary w-full justify-center py-3.5 text-base">
                    Ingresar al panel
                </button>
            </form>

            <p class="text-center text-white/40 text-sm mt-6">
                ¿No tienes cuenta?
                <a href="{{ route('register') }}" class="text-tribio-cyan hover:text-white transition-colors font-medium">Registrar mi negocio</a>
            </p>
        </div>
    </div>
</div>
@endsection
