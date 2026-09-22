@extends('layouts.dashboard')
@section('title', 'Personalizar plantilla')
@section('page_title', 'Personalizar · ' . $template['name'])

@php
    $fontsForJs = collect($fonts)->map(fn ($font) => [
        'family' => $font['family'],
        'href' => $font['google'] ? 'https://fonts.googleapis.com/css2?family=' . $font['google'] . '&display=swap' : null,
    ])->all();
    $inputName = fn (string $path) => 'settings[' . str_replace('.', '][', $path) . ']';
    $inputId = fn (string $path) => 'tpl-' . str_replace('.', '-', $path);
    $presets = [
        ['Salvia', '#7DA268', '#D4B48A'], ['Océano', '#2F6F9F', '#9CC3D5'], ['Terracota', '#C0643F', '#E3B98F'],
        ['Lavanda', '#7E6BB5', '#E0B7C8'], ['Carbón', '#2E2E33', '#C9A96E'], ['Coral', '#E0605A', '#F2C14E'],
        ['Menta', '#2E9E8C', '#F4B183'], ['Vino', '#8E2F4F', '#D9A5A0'],
    ];
    $itemLabels = ['pillars' => 'Pilar', 'benefits' => 'Beneficio'];
    $customizerConfig = [
        'values' => $values,
        'defaults' => $defaults,
        'fields' => collect($fields)->map(fn ($field) => ['type' => $field['type']])->all(),
        'fonts' => $fontsForJs,
        'backgrounds' => \App\Services\Storefront\StorefrontTheme::BACKGROUNDS,
    ];
