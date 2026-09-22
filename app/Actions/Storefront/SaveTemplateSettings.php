<?php

namespace App\Actions\Storefront;

use App\Models\Store;
use App\Services\Storefront\ColorScale;
use App\Services\Storefront\TemplateRegistry;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Persists the owner's customization of one template. Input is expected to be already
 * validated by UpdateTemplateSettingsRequest; this only normalizes each value to its
 * field type and stores it under stores.template_settings[{template}], leaving the other
 * templates' saved settings alone.
 */
class SaveTemplateSettings
{
    public function __construct(private readonly TemplateRegistry $registry)
    {
    }

    /** @param array $input nested, as submitted: ['colors' => ['primary' => '#..'], ...] */
    public function handle(Store $store, string $templateKey, array $input): Store
    {
        $this->authorize($store, $templateKey);

        $settings = [];
        $columns = [];
        foreach ($this->registry->fields($templateKey) as $path => $field) {
            $value = $this->normalize($field, data_get($input, $path));
            if (!empty($field['column'])) {
                if ($value !== null) {
                    $columns[$field['column']] = $value;
                }
                continue;
            }
            if ($value !== null) {
                data_set($settings, $path, $value);
            }
        }

        $all = $store->template_settings ?? [];
        $all[$templateKey] = $settings;
        $store->forceFill(['template_settings' => $all] + $columns)->save();

        return $store;
    }

    /** Back to the template's defaults. Legacy columns (hero_title…) are not touched. */
    public function reset(Store $store, string $templateKey): Store
    {
        $this->authorize($store, $templateKey);

        $all = $store->template_settings ?? [];
        unset($all[$templateKey]);
        $store->forceFill(['template_settings' => $all ?: null])->save();

        return $store;
    }

    private function authorize(Store $store, string $templateKey): void
    {
        if (!$this->registry->canManage($store) || $store->template_name !== $templateKey || !$this->registry->isCustomizable($templateKey)) {
            throw new AuthorizationException('No puedes personalizar esta plantilla.');
        }
    }

    /** null = "not customized" (the theme falls back to the default). */
    private function normalize(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'toggle' => $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'color' => ColorScale::normalize(is_string($value) ? $value : null),
            'select', 'font' => is_string($value) && array_key_exists($value, $field['options']) ? $value : null,
            default => $this->normalizeText($field, $value),
        };
    }

    private function normalizeText(array $field, mixed $value): ?string
    {
        $text = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value)) : '';

        if ($text === '') {
            // Optional copy keeps an explicit blank ("hide it"); required copy goes back to its default.
            return ($field['default'] ?? null) === '' && $value !== null ? '' : null;
        }

        return $text;
    }
}
