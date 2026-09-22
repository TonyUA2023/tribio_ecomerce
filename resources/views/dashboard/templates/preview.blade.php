@extends('layouts.dashboard')
@section('title', 'Vista previa')
@section('page_title', 'Vista previa · ' . $template['name'])

@section('content')
<div class="tpl-page tpl-preview" x-data="templatePreview({ applyUrl: @js(route('dashboard.plantillas.apply', '__TEMPLATE__')) })">
    <div class="tpl-topbar">
        <a href="{{ route('dashboard.plantillas.index') }}" class="btn-ghost tpl-topbar-back" aria-label="Volver a Plantillas" title="Volver a Plantillas"><x-dashboard-icon name="back"/></a>
        <div class="tpl-topbar-title">
            <p>Vista previa con tus productos</p>
            <strong>{{ $template['name'] }}</strong>
        </div>
        <div class="tpl-devices" role="group" aria-label="Tamaño de pantalla">
            @foreach(['desktop' => ['desktop', 'Computadora'], 'tablet' => ['tablet', 'Tablet'], 'mobile' => ['phone', 'Celular']] as $deviceKey => [$deviceIcon, $deviceLabel])
                <button type="button" :class="{ 'is-active': device === '{{ $deviceKey }}' }" :aria-pressed="device === '{{ $deviceKey }}'" @click="device = '{{ $deviceKey }}'" title="{{ $deviceLabel }}">
                    <x-dashboard-icon :name="$deviceIcon"/><span class="sr-only">{{ $deviceLabel }}</span>
                </button>
            @endforeach
        </div>
        <div class="tpl-topbar-actions">
            @if($isCurrent)
                <span class="badge badge-green">En uso</span>
                @if(app(\App\Services\Storefront\TemplateRegistry::class)->isCustomizable($templateKey))
                    <a href="{{ route('dashboard.plantillas.customize') }}" class="btn-primary"><x-dashboard-icon name="wand"/> <span class="tpl-hide-xs">Personalizar</span></a>
                @endif
            @elseif($selectable)
                <button type="button" class="btn-primary" @click="ask(@js($templateKey), @js($template['name']))">Usar <span class="tpl-hide-xs">esta plantilla</span></button>
            @endif
        </div>
    </div>

    <div class="tpl-preview-info">
        <p><x-dashboard-icon name="eye"/> Así se vería tu tienda. Es solo una vista previa: los enlaces y el carrito están desactivados.</p>
        @if(!empty($template['ideal_for']))<p class="tpl-ideal">Ideal para {{ implode(' · ', $template['ideal_for']) }}</p>@endif
    </div>

    <div class="tpl-stage" x-ref="stage">
        <div class="tpl-device" :class="'is-' + device" :style="boxStyle">
            <iframe src="{{ route('dashboard.plantillas.frame', $templateKey) }}" title="Vista previa de {{ $template['name'] }} con los datos de {{ $store->name }}" :style="frameStyle"></iframe>
        </div>
    </div>

    @include('dashboard.templates._apply-dialog')
</div>
@endsection

@push('scripts')
@include('dashboard.templates._scripts')
@endpush