@endphp

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&family=Playfair+Display:wght@700&family=Space+Grotesk:wght@600&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="tpl-page tpl-customize" x-data="templateCustomizer(@js($customizerConfig))">
    <form id="tpl-settings-form" method="POST" action="{{ route('dashboard.plantillas.update') }}" @input="changed()" @change="changed()" @submit="saving = true" novalidate>
        @csrf
        @method('PUT')

        {{-- Barra de acción fija arriba (no abajo): volver, estado y publicar --}}
        <div class="tpl-topbar is-sticky">
            <a href="{{ route('dashboard.plantillas.index') }}" class="btn-ghost tpl-topbar-back" aria-label="Volver a Plantillas" title="Volver a Plantillas"><x-dashboard-icon name="back"/></a>
            <div class="tpl-topbar-title">
                <p>Personalizando</p>
                <strong>{{ $template['name'] }}</strong>
            </div>
            <span class="tpl-dirty" x-show="dirty" x-cloak role="status"><i aria-hidden="true"></i> Sin publicar</span>
            <div class="tpl-topbar-actions">
                <button type="button" class="btn-secondary tpl-only-compact" @click="previewOpen = true"><x-dashboard-icon name="eye"/> <span class="tpl-hide-xs">Vista previa</span></button>
                <button type="submit" class="btn-primary" :disabled="saving">
                    <span x-show="!saving">Publicar <span class="tpl-hide-xs">cambios</span></span>
                    <span x-show="saving" x-cloak>Publicando…</span>
                </button>
            </div>
        </div>

        @if($errors->any())
            <div class="tpl-errors" role="alert">
                <p><x-dashboard-icon name="alert"/> Revisa estos campos antes de publicar:</p>
                <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="tpl-customize-grid">
            <div class="tpl-panel">
                @foreach($template['settings'] as $groupIndex => $group)
                    @php
                        $groupFields = collect($fields)->filter(fn ($field) => $field['group'] === $group['key']);
                        $groupHasError = $groupFields->keys()->contains(fn ($path) => $errors->has("settings.{$path}"));
                        // Consecutive "<x>.items.<n>.*" fields render together as one item card.
                        $rows = [];
                        foreach ($groupFields as $path => $field) {
                            if (preg_match('/\.items\.(\d+)\./', $path, $m)) {
                                $rows['item-' . $m[1]]['index'] = (int) $m[1];
                                $rows['item-' . $m[1]]['fields'][$path] = $field;
                            } else {
                                $rows[$path] = ['field' => $field, 'path' => $path];
                            }
                        }
                    @endphp
                    <details class="tpl-group" @if($groupIndex === 0 || $groupHasError) open @endif>
                        <summary>
                            <span class="tpl-group-icon"><x-dashboard-icon :name="$group['icon'] ?? 'grid'"/></span>
                            <span class="tpl-group-text"><strong>{{ $group['title'] }}</strong><small>{{ $group['description'] ?? '' }}</small></span>
                            <x-dashboard-icon name="chevron" class="tpl-group-chevron"/>
                        </summary>
                        <div class="tpl-group-body">
                            @if($group['key'] === 'brand')
                                @if($logoPalette)
                                    <div class="tpl-logo-colors">
                                        <img src="{{ $store->logo_url }}" alt="Logo de {{ $store->name }}">
                                        <div class="tpl-logo-colors-text">
                                            <strong>Colores de tu logo</strong>
                                            <p>Aplícalos con un clic y toda la tienda combinará con tu marca.</p>
                                            <span class="tpl-swatches is-lg" aria-hidden="true"><i style="background: {{ $logoPalette['primary'] }}"></i><i style="background: {{ $logoPalette['secondary'] }}"></i></span>
                                        </div>
                                        <button type="button" class="btn-secondary" @click="useColors(@js($logoPalette['primary']), @js($logoPalette['secondary']))"><x-dashboard-icon name="wand"/> Usar colores del logo</button>
                                    </div>
                                @elseif(!$store->logo_path)
                                    <div class="tpl-logo-colors is-empty">
                                        <x-dashboard-icon name="image"/>
                                        <p>Sube tu logo en <a href="{{ route('dashboard.store.edit') }}">Mi tienda</a> y te proponemos colores que combinen con tu marca.</p>
                                    </div>
                                @endif

                                <div class="tpl-field">
                                    <p class="tpl-label">Paletas sugeridas</p>
                                    <div class="tpl-presets" role="group" aria-label="Paletas sugeridas">
                                        @foreach($presets as [$presetName, $presetPrimary, $presetSecondary])
                                            <button type="button" class="tpl-preset" @click="useColors('{{ $presetPrimary }}', '{{ $presetSecondary }}')"
                                                    :class="{ 'is-active': color('colors.primary') === '{{ $presetPrimary }}' && color('colors.secondary') === '{{ $presetSecondary }}' }">
                                                <span class="tpl-preset-dots" aria-hidden="true"><i style="background: {{ $presetPrimary }}"></i><i style="background: {{ $presetSecondary }}"></i></span>
                                                <small>{{ $presetName }}</small>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($group['key'] === 'hero')
                                <a href="{{ route('dashboard.galeria.index') }}" class="tpl-inline-link"><x-dashboard-icon name="image"/> Elegir las fotos de la portada en Galería</a>
                            @endif

                            @foreach($rows as $row)
                                @if(isset($row['fields']))
                                    <div class="tpl-item">
                                        <p class="tpl-item-title">{{ $itemLabels[$group['key']] ?? 'Elemento' }} {{ $row['index'] + 1 }}</p>
                                        <div class="tpl-item-grid">
                                            @foreach($row['fields'] as $path => $field)
                                                @include('dashboard.templates._field', ['path' => $path, 'field' => $field, 'compact' => true])
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    @include('dashboard.templates._field', ['path' => $row['path'], 'field' => $row['field'], 'compact' => false])
                                @endif
                            @endforeach
                        </div>
                    </details>
                @endforeach

                <div class="tpl-reset">
                    <div>
                        <strong>¿Quieres empezar de cero?</strong>
                        <p>Vuelve a los colores y textos originales de {{ $template['name'] }}. Tus productos no se tocan.</p>
                    </div>
                    <button type="submit" form="tpl-reset-form" class="btn-ghost" @if(!$hasSaved) disabled @endif
                            onclick="return confirm('¿Restablecer el diseño original de la plantilla? Perderás los colores y textos personalizados.')">
                        <x-dashboard-icon name="refresh"/> Restablecer
                    </button>
                </div>
            </div>

            {{-- Vista previa en vivo: columna fija en escritorio, pantalla completa en celular --}}
            <aside class="tpl-live" :class="{ 'is-open': previewOpen }" aria-label="Vista previa en vivo">
                <div class="tpl-live-bar">
                    <strong><span class="tpl-live-dot" aria-hidden="true"></span> Vista previa en vivo</strong>
                    <div class="tpl-devices" role="group" aria-label="Tamaño de pantalla">
                        <button type="button" :class="{ 'is-active': device === 'desktop' }" :aria-pressed="device === 'desktop'" @click="device = 'desktop'" title="Computadora"><x-dashboard-icon name="desktop"/><span class="sr-only">Computadora</span></button>
                        <button type="button" :class="{ 'is-active': device === 'mobile' }" :aria-pressed="device === 'mobile'" @click="device = 'mobile'" title="Celular"><x-dashboard-icon name="phone"/><span class="sr-only">Celular</span></button>
                    </div>
                    <button type="button" class="btn-ghost tpl-only-compact" @click="previewOpen = false" aria-label="Cerrar vista previa"><x-dashboard-icon name="close"/></button>
                </div>
                <div class="tpl-stage" x-ref="stage">
                    <div class="tpl-device" :class="'is-' + device" :style="boxStyle">
                        <iframe x-ref="frame" src="{{ route('dashboard.plantillas.frame', $templateKey) }}" title="Vista previa en vivo de {{ $store->name }}" :style="frameStyle"></iframe>
                    </div>
                </div>
                <p class="tpl-live-note">Los cambios se ven aquí al instante. Tu tienda se actualiza cuando presionas <strong>Publicar</strong>.</p>
            </aside>
        </div>
    </form>

    {{-- Formulario hermano (nunca anidado): el botón "Restablecer" lo envía con form="tpl-reset-form". --}}
    <form id="tpl-reset-form" method="POST" action="{{ route('dashboard.plantillas.reset') }}" @submit="saving = true" hidden>
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@push('scripts')
@include('dashboard.templates._scripts')
@endpush
