@extends('layouts.dashboard')
@section('title', 'Equipo')
@section('page_title', '👥 Equipo')
@section('content')
@php
    $roleLabel = ['seller' => 'Vendedor', 'admin' => 'Administrador'];
    $shareText = 'Hola, crea tu Tribio Pass con Google en ' . $passUrl . ' usando el correo con el que te invité. Al entrar verás «Ingresar a ' . $store->name . '».';
@endphp
<div class="w-full max-w-[1600px] mx-auto space-y-5">
    <p class="text-sm text-white/60 max-w-3xl">Tú eres el administrador global de <strong>{{ $store->name }}</strong>. Cada trabajador usa <strong>su propio Tribio Pass</strong> (con Google en un toque) y tú decides qué módulos puede abrir. Cada venta queda con su nombre.</p>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">
        {{-- Invitar --}}
        <form method="POST" action="{{ route('dashboard.team.store') }}" class="glass-card p-4 sm:p-5 space-y-4 xl:col-span-2">
            @csrf
            <h3 class="text-white font-bold text-base">Agregar a una persona</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-2"><label class="input-label" for="tm-email">Correo de su Tribio Pass</label>
                    <input id="tm-email" name="email" type="email" class="input-field" value="{{ old('email') }}" placeholder="ana@gmail.com" required autocomplete="off"></div>
                <div><label class="input-label" for="tm-name">Nombre (opcional)</label>
                    <input id="tm-name" name="name" class="input-field" maxlength="80" value="{{ old('name') }}" placeholder="Ana Torres"></div>
                <div><label class="input-label" for="tm-role">Rol</label>
                    <select id="tm-role" name="role" class="input-field" x-data x-ref="role">
                        <option value="seller" @selected(old('role', 'seller') === 'seller')>Vendedor (solo lo que marques)</option>
                        @if($isOwner)<option value="admin" @selected(old('role') === 'admin')>Administrador (todo)</option>@endif
                    </select></div>
            </div>
            <fieldset>
                <legend class="input-label">Qué puede abrir un vendedor</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                    @foreach($modules as $key => $module)
                        <label class="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-3 cursor-pointer">
                            <input type="checkbox" name="modules[]" value="{{ $key }}" class="mt-0.5" @checked(in_array($key, old('modules', \App\Services\Team\TeamAccess::defaultModules()), true))>
                            <span><strong class="text-sm text-white block">{{ $module['label'] }}</strong><small class="text-[11px] text-white/50">{{ $module['hint'] }}</small></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <button type="submit" class="btn-primary">Dar acceso</button>
        </form>

        {{-- Cómo se une --}}
        <aside class="glass-card p-4 sm:p-5 space-y-3" x-data="{ copied: false }">
            <h3 class="text-white font-bold text-base">Cómo entra tu trabajador</h3>
            <ol class="text-sm text-white/70 space-y-2 list-decimal pl-5">
                <li>Tú lo agregas aquí con su correo.</li>
                <li>Él crea su <strong>Tribio Pass con Google</strong>: un toque, sin llenar direcciones ni datos.</li>
                <li>Al abrir su Tribio Pass verá primero <strong>«Ingresar a {{ $store->name }}»</strong>.</li>
            </ol>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-secondary text-xs" @click="navigator.clipboard.writeText(@js($shareText)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"><span x-text="copied ? '¡Copiado!' : 'Copiar mensaje'">Copiar mensaje</span></button>
                <a class="btn-secondary text-xs" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($shareText) }}">Enviar por WhatsApp</a>
            </div>
        </aside>
    </div>

    {{-- Equipo --}}
    <section class="space-y-3" aria-labelledby="tm-title">
        <h3 id="tm-title" class="text-white font-bold text-base">Tu equipo ({{ $members->count() + 1 }})</h3>

        <div class="glass-card p-4 flex items-center justify-between gap-3 flex-wrap">
            <span class="min-w-0"><strong class="text-white text-sm">{{ $owner?->name ?? 'Dueño' }}</strong> <span class="text-white/50 text-xs">· {{ $owner?->email }}</span></span>
            <span class="badge badge-purple">Administrador global</span>
        </div>

        @foreach($members as $member)
            <form method="POST" action="{{ route('dashboard.team.update', $member->id) }}" class="glass-card p-4 sm:p-5 space-y-4 {{ $member->is_active ? '' : 'opacity-60' }}" x-data="{ role: @js($member->role) }">
                @csrf @method('PUT')
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <span class="min-w-0">
                        <strong class="text-white text-sm">{{ $member->user?->name ?? $member->name ?? $member->email }}</strong>
                        <span class="text-white/50 text-xs">· {{ $member->email }}</span>
                        @if($member->user_id)<span class="badge badge-green">Ya tiene Tribio Pass</span>@else<span class="badge badge-gold">Esperando que cree su Tribio Pass</span>@endif
                    </span>
                    <label class="flex items-center gap-2 text-xs text-white/70"><input type="checkbox" name="is_active" value="1" @checked($member->is_active)> Con acceso</label>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div><label class="input-label" for="mn-{{ $member->id }}">Nombre</label><input id="mn-{{ $member->id }}" name="name" class="input-field" maxlength="80" value="{{ $member->name }}"></div>
                    <div><label class="input-label" for="mr-{{ $member->id }}">Rol</label>
                        <select id="mr-{{ $member->id }}" name="role" class="input-field" x-model="role" @disabled(!$isOwner && $member->isAdmin())>
                            <option value="seller">Vendedor</option>
                            @if($isOwner || $member->isAdmin())<option value="admin">Administrador</option>@endif
                        </select>
                        @if(!$isOwner && $member->isAdmin())<input type="hidden" name="role" value="admin">@endif
                    </div>
                </div>
                <fieldset x-show="role === 'seller'">
                    <legend class="input-label">Módulos que puede abrir</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                        @foreach($modules as $key => $module)
                            <label class="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-white/80 cursor-pointer">
                                <input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, $member->modules ?? \App\Services\Team\TeamAccess::defaultModules(), true))> {{ $module['label'] }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <p class="text-xs text-white/50" x-show="role === 'admin'" x-cloak>El administrador abre todo, como tú, incluido este equipo.</p>
                <div class="flex items-center justify-between gap-3">
                    <button type="submit" class="btn-primary text-sm">Guardar</button>
                    @unless(!$isOwner && $member->isAdmin())
                        <button type="submit" form="rm-{{ $member->id }}" class="text-xs text-red-300 underline min-h-[44px]" onclick="return confirm('¿Quitar el acceso de esta persona?')">Quitar del equipo</button>
                    @endunless
                </div>
            </form>
            @unless(!$isOwner && $member->isAdmin())
                <form id="rm-{{ $member->id }}" method="POST" action="{{ route('dashboard.team.destroy', $member->id) }}" class="hidden">@csrf @method('DELETE')</form>
            @endunless
        @endforeach

        @if($members->isEmpty())
            <p class="glass-card p-6 text-center text-sm text-white/60">Todavía trabajas solo. Agrega a tu primer vendedor arriba.</p>
        @endif
    </section>
</div>
@endsection
