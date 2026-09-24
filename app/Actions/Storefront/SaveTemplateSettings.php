<?php

namespace App\Actions\Storefront;

use App\Models\Store;
use App\Services\Storefront\ColorScale;
use App\Services\Storefront\StorefrontLinks;
use App\Services\Storefront\TemplateRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

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

    /**
     * @param array $input nested, as submitted: ['colors' => ['primary' => '#..'], ...]
     * @param array $removeImages paths of `image` fields the owner asked to clear
     */
    public function handle(Store $store, string $templateKey, array $input, array $removeImages = []): Store
    {
        $this->authorize($store, $templateKey);

        $previous = $store->template_settings[$templateKey] ?? [];
        $settings = [];
        $columns = [];
        $replaced = [];
        foreach ($this->registry->fields($templateKey) as $path => $field) {
            if ($field['type'] === 'image') {
                // An image is kept unless a new file arrives or the owner removes it.
                $old = data_get($previous, $path);
                $old = is_string($old) && $old !== '' ? $old : null;
                $upload = data_get($input, $path);
                if ($upload instanceof UploadedFile && $upload->isValid()) {
                    data_set($settings, $path, $upload->store($this->imageDirectory($store, $templateKey), 'public'));
                    $replaced[] = $old;
                } elseif (in_array($path, $removeImages, true)) {
                    $replaced[] = $old;
                } elseif ($old !== null) {
                    data_set($settings, $path, $old);
                }
                continue;
            }

            $raw = data_get($input, $path);
            // ConvertEmptyStringsToNull turns a cleared box into null; a key that was
            // submitted is still an explicit "empty", unlike one that was never sent.
            if ($raw === null && Arr::has($input, $path) && in_array($field['type'], ['text', 'textarea', 'emoji', 'date'], true)) {
                $raw = '';
            }
            $value = $this->normalize($field, $raw);
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

        // Only once the new paths are safely persisted.
        $this->deleteImages($store, $templateKey, array_filter($replaced));

        return $store;
    }

    /** Back to the template's defaults. Legacy columns (hero_title…) are not touched. */
    public function reset(Store $store, string $templateKey): Store
    {
        $this->authorize($store, $templateKey);

        $all = $store->template_settings ?? [];
        $images = collect($this->registry->fields($templateKey))
            ->filter(fn (array $field) => $field['type'] === 'image')
            ->map(fn (array $field, string $path) => data_get($all[$templateKey] ?? [], $path))
            ->filter(fn ($stored) => is_string($stored) && $stored !== '')
            ->values()->all();
        unset($all[$templateKey]);
        $store->forceFill(['template_settings' => $all ?: null])->save();
        $this->deleteImages($store, $templateKey, $images);

        return $store;
    }

    private function imageDirectory(Store $store, string $templateKey): string
    {
        return "stores/{$store->id}/templates/{$templateKey}";
    }

    /** Deletes files this action uploaded — never anything outside the template's own folder. */
    private function deleteImages(Store $store, string $templateKey, array $paths): void
    {
        $prefix = $this->imageDirectory($store, $templateKey) . '/';
        foreach ($paths as $path) {
            if (is_string($path) && str_starts_with($path, $prefix) && !str_contains($path, '..')) {
                Storage::disk('public')->delete($path);
            }
        }
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
            'link' => StorefrontLinks::isValid($value) ? $value : null,
            'date' => $this->normalizeDate($value),
            default => $this->normalizeText($field, $value),
        };
    }

    private function normalizeText(array $field, mixed $value): ?string
    {
        $text = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value)) : '';

        if ($text === '') {
            // Optional copy keeps an explicit blank ("hide it"); required copy goes back to its default.
            $keepsBlank = ($field['default'] ?? null) === '' || !empty($field['optional']);

            return $keepsBlank && $value !== null ? '' : null;
        }

        return $text;
    }

    /** 'Y-m-d', or '' when explicitly cleared; anything unparseable counts as not customized. */
    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
