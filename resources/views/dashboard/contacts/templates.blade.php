@extends('layouts.dashboard')
@section('title', 'Plantillas de WhatsApp')
@section('page_title', '💬 Plantillas de WhatsApp')
@section('content')
<div class="w-full max-w-3xl mx-auto space-y-5">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <p class="text-sm text-white/60 max-w-2xl">Los mensajes que usas con tus clientes desde su ficha y desde Seguimientos. Los datos entre llaves se completan solos con los de cada cliente.</p>
        <a href="{{ route('dashboard.clientes.index') }}" class="text-xs text-tribio-cyan underline">← Clientes</a>
    </div>

    @if($errors->any())
        <div class="glass-card p-4" role="alert">@foreach($errors->all() as $error)<p class="text-xs text-red-300">{{ $error }}</p>@endforeach</div>
    @endif

    <div class="glass-card p-4">
        <p class="text-[11px] text-white/40 uppercase tracking-wider">Datos que puedes usar</p>
        <div class="flex flex-wrap gap-2 mt-2">
            @foreach($placeholders as $key => $label)
                <span class="badge badge-gray" title="{{ $label }}">{{ '{' . $key . '}' }}</span>
            @endforeach
        </div>
        <p class="text-[11px] text-white/40 mt-2">Las plantillas de <strong>ofertas</strong> solo se pueden enviar a clientes que aceptaron recibirlas (Ley 29733).</p>
    </div>

    @foreach($templates as $template)
    <div class="glass-card p-4 sm:p-5" x-data="{ open: false, body: @js($template['body']) }">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm text-white font-semibold">
                    {{ $template['name'] }}
                    @if($template['promo'])<span class="badge badge-gold ml-1">Ofertas</span>@endif
                    @if($template['builtin'] && $template['customized'])<span class="badge badge-blue ml-1">Editada</span>@endif
                    @unless($template['builtin'])<span class="badge badge-purple ml-1">Propia</span>@endunless
                </p>
                <p class="text-xs text-white/60 mt-1 whitespace-pre-line" x-show="!open">{{ $preview($template['body']) }}</p>
            </div>
            <button type="button" class="btn-ghost text-xs flex-shrink-0" @click="open = !open" x-text="open ? 'Cerrar' : 'Editar'"></button>
        </div>

        <form method="POST" action="{{ route('dashboard.clientes.templates.update', $template['key']) }}" class="mt-3 space-y-3" x-show="open" x-cloak>
            @csrf @method('PUT')
            @unless($template['builtin'])
                <div>
                    <label class="input-label" for="tpl-name-{{ $template['key'] }}">Nombre</label>
                    <input id="tpl-name-{{ $template['key'] }}" name="name" class="input-field" maxlength="60" value="{{ $template['name'] }}">
                </div>
            @endunless
            <div>
                <label class="input-label" for="tpl-body-{{ $template['key'] }}">Mensaje</label>
                <textarea id="tpl-body-{{ $template['key'] }}" name="body" rows="3" maxlength="1000" class="input-field" x-model="body" required></textarea>
            </div>
            @unless($template['builtin'])
                <label class="flex items-center gap-2 text-sm text-white/70"><input type="checkbox" name="is_promo" value="1" @checked($template['promo'])> Es de ofertas (solo para quien aceptó)</label>
            @endunless
            <button type="submit" class="btn-primary text-xs sm:text-sm">Guardar</button>
        </form>
        @if(!$template['builtin'] || $template['customized'])
        <form method="POST" action="{{ route('dashboard.clientes.templates.destroy', $template['key']) }}" class="mt-2" x-show="open" x-cloak
              onsubmit="return confirm('{{ $template['builtin'] ? '¿Volver al mensaje original?' : '¿Eliminar esta plantilla?' }}')">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-white/40 hover:text-red-300">{{ $template['builtin'] ? 'Volver al mensaje original' : 'Eliminar plantilla' }}</button>
        </form>
        @endif
    </div>
    @endforeach

    <form method="POST" action="{{ route('dashboard.clientes.templates.store') }}" class="glass-card p-4 sm:p-5 space-y-3">
        @csrf
        <h3 class="text-white font-bold text-base">Nueva plantilla</h3>
        <div>
            <label class="input-label" for="tpl-new-name">Nombre</label>
            <input id="tpl-new-name" name="name" class="input-field" maxlength="60" value="{{ old('name') }}" placeholder="Ej.: Confirmar entrega" required>
        </div>
        <div>
            <label class="input-label" for="tpl-new-body">Mensaje</label>
            <textarea id="tpl-new-body" name="body" rows="3" maxlength="1000" class="input-field" placeholder="Hola {nombre}, tu pedido {pedido} sale hoy 🚚" required>{{ old('body') }}</textarea>
        </div>
        <label class="flex items-center gap-2 text-sm text-white/70"><input type="checkbox" name="is_promo" value="1" @checked(old('is_promo'))> Es de ofertas (solo para quien aceptó)</label>
        <button type="submit" class="btn-primary text-xs sm:text-sm">Crear plantilla</button>
    </form>
</div>
@endsection
