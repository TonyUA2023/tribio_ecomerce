@extends('templates.elegant-refurbished.layout')

@section('title', 'Ingresar o Crear Cuenta')

@section('content')
<div class="er-container er-section" style="max-width: 500px; padding-top: var(--space-xl);">
    <div style="background-color: var(--bg-secondary); border-radius: var(--radius-lg); padding: var(--space-lg); box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
        
        <div style="text-align: center; margin-bottom: var(--space-md);">
            <h1 class="er-title-md" style="margin-bottom: 0.5rem;">Bienvenido a {{ $store->name ?? 'Tienda' }}</h1>
            <p class="er-subtitle" style="font-size: 0.95rem;">Ingresa para ver tus pedidos o regístrate para comprar más rápido.</p>
        </div>

        <!-- Google SSO (Store Level) -->
        <a href="{{ route('store.auth.google', ['tenant' => $tenant_domain ?? 'default']) }}" class="er-btn" style="width: 100%; margin-bottom: var(--space-md); background-color: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-main); display: flex; gap: 0.5rem; padding: 0.8rem;">
            <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
                <g transform="matrix(1, 0, 0, 1, 27.009001, -39.238998)">
                    <path fill="#4285F4" d="M -3.264 51.509 C -3.264 50.719 -3.334 49.969 -3.454 49.239 L -14.754 49.239 L -14.754 53.749 L -8.284 53.749 C -8.574 55.229 -9.424 56.479 -10.684 57.329 L -10.684 60.329 L -6.824 60.329 C -4.564 58.239 -3.264 55.159 -3.264 51.509 Z"/>
                    <path fill="#34A853" d="M -14.754 63.239 C -11.514 63.239 -8.804 62.159 -6.824 60.329 L -10.684 57.329 C -11.764 58.049 -13.134 58.489 -14.754 58.489 C -17.884 58.489 -20.534 56.379 -21.484 53.529 L -25.464 53.529 L -25.464 56.619 C -23.494 60.539 -19.444 63.239 -14.754 63.239 Z"/>
                    <path fill="#FBBC05" d="M -21.484 53.529 C -21.734 52.809 -21.864 52.039 -21.864 51.239 C -21.864 50.439 -21.724 49.669 -21.484 48.949 L -21.484 45.859 L -25.464 45.859 C -26.284 47.479 -26.754 49.299 -26.754 51.239 C -26.754 53.179 -26.284 54.999 -25.464 56.619 L -21.484 53.529 Z"/>
                    <path fill="#EA4335" d="M -14.754 43.989 C -12.984 43.989 -11.404 44.599 -10.154 45.789 L -6.734 42.369 C -8.804 40.429 -11.514 39.239 -14.754 39.239 C -19.444 39.239 -23.494 41.939 -25.464 45.859 L -21.484 48.949 C -20.534 46.099 -17.884 43.989 -14.754 43.989 Z"/>
                </g>
            </svg>
            Continuar con Google
        </a>

        <div style="display: flex; align-items: center; text-align: center; color: var(--text-muted); margin-bottom: var(--space-md);">
            <hr style="flex: 1; border: none; border-top: 1px solid var(--border-color);">
            <span style="padding: 0 1rem; font-size: 0.85rem;">O usa tu correo</span>
            <hr style="flex: 1; border: none; border-top: 1px solid var(--border-color);">
        </div>

        <!-- Classic Login Form -->
        <form action="{{ route('store.login.submit', ['tenant' => $tenant_domain ?? 'default']) }}" method="POST">
            @csrf
            <div class="er-form-group">
                <input type="email" name="email" class="er-input" placeholder="Correo electrónico" required>
            </div>
            
            <div class="er-form-group" style="margin-bottom: var(--space-md);">
                <input type="password" name="password" class="er-input" placeholder="Contraseña" required>
            </div>

            <button type="submit" class="er-btn er-btn-primary" style="width: 100%; padding: 0.8rem; font-size: 1rem;">
                Ingresar / Registrarse
            </button>
            <p style="text-align: center; margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                Si no tienes cuenta, te la crearemos automáticamente.
            </p>
        </form>

    </div>
</div>
@endsection
