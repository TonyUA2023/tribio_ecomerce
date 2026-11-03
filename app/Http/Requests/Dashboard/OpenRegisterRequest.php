<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class OpenRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return ['opening_float' => ['required', 'numeric', 'min:0', 'max:100000']];
    }

    public function messages(): array
    {
        return ['opening_float.required' => 'Escribe con cuánto efectivo empiezas (0 si no hay sencillo).'];
    }
}
