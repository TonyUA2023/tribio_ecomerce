<?php

namespace App\Http\Requests\Dashboard;

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
        $rules = ['settings' => ['nullable', 'array']];

        foreach ($registry->fields($this->user()->currentStore()->template_name) as $path => $field) {
            $rules["settings.{$path}"] = match ($field['type']) {
                'color' => ['nullable', 'string', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
                'select', 'font' => ['nullable', 'string', Rule::in(array_keys($field['options']))],
                'toggle' => ['nullable', 'boolean'],
                'emoji' => ['nullable', 'string', 'max:16'],
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
            'settings.*.regex' => 'El :attribute debe ser un color válido (por ejemplo #7DA268).',
        ];
    }
}
