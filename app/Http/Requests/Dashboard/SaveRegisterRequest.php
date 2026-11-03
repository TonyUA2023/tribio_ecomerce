<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SaveRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:60']];
    }

    public function messages(): array
    {
        return ['name.required' => 'Ponle un nombre a la caja (por ejemplo: Caja 2).'];
    }
}
