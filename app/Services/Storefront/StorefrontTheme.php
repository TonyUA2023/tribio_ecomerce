<?php

namespace App\Services\Storefront;

use App\Helpers\TranslationHelper;
use App\Models\Store;

/**
 * The resolved look & copy of one store in one customizable template. Every value
 * follows the same chain: preview draft → saved setting → legacy store column
 * (e.g. stores.hero_title) → the template's default. Views only ever ask this object,
 * so they never need to know where a value came from.
 */
class StorefrontTheme
{
    public const BACKGROUNDS = [
        'cream' => '#FAF7F2',
        'white' => '#FFFFFF',
        'mist'  => '#F5F7FA',
    ];

    private array $fields;
    private array $saved;
    private ?array $palette = null;

    public function __construct(
        public readonly Store $store,
        public readonly string $templateKey,
        private readonly array $overrides = [],
        private readonly ?string $lang = null,
    ) {
        $registry = app(TemplateRegistry::class);
        $this->fields = $registry->fields($templateKey);
        $this->saved = $store->template_settings[$templateKey] ?? [];
    }

    public static function for(Store $store, ?string $templateKey = null, array $overrides = [], ?string $lang = null): self
    {
        return new self($store, $templateKey ?? $store->template_name, $overrides, $lang);
    }

    /** Raw effective value (untranslated default for bilingual fields resolves to the current language). */
    public function value(string $path): mixed
    {
        $field = $this->fields[$path] ?? null;

        foreach ([$this->overrides, $this->saved] as $source) {
            $candidate = data_get($source, $path);
            if ($this->isSet($candidate, $field)) {
                return $candidate;
            }
        }

        if (!empty($field['column']) && $this->isSet($this->store->{$field['column']}, $field)) {
            return $this->store->{$field['column']};
        }
        if (!empty($field['fallback']) && $this->isSet($this->store->{$field['fallback']}, $field)) {
            return $this->store->{$field['fallback']};
        }

        return $this->defaultFor($path);
    }

    public function defaultFor(string $path): mixed
    {
        $default = $this->fields[$path]['default'] ?? null;
        if (is_array($default)) {
            return $default[$this->language()] ?? $default['es'] ?? reset($default);
        }

        return $default;
    }

    public function text(string $path): string
    {
        return trim((string) $this->value($path));
    }

    public function enabled(string $path): bool
    {
        return filter_var($this->value($path), FILTER_VALIDATE_BOOLEAN);
    }

    /** Public URL of an uploaded `image` field, or null when the owner has not uploaded one. */
    public function image(string $path): ?string
    {
        $stored = $this->value($path);

        return is_string($stored) && $stored !== '' ? self::imageUrl($stored) : null;
    }

    public static function imageUrl(string $storedPath): string
    {
        return asset('storage/' . ltrim($storedPath, '/'));
    }

    /** Real URL of a `link` field (see StorefrontLinks), or null for "Sin enlace". */
    public function link(string $path): ?string
    {
        return StorefrontLinks::resolve($this->store, (string) $this->value($path));
    }

    /** A select/date value guaranteed to be one of the field's options (or the default). */
    public function choice(string $path): string
    {
        $field = $this->fields[$path] ?? [];
        $value = (string) $this->value($path);
        if (isset($field['options']) && !array_key_exists($value, $field['options'])) {
            return (string) $this->defaultFor($path);
        }

        return $value;
    }

    /** Whether the owner (or the preview draft) set this explicitly. */
    public function isCustomized(string $path): bool
    {
        $field = $this->fields[$path] ?? null;

        return $this->isSet(data_get($this->overrides, $path), $field) || $this->isSet(data_get($this->saved, $path), $field);
    }

    public function palette(): array
    {
        if ($this->palette !== null) {
            return $this->palette;
        }

        $primary = ColorScale::normalize((string) $this->value('colors.primary'))
            ?? ColorScale::normalize((string) $this->defaultFor('colors.primary')) ?? '#7DA268';
        $secondary = ColorScale::normalize((string) $this->value('colors.secondary'))
            ?? ColorScale::normalize((string) $this->defaultFor('colors.secondary')) ?? '#D4B48A';
        $backgroundKey = (string) $this->value('colors.background');
        $background = $backgroundKey === 'tint'
            ? ColorScale::tint($primary, .95)
            : (self::BACKGROUNDS[$backgroundKey] ?? self::BACKGROUNDS['cream']);

        return $this->palette = ColorScale::primary($primary) + ColorScale::secondary($secondary) + ['bg' => $background];
    }

    public function color(string $token): string
    {
        return $this->palette()[$token] ?? '#000000';
    }

    /** Declarations for a `:root {}` block. Every value is a validated hex or a whitelisted font stack. */
    public function cssVariables(): string
    {
        $css = '';
        foreach ($this->palette() as $token => $hex) {
            $css .= "--t-{$token}: {$hex}; ";
        }

        return $css . '--font-brand: ' . $this->headingFont()['family'] . ';';
    }

    public function headingFont(): array
    {
        $fonts = config('storefront.fonts', []);
        $key = (string) $this->value('typography.heading');
        if (!array_key_exists($key, $fonts)) {
            $key = array_key_exists((string) $this->defaultFor('typography.heading'), $fonts) ? (string) $this->defaultFor('typography.heading') : 'fredoka';
        }
        $font = $fonts[$key] ?? ['family' => 'sans-serif', 'google' => null];

        return [
            'key' => $key,
            'family' => $font['family'],
            'href' => $font['google'] ? 'https://fonts.googleapis.com/css2?family=' . $font['google'] . '&display=swap' : null,
        ];
    }

    /**
     * Form state for the dashboard customizer. Choice fields (colors, fonts, toggles)
     * show their effective value; copy fields show only what the owner actually wrote
     * (or inherited from a legacy column) and leave the default as the placeholder —
     * otherwise merely saving the form would freeze the Spanish default into the store
     * and English visitors would lose the translated default.
     */
    public function formValues(): array
    {
        $values = [];
        foreach ($this->fields as $path => $field) {
            if ($field['type'] === 'image') {
                $values[$path] = $this->image($path) ?? '';
            } elseif (in_array($field['type'], ['text', 'textarea', 'emoji'], true)) {
                // Optional copy shows its effective value, so clearing the box really means "hide it".
                $values[$path] = !empty($field['optional']) || $this->isCustomized($path) || $this->inheritsColumn($path) ? $this->text($path) : '';
            } else {
                $values[$path] = $this->value($path);
            }
        }

        return $values;
    }

    private function inheritsColumn(string $path): bool
    {
        $field = $this->fields[$path] ?? null;
        $column = $field['column'] ?? $field['fallback'] ?? null;

        return $column !== null && $this->isSet($this->store->{$column}, $field);
    }

    private function isSet(mixed $value, ?array $field): bool
    {
        if ($value === null) {
            return false;
        }
        if (($field['type'] ?? null) === 'toggle') {
            return true;
        }
        if (is_string($value) && trim($value) === '') {
            // Optional copy (default '' or 'optional' => true) treats an explicit empty
            // value as "hide it"; for everything else a cleared field means "go back to
            // the default". An image is never "set" to an empty path.
            return ($field['type'] ?? null) !== 'image'
                && (($field['default'] ?? null) === '' || !empty($field['optional']));
        }

        return true;
    }

    private function language(): string
    {
        return $this->lang ?? (TranslationHelper::isEn() ? 'en' : 'es');
    }
}
