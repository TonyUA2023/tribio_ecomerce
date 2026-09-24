{{-- One customizer field, rendered from its schema entry in config/storefront.php.
     Never `required`: groups are collapsible and every field falls back to a default. --}}
@php
    $name = 'settings[' . str_replace('.', '][', $path) . ']';
    $id = 'tpl-' . str_replace('.', '-', $path);
    $label = $compact ? preg_replace('/\s*\d+$/', '', $field['label']) : $field['label'];
    $default = $defaults[$path] ?? '';
    $error = $errors->first("settings.{$path}");
@endphp

@switch($field['type'])
    @case('color')
        <div class="tpl-field">
            <label class="tpl-label" for="{{ $id }}">{{ $label }}</label>
            <div class="tpl-color">
                <input type="color" :value="color(@js($path)).toLowerCase()" @input="v[@js($path)] = $event.target.value.toUpperCase()" aria-label="Elegir {{ mb_strtolower($label) }}">
                <input id="{{ $id }}" type="text" name="{{ $name }}" x-model="v[@js($path)]" class="input-field" maxlength="7" spellcheck="false" autocomplete="off" placeholder="{{ $default }}" @if($error) aria-invalid="true" @endif>
            </div>
            @if($path === 'colors.primary')
                <p class="tpl-help" x-show="primaryIsLight" x-cloak>Es un color claro: los botones usarán texto oscuro para que se lean bien.</p>
            @endif
            @if(!empty($field['help']))<p class="tpl-help">{{ $field['help'] }}</p>@endif
            @if($error)<p class="tpl-error">{{ $error }}</p>@endif
        </div>
        @break

    @case('select')
        <fieldset class="tpl-field" @if($path === 'colors.background') :style="'--tpl-p:' + color('colors.primary')" @endif>
            <legend class="tpl-label">{{ $label }}</legend>
            <div class="tpl-options">
                @foreach($field['options'] as $optionValue => $optionLabel)
                    <label class="tpl-option" :class="{ 'is-active': v[@js($path)] === @js($optionValue) }">
                        <input type="radio" name="{{ $name }}" value="{{ $optionValue }}" x-model="v[@js($path)]" class="sr-only">
                        @if($path === 'colors.background')
                            <span class="tpl-option-swatch is-{{ $optionValue }}" @if(isset(\App\Services\Storefront\StorefrontTheme::BACKGROUNDS[$optionValue])) style="background: {{ \App\Services\Storefront\StorefrontTheme::BACKGROUNDS[$optionValue] }}" @endif aria-hidden="true"></span>
                        @endif
                        <span>{{ $optionLabel }}</span>
                    </label>
                @endforeach
            </div>
            @if($error)<p class="tpl-error">{{ $error }}</p>@endif
        </fieldset>
        @break

    @case('font')
        <fieldset class="tpl-field">
            <legend class="tpl-label">{{ $label }}</legend>
            <div class="tpl-fonts">
                @foreach($field['options'] as $fontKey => $fontLabel)
                    @php [$fontMood, $fontName] = array_pad(explode(' · ', $fontLabel, 2), 2, ''); @endphp
                    <label class="tpl-font" :class="{ 'is-active': v[@js($path)] === @js($fontKey) }">
                        <input type="radio" name="{{ $name }}" value="{{ $fontKey }}" x-model="v[@js($path)]" class="sr-only">
                        <span class="tpl-font-sample" style="font-family: {{ $fonts[$fontKey]['family'] ?? 'inherit' }}" aria-hidden="true">Aa</span>
                        <span class="tpl-font-text"><strong>{{ $fontMood }}</strong><small>{{ $fontName }}</small></span>
                    </label>
                @endforeach
            </div>
            @if($error)<p class="tpl-error">{{ $error }}</p>@endif
        </fieldset>
        @break

    @case('toggle')
        <div class="tpl-field">
            <input type="hidden" name="{{ $name }}" value="0">
            <label class="tpl-switch" for="{{ $id }}">
                <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" x-model="v[@js($path)]">
                <span class="tpl-switch-track" aria-hidden="true"></span>
                <span>{{ $label }}</span>
            </label>
        </div>
        @break

    @case('image')
        <div class="tpl-field tpl-field-image">
            <span class="tpl-label" id="{{ $id }}-label">{{ $label }}</span>
            <div class="tpl-image is-{{ $field['aspect'] ?? 'wide' }}" :class="{ 'has-image': imageUrl(@js($path)) }">
                <img x-show="imageUrl(@js($path))" :src="imageUrl(@js($path))" alt="" x-cloak>
                <span class="tpl-image-empty" x-show="!imageUrl(@js($path))"><x-dashboard-icon name="image"/> Sin imagen</span>
            </div>
            <div class="tpl-image-actions">
                <label class="btn-secondary tpl-image-pick">
                    <input id="{{ $id }}" type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp" class="sr-only" aria-labelledby="{{ $id }}-label" @change="pickImage(@js($path), $event)" @if($error) aria-invalid="true" @endif>
                    <x-dashboard-icon name="plus"/> <span x-text="imageUrl(@js($path)) ? 'Cambiar' : 'Subir imagen'">Subir imagen</span>
                </label>
                <button type="button" class="btn-ghost" x-show="imageUrl(@js($path))" x-cloak @click="removeImage(@js($path), $event)">Quitar</button>
                <template x-if="removed[@js($path)]"><input type="hidden" name="remove_images[]" value="{{ $path }}"></template>
            </div>
            @if(!empty($field['help']))<p class="tpl-help">{{ $field['help'] }}</p>@endif
            @if($error)<p class="tpl-error">{{ $error }}</p>@endif
        </div>
        @break

    @case('link')
        <div class="tpl-field">
            <label class="tpl-label" for="{{ $id }}">{{ $label }}</label>
            <select id="{{ $id }}" name="{{ $name }}" x-model="v[@js($path)]" class="input-field" @if($error) aria-invalid="true" @endif>
                @foreach($linkOptions ?? [] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                @endforeach
            </select>
            @if(!empty($field['help']))<p class="tpl-help">{{ $field['help'] }}</p>@endif
            @if($error)<p class="tpl-error">{{ $error }}</p>@endif
        </div>
        @break

    @case('date')
        <div class="tpl-field">
            <label class="tpl-label" for="{{ $id }}">{{ $label }}</label>
            <div class="tpl-date">
                <input id="{{ $id }}" type="date" name="{{ $name }}" x-model="v[@js($path)]" class="input-field" @if($error) aria-invalid="true" @endif>
                <button type="button" class="btn-ghost" x-show="v[@js($path)]" x-cloak @click="v[@js($path)] = ''; changed()">Quitar fecha</button>
            </div>
            @if(!empty($field['help']))<p class="tpl-help">{{ $field['help'] }}</p>@endif
            @if($error)<p class="tpl-error">{{ $error }}</p>@endif
        </div>
        @break

    @case('emoji')
        <div class="tpl-field tpl-field-emoji">
            <label class="tpl-label" for="{{ $id }}">{{ $label }}</label>
            <input id="{{ $id }}" type="text" name="{{ $name }}" x-model="v[@js($path)]" class="input-field tpl-emoji" maxlength="8" placeholder="{{ $default }}" autocomplete="off" @if($error) aria-invalid="true" @endif>
            @if($error)<p class="tpl-error">{{ $error }}</p>@endif
        </div>
        @break

    @default
        @php $max = $field['max'] ?? 255; @endphp
        <div class="tpl-field">
            <div class="tpl-label-row">
                <label class="tpl-label" for="{{ $id }}">{{ $label }}</label>
                <span class="tpl-count" x-text="count(@js($path)) + ' / {{ $max }}'" aria-hidden="true"></span>
            </div>
            @if($field['type'] === 'textarea')
                <textarea id="{{ $id }}" name="{{ $name }}" x-model="v[@js($path)]" class="input-field" rows="2" maxlength="{{ $max }}" placeholder="{{ $default ?: 'Opcional' }}" @if($error) aria-invalid="true" @endif></textarea>
            @else
                <input id="{{ $id }}" type="text" name="{{ $name }}" x-model="v[@js($path)]" class="input-field" maxlength="{{ $max }}" placeholder="{{ $default ?: 'Opcional' }}" @if($error) aria-invalid="true" @endif>
            @endif
            @if(!empty($field['help']))<p class="tpl-help">{{ $field['help'] }}</p>@endif
            @if($error)<p class="tpl-error">{{ $error }}</p>@endif
        </div>
@endswitch
