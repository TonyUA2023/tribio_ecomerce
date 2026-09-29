<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->currentStore();
    }

    public function rules(): array
    {
        $storeId = $this->user()->currentStore()->id;

        return [
            'name'           => 'required|string|max:100',
            // A parent must belong to this same store.
            'parent_id'      => ['nullable', Rule::exists('categories', 'id')->where('store_id', $storeId)],
            'icon'           => 'nullable|string|max:20',
            'color'          => 'nullable|string|max:7',
            'image'          => 'nullable|image|max:3072',
            'remove_image'   => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
            'show_in_header' => 'nullable|boolean',
            'is_featured'    => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre de la categoría.',
            'name.max'      => 'El nombre puede tener como máximo 100 caracteres.',
            'parent_id.exists' => 'La categoría padre no es válida.',
            'image.image'   => 'El archivo debe ser una imagen.',
            'image.max'     => 'La imagen no debe pasar de 3 MB.',
        ];
    }
}
