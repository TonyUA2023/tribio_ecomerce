@props(['name', 'label' => 'Imagen', 'current' => '', 'multiple' => false, 'maxMb' => 3, 'saveLabel' => 'Guardar cambios', 'removable' => false, 'wide' => false, 'extraFormats' => false])
@php $pickerId = 'image-' . Str::uuid(); $errorKey = str_replace('[]', '', $name); @endphp
<div class="image-picker {{ $wide ? 'image-picker-wide' : '' }}" data-image-picker data-max-mb="{{ $maxMb }}" data-save-label="{{ $saveLabel }}">
    <div class="image-picker-heading"><label for="{{ $pickerId }}">{{ $label }}</label><span data-image-badge>{{ $current ? 'Guardada' : 'Sin seleccionar' }}</span></div>
    <label for="{{ $pickerId }}" class="image-picker-stage" title="{{ $current ? 'Cambiar' : 'Seleccionar' }} {{ mb_strtolower($label) }}">
        <span data-image-original>
            @if($current)<img src="{{ $current }}" alt="{{ $label }} guardada" loading="lazy">@else<x-dashboard-icon name="image"/><span>{{ $multiple ? 'Selecciona las fotos que quieres agregar' : 'Selecciona una imagen' }}</span>@endif
        </span>
        <span data-image-preview hidden></span>
        <span class="image-picker-stage-action">{{ $current ? 'Cambiar imagen' : ($multiple ? 'Elegir fotos' : 'Elegir imagen') }}</span>
    </label>
    <input id="{{ $pickerId }}" class="image-picker-input" type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp{{ $extraFormats ? ',image/gif,image/bmp' : '' }}" @if($multiple) multiple @endif aria-describedby="{{ $pickerId }}-help {{ $pickerId }}-status" data-image-input>
    <p id="{{ $pickerId }}-help" class="image-picker-help">{{ $extraFormats ? 'JPG, PNG, WEBP, GIF o BMP' : 'JPG, PNG o WEBP' }} · Hasta {{ $maxMb }} MB por imagen. {{ $multiple ? 'Puedes seleccionar varias a la vez.' : 'Pulsa la imagen para cambiarla.' }}</p>
    @if($errors->any())<p class="image-picker-error">Los cambios no se guardaron. Si habías elegido fotos nuevas, vuelve a seleccionarlas antes de guardar.</p>@endif
    <p id="{{ $pickerId }}-status" class="image-picker-status" data-image-status role="status" aria-live="polite">{{ $current ? 'Esta es la imagen guardada actualmente.' : 'La vista previa aparecerá aquí.' }}</p>
    <p class="image-picker-error" data-image-error role="alert" hidden></p>
    @foreach($errors->get($errorKey . '*') as $messages) @foreach($messages as $message)<p class="image-picker-error" role="alert">{{ $message }}</p>@endforeach @endforeach
    <div class="image-picker-actions">
        <button type="button" data-image-choose class="btn-secondary">{{ $current ? 'Cambiar imagen' : ($multiple ? 'Seleccionar fotos' : 'Seleccionar imagen') }}</button>
        <button type="button" data-image-reset class="btn-ghost" hidden>Deshacer selección</button>
    </div>
    @if($removable && $current)
    <label class="image-picker-remove"><input type="checkbox" name="remove_image" value="1" data-image-remove> Quitar imagen guardada al guardar</label>
    @endif
    <button type="submit" class="btn-primary image-picker-save" data-image-save hidden>{{ $saveLabel }}</button>
</div>
