<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SaveProductUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller checks the product belongs to the current store
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:40'],
            'factor'  => ['required', 'integer', 'min:2', 'max:100000'],
            'barcode' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Escribe el nombre de la presentación (caja, saco, docena…).',
            'factor.required' => 'Indica cuántas unidades trae.',
            'factor.min'      => 'Una presentación trae al menos 2 unidades.',
        ];
    }
}
