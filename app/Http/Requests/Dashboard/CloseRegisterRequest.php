<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class CloseRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['counted_cash.required' => 'Cuenta el efectivo de la caja y escribe el total.'];
    }
}
