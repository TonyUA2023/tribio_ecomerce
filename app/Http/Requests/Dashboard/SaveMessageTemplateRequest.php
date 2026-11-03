<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SaveMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // MessageTemplates scopes every template to the current store
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_promo' => $this->boolean('is_promo')]);
    }

    public function rules(): array
    {
        return [
            'name'     => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'max:60'],
            'body'     => ['required', 'string', 'max:1000'],
            'is_promo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre a la plantilla.',
            'body.required' => 'Escribe el mensaje.',
            'body.max'      => 'El mensaje puede tener hasta :max caracteres.',
        ];
    }
}
