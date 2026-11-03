<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SavePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return [
            'rows'     => ['required', 'array', 'max:200'],
            'rows.*'   => ['array'],
            'rows.*.*' => ['nullable', 'numeric', 'min:0.01', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return ['rows.*.*.min' => 'Los precios deben ser mayores que cero.', 'rows.*.*.numeric' => 'Escribe los precios solo con números.'];
    }
}
