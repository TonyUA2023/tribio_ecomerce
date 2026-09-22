@extends('layouts.dashboard')
@section('title', 'Plantillas')
@section('page_title', 'Plantillas')

@section('content')
@php
    $storeUrlLabel = preg_replace('#^https?://#', '', $store->url);
    $categories = collect($available)->pluck('ideal_for')->flatten()->unique()->values();
@endphp
<div class="tpl-page" x-data="templateGallery({ applyUrl: @js(route('dashboard.plantillas.apply', '__TEMPLATE__')) })">

    @if($locked)
        {{-- Tiendas con diseño a medida: fuera del módulo, sin acciones --}}
        <section class="tpl-locked" aria-labelledby="tpl-locked-title">
            <span class="tpl-locked-icon"><x-dashboard-icon name="lock"/></span>
            <div>
                <p class="tpl-eyebrow">Diseño exclusivo protegido</p>
                <h2 id="tpl-locked-title">{{ $store->name }} tiene un diseño hecho a medida</h2>
                <p>Tu tienda fue construida especialmente para tu marca, así que está protegida: el catálogo de plantillas y sus cambios no la afectan. Si quieres ajustar algo de tu diseño, escríbenos y lo hacemos por ti.</p>
                <div class="tpl-actions">
                    <a href="{{ route('store.show', $store->slug) }}" target="_blank" rel="noopener" class="btn-primary"><x-dashboard-icon name="external"/> Ver mi tienda</a>
                    <a href="https://wa.me/{{ config('tribio.support.whatsapp_number') }}?text={{ urlencode('Hola, quiero un ajuste en el diseño de mi tienda ' . $store->name) }}" target="_blank" rel="noopener" class="btn-secondary">Pedir un ajuste</a>
                </div>
            </div>
        </section>
    @else

    {{-- 1. Diseño actual --}}
    <section class="tpl-current" aria-labelledby="tpl-current-title">
        <div class="tpl-current-copy">
            <p class="tpl-eyebrow"><span class="tpl-live-dot" aria-hidden="true"></span> Tu diseño actual</p>
            <h2 id="tpl-current-title">{{ $current['name'] ?? \Illuminate\Support\Str::headline($currentKey) }}</h2>
            <p class="tpl-current-tagline">{{ $current['tagline'] ?? 'Diseño de tu tienda' }}</p>
            @if(!$customizable)
                <p class="tpl-current-note">Este diseño aún no tiene opciones de personalización. Elige una plantilla disponible para cambiar colores, textos y portada tú mismo.</p>
            @else
                <p class="tpl-current-note">Cambia colores, tipografía, textos de portada y títulos, y mira el resultado en vivo antes de publicar.</p>
            @endif
            <div class="tpl-actions">
                @if($customizable)
                    <a href="{{ route('dashboard.plantillas.customize') }}" class="btn-primary"><x-dashboard-icon name="wand"/> Personalizar</a>
                @endif
                <a href="{{ route('store.show', $store->slug) }}" target="_blank" rel="noopener" class="btn-secondary"><x-dashboard-icon name="external"/> Ver mi tienda</a>
            </div>
            <ol class="tpl-steps" aria-label="Cómo funciona">
                <li><span>1</span> Elige un diseño</li>
                <li><span>2</span> Míralo con tus productos</li>
                <li><span>3</span> Personalízalo y publica</li>
            </ol>
        </div>
        <div class="tpl-current-stage">
            <div class="tpl-browser tpl-browser-lg">
                <div class="tpl-browser-bar" aria-hidden="true"><i></i><i></i><i></i><span>{{ $storeUrlLabel }}</span></div>
                <div class="tpl-thumb" data-tpl-thumb>
                    @if($canPreviewCurrent)
                        <iframe src="{{ route('dashboard.plantillas.frame', ['template' => $currentKey, 'thumb' => 1]) }}" title="Tu tienda con el diseño actual" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                    @else
                        @include('dashboard.templates._mockup', ['swatches' => $current['swatches'] ?? ['#2176d2', '#9ddcfa', '#ffffff', '#24364b']])
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- 2. Catálogo --}}
    <section class="tpl-section" aria-labelledby="tpl-catalog-title">
        <div class="tpl-toolbar">
            <div>
                <h2 id="tpl-catalog-title">Explora diseños</h2>
                <p>Cada vista previa usa tu nombre, tu logo y tus productos reales.</p>
            </div>
            @if(count($available) > 1 && $categories->count() > 1)
            <div class="tpl-chips" role="group" aria-label="Filtrar por tipo de negocio">
                <button type="button" class="tpl-chip" :class="{ 'is-active': filter === '' }" :aria-pressed="filter === ''" @click="filter = ''">Todas</button>
                @foreach($categories as $category)
                    <button type="button" class="tpl-chip" :class="{ 'is-active': filter === @js($category) }" :aria-pressed="filter === @js($category)" @click="filter = @js($category)">{{ $category }}</button>
                @endforeach
            </div>
            @endif
        </div>

        @if(empty($available))
            <div class="dash-empty"><x-dashboard-icon name="palette"/><h3>Muy pronto</h3><p>Estamos preparando las primeras plantillas.</p></div>
        @else
        <div class="tpl-grid {{ count($available) === 1 ? 'is-single' : '' }}">
            @foreach($available as $key => $template)
                @php $isCurrent = $key === $currentKey; @endphp
                <article class="tpl-card {{ $isCurrent ? 'is-current' : '' }}" x-show="!filter || @js($template['ideal_for'] ?? []).includes(filter)" x-transition.opacity>
                    <a href="{{ route('dashboard.plantillas.preview', $key) }}" class="tpl-card-media" aria-label="Vista previa de {{ $template['name'] }}">
                        <div class="tpl-browser">
                            <div class="tpl-browser-bar" aria-hidden="true"><i></i><i></i><i></i><span>{{ $storeUrlLabel }}</span></div>
                            <div class="tpl-thumb" data-tpl-thumb>
                                @if(!empty($template['thumbnail']))
                                    <img src="{{ asset($template['thumbnail']) }}" alt="" loading="lazy">
                                @else
                                    <iframe src="{{ route('dashboard.plantillas.frame', ['template' => $key, 'thumb' => 1]) }}" title="Miniatura de {{ $template['name'] }}" loading="lazy" tabindex="-1" aria-hidden="true"></iframe>
                                @endif
                            </div>
                        </div>
                        <span class="tpl-card-hover" aria-hidden="true"><x-dashboard-icon name="eye"/> Vista previa</span>
                    </a>
                    <div class="tpl-card-body">
                        <div class="tpl-card-head">
                            <h3>{{ $template['name'] }}</h3>
                            @if($isCurrent)<span class="badge badge-green">En uso</span>@else<span class="badge badge-blue">Disponible</span>@endif
                        </div>
                        <p class="tpl-card-tagline">{{ $template['tagline'] }}</p>
                        <p class="tpl-card-description">{{ $template['description'] }}</p>
                        <div class="tpl-card-meta">
                            <span class="tpl-swatches" aria-label="Colores base">
                                @foreach($template['swatches'] ?? [] as $swatch)<i style="background: {{ $swatch }}"></i>@endforeach
                            </span>
                            @if(!empty($template['ideal_for']))<span class="tpl-ideal">Ideal para {{ implode(' · ', $template['ideal_for']) }}</span>@endif
                        </div>
                        <div class="tpl-card-actions">
                            <a href="{{ route('dashboard.plantillas.preview', $key) }}" class="btn-secondary"><x-dashboard-icon name="eye"/> Vista previa</a>
                            @if($isCurrent)
                                @if(app(\App\Services\Storefront\TemplateRegistry::class)->isCustomizable($key))
                                    <a href="{{ route('dashboard.plantillas.customize') }}" class="btn-primary"><x-dashboard-icon name="wand"/> Personalizar</a>
                                @endif
                            @else
                                <button type="button" class="btn-primary" @click="ask(@js($key), @js($template['name']))">Usar plantilla</button>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        @endif
    </section>

    {{-- 3. Próximamente --}}
    @if(!empty($upcoming))
    <section class="tpl-section" aria-labelledby="tpl-upcoming-title">
        <div class="tpl-toolbar">
            <div>
                <h2 id="tpl-upcoming-title">Próximamente</h2>
                <p>Nuestro equipo está preparando estos diseños. Aparecerán arriba, listos para usar, en cuanto estén terminados.</p>
            </div>
        </div>
        <div class="tpl-grid tpl-grid-compact">
            @foreach($upcoming as $key => $template)
                <article class="tpl-card is-upcoming">
                    <div class="tpl-card-media">
                        <div class="tpl-browser">
                            <div class="tpl-browser-bar" aria-hidden="true"><i></i><i></i><i></i></div>
                            <div class="tpl-thumb">@include('dashboard.templates._mockup', ['swatches' => $template['swatches'] ?? []])</div>
                        </div>
                    </div>
                    <div class="tpl-card-body">
                        <div class="tpl-card-head">
                            <h3>{{ $template['name'] }}</h3>
                            @if($key === $currentKey)<span class="badge badge-green">Tu diseño actual</span>@else<span class="badge badge-gray">En desarrollo</span>@endif
                        </div>
                        <p class="tpl-card-tagline">{{ $template['tagline'] }}</p>
                        <p class="tpl-card-description">{{ $template['status_note'] ?? $template['description'] }}</p>
                        <div class="tpl-card-meta">
                            <span class="tpl-swatches" aria-label="Colores base">
                                @foreach($template['swatches'] ?? [] as $swatch)<i style="background: {{ $swatch }}"></i>@endforeach
                            </span>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
    @endif

    @include('dashboard.templates._apply-dialog')
    @endif
</div>
@endsection

@push('scripts')
@include('dashboard.templates._scripts')
@endpush
