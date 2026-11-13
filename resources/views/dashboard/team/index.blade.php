@extends('layouts.dashboard')
@section('title', 'Equipo')
@section('page_title', '👥 Equipo')
@section('compact', '1')
@section('content')
@php
    use App\Services\Team\AccessLog;
    $shareText = 'Hola, crea tu Tribio Pass con Google en ' . $passUrl . ' usando el correo con el que te invité. Al entrar verás «Ingresar a ' . $store->name . '».';
    $eventLabel = ['login' => 'Inició sesión', 'enter' => 'Entró al negocio', 'visit' => 'Usó el sistema'];
    $healthLabel = ['today' => 'Activo hoy', 'recent' => 'Esta semana', 'stale' => 'Sin entrar hace tiempo', 'never' => 'Nunca entró'];
@endphp
<div class="tm-app" x-data="{ adding: {{ $errors->any() ? 'true' : 'false' }}, share: false, copied: false }">
    <header class="tm-head">
        <div>
            <h1>Equipo</h1>
            <p>
                {{ $summary['people'] }} {{ $summary['people'] === 1 ? 'persona' : 'personas' }}
                @if($logReady) · <span class="tm-ok">{{ $summary['today'] }} {{ $summary['today'] === 1 ? 'activa' : 'activas' }} hoy</span>@endif
                @if($logReady && $summary['idle'] > 0) · <span class="tm-warn">{{ $summary['idle'] }} sin entrar en 7 días</span>@endif
            </p>
        </div>
        <div class="tm-head-actions">
            <button type="button" class="tm-link" @click="share = !share">¿Cómo entra?</button>
            <button type="button" class="btn-primary" @click="adding = !adding" :aria-expanded="adding">+ Agregar persona</button>
        </div>
    </header>

    @if($errors->any())
        <div class="tm-alert" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    <div class="tm-note" x-show="share" x-cloak>
        <p>Tú lo agregas con su correo → crea su <strong>Tribio Pass con Google</strong> (un toque) → al abrirlo ve <strong>«Ingresar a {{ $store->name }}»</strong>.</p>
        <span>
            <button type="button" class="tm-link" @click="navigator.clipboard.writeText(@js($shareText)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" x-text="copied ? '¡Copiado!' : 'Copiar mensaje'">Copiar mensaje</button>
            <a class="tm-link" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($shareText) }}">Enviar por WhatsApp</a>
        </span>
    </div>

    {{-- Agregar --}}
    <form method="POST" action="{{ route('dashboard.team.store') }}" class="tm-add" x-show="adding" x-cloak x-data="{ role: @js(old('role', 'seller')) }">
        @csrf
        <div class="tm-add-row">
            <label>Correo de su Tribio Pass<input name="email" type="email" class="input-field" value="{{ old('email') }}" placeholder="ana@gmail.com" required autocomplete="off"></label>
            <label>Nombre (opcional)<input name="name" class="input-field" maxlength="80" value="{{ old('name') }}" placeholder="Ana Torres"></label>
            <label>Rol
                <select name="role" class="input-field" x-model="role">
                    <option value="seller">Vendedor</option>
                    @if($isOwner)<option value="admin">Administrador</option>@endif
                </select>
            </label>
        </div>
        <div class="tm-mods" x-show="role === 'seller'">
            <span>Puede abrir:</span>
            @foreach($modules as $key => $module)
                <label class="tm-chip" title="{{ $module['hint'] }}"><input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, old('modules', \App\Services\Team\TeamAccess::defaultModules()), true))>{{ $module['label'] }}</label>
            @endforeach
        </div>
        <p class="tm-hint" x-show="role === 'admin'" x-cloak>El administrador abre todo, como tú, incluido este equipo.</p>
        <div class="tm-add-actions"><button type="submit" class="btn-primary">Dar acceso</button><button type="button" class="tm-link" @click="adding = false">Cancelar</button></div>
    </form>

    {{-- Personas --}}
    <section class="tm-list" aria-label="Personas del equipo">
        <div class="tm-row tm-row-head" aria-hidden="true"><span>Persona</span><span>Rol</span><span>Último acceso</span><span class="tm-col-days">7 días</span><span></span></div>

        @php $ownerStat = $stats[(int) $store->user_id] ?? ['last_at' => null, 'days_7d' => 0]; $ownerHealth = AccessLog::health($ownerStat['last_at']); @endphp
        <div class="tm-item">
            <div class="tm-row">
                <span class="tm-person"><i class="tm-avatar">{{ mb_substr($owner?->name ?? 'D', 0, 1) }}</i><span><strong>{{ $owner?->name ?? 'Dueño' }}</strong><small>{{ $owner?->email }}</small></span></span>
                <span><b class="tm-role is-owner">Administrador global</b></span>
                <span class="tm-seen is-{{ $ownerHealth }}"><i></i>{{ $logReady ? AccessLog::when($ownerStat['last_at']) : '—' }}</span>
                <span class="tm-col-days">{{ $logReady ? $ownerStat['days_7d'] . '/7' : '—' }}</span>
                <span><a class="tm-link" href="{{ route('dashboard.team.index', ['persona' => $store->user_id]) }}#actividad">Ver</a></span>
            </div>
        </div>

        @foreach($members as $member)
            @php
                $stat = $stats[$member->user_id] ?? ['last_at' => null, 'days_7d' => 0];
                $health = $member->user_id ? AccessLog::health($stat['last_at']) : 'pending';
                $mine = $member->modules ?? \App\Services\Team\TeamAccess::defaultModules();
            @endphp
            <div class="tm-item {{ $member->is_active ? '' : 'is-off' }}" x-data="{ open: {{ $errors->any() && old('_member') == $member->id ? 'true' : 'false' }}, role: @js($member->role) }">
                <div class="tm-row">
                    <span class="tm-person"><i class="tm-avatar">{{ mb_substr($member->user?->name ?? $member->name ?? $member->email, 0, 1) }}</i><span><strong>{{ $member->user?->name ?? $member->name ?? $member->email }}</strong><small>{{ $member->email }}</small></span></span>
                    <span>
                        <b class="tm-role {{ $member->isAdmin() ? 'is-admin' : '' }}">{{ $member->isAdmin() ? 'Administrador' : 'Vendedor' }}</b>
                        @unless($member->isAdmin())<small class="tm-mini">{{ count($mine) }} {{ count($mine) === 1 ? 'módulo' : 'módulos' }}</small>@endunless
                    </span>
                    <span class="tm-seen is-{{ $health }}"><i></i>
                        @if(!$member->is_active) Sin acceso
                        @elseif(!$member->user_id) Esperando su Tribio Pass
                        @else {{ $logReady ? AccessLog::when($stat['last_at']) : '—' }}@endif
                    </span>
                    <span class="tm-col-days">{{ $member->user_id && $logReady ? $stat['days_7d'] . '/7' : '—' }}</span>
                    <span class="tm-actions">
                        @if($member->user_id)<a class="tm-link" href="{{ route('dashboard.team.index', ['persona' => $member->user_id]) }}#actividad">Ver</a>@endif
                        <button type="button" class="tm-link" @click="open = !open" :aria-expanded="open">Editar</button>
                    </span>
                </div>

                <form method="POST" action="{{ route('dashboard.team.update', $member->id) }}" class="tm-edit" x-show="open" x-cloak>
                    @csrf @method('PUT')
                    <input type="hidden" name="_member" value="{{ $member->id }}">
                    <div class="tm-add-row">
                        <label>Nombre<input name="name" class="input-field" maxlength="80" value="{{ $member->name }}"></label>
                        <label>Rol
                            <select name="role" class="input-field" x-model="role" @disabled(!$isOwner && $member->isAdmin())>
                                <option value="seller">Vendedor</option>
                                @if($isOwner || $member->isAdmin())<option value="admin">Administrador</option>@endif
                            </select>
                            @if(!$isOwner && $member->isAdmin())<input type="hidden" name="role" value="admin">@endif
                        </label>
                        <label class="tm-switch"><input type="checkbox" name="is_active" value="1" @checked($member->is_active)> Con acceso</label>
                    </div>
                    <div class="tm-mods" x-show="role === 'seller'">
                        <span>Puede abrir:</span>
                        @foreach($modules as $key => $module)
                            <label class="tm-chip" title="{{ $module['hint'] }}"><input type="checkbox" name="modules[]" value="{{ $key }}" @checked(in_array($key, $mine, true))>{{ $module['label'] }}</label>
                        @endforeach
                    </div>
                    <div class="tm-add-actions">
                        <button type="submit" class="btn-primary">Guardar</button>
                        @unless(!$isOwner && $member->isAdmin())
                            <button type="submit" form="rm-{{ $member->id }}" class="tm-danger" onclick="return confirm('¿Quitar el acceso de esta persona?')">Quitar del equipo</button>
                        @endunless
                    </div>
                </form>
                @unless(!$isOwner && $member->isAdmin())
                    <form id="rm-{{ $member->id }}" method="POST" action="{{ route('dashboard.team.destroy', $member->id) }}" class="hidden">@csrf @method('DELETE')</form>
                @endunless
            </div>
        @endforeach

        @if($members->isEmpty())
            <p class="tm-empty">Todavía trabajas solo. Agrega a tu primera persona con «+ Agregar persona».</p>
        @endif
    </section>

    {{-- Actividad --}}
    <section class="tm-activity" id="actividad" aria-labelledby="tm-act">
        <div class="tm-act-head">
            <h2 id="tm-act">Actividad reciente</h2>
            @if($only)<a class="tm-link" href="{{ route('dashboard.team.index') }}#actividad">Ver todos</a>@endif
        </div>
        @if(!$logReady)
            <p class="tm-empty">El registro de accesos se activa con la actualización del servidor.</p>
        @elseif($activity->isEmpty())
            <p class="tm-empty">Aún no hay accesos registrados{{ $only ? ' de esta persona' : '' }}.</p>
        @else
            <ul>
                @foreach($activity as $row)
                    <li>
                        <time datetime="{{ $row->created_at->toIso8601String() }}">{{ $row->created_at->copy()->setTimezone(AccessLog::TZ)->locale('es')->isoFormat('ddd D MMM · HH:mm') }}</time>
                        <strong>{{ $row->user?->name ?? 'Usuario' }}</strong>
                        <span class="tm-ev is-{{ $row->type }}">{{ $eventLabel[$row->type] ?? $row->type }}</span>
                        <small>{{ $row->device ?? '—' }}</small>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
@endsection
