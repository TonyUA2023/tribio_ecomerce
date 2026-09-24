<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Storefront\StorefrontLinks;
use App\Services\Storefront\TemplateRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation is generated from the template's own settings schema (config/storefront.php),
 * so a new field declared there is validated the moment it exists — no rule to forget.
 * Nothing is required: every field has a default, and required inputs would also be
 * risky inside the customizer's collapsible groups (hidden required fields silently
 * block submission).
 */
class UpdateTemplateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $store = $this->user()?->currentStore();

        return $store !== null && app(TemplateRegistry::class)->canManage($store);
    }

    public function rules(): array
    {
        $registry = app(TemplateRegistry::class);
        $fields = $registry->fields($this->user()->currentStore()->template_name);
        $imagePaths = array_keys(array_filter($fields, fn (array $field) => $field['type'] === 'image'));
        $rules = [
            'settings' => ['nullable', 'array'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['string', Rule::in($imagePaths)],
        ];

        foreach ($fields as $path => $field) {
            $rules["settings.{$path}"] = match ($field['type']) {
                'color' => ['nullable', 'string', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
                'select', 'font' => ['nullable', 'string', Rule::in(array_keys($field['options']))],
                'toggle' => ['nullable', 'boolean'],
                'emoji' => ['nullable', 'string', 'max:16'],
                'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'link' => ['nullable', 'string', 'regex:' . StorefrontLinks::PATTERN],
                'date' => ['nullable', 'date_format:Y-m-d'],
                default => ['nullable', 'string', 'max:' . ($field['max'] ?? 255)],
            };
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (app(TemplateRegistry::class)->fields($this->user()->currentStore()->template_name) as $path => $field) {
            $attributes["settings.{$path}"] = mb_strtolower($field['label']);
        }

        return $attributes;
    }

    public function messages(): array
    {
        return [
            'settings.colors.*.regex' => 'El :attribute debe ser un color válido (por ejemplo #7DA268).',
            'settings.*.regex' => 'Elige una opción válida para :attribute.',
            'settings.*.mimes' => 'La :attribute debe ser una imagen JPG, PNG o WEBP.',
            'settings.*.uploaded' => 'La :attribute no se pudo subir. Prueba con una imagen de menos de 4 MB.',
            'settings.*.date_format' => 'Elige una fecha válida para :attribute.',
        ];
    }
}
