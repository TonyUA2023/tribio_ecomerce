<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SaveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller saves into the owner's current store
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:120'],
            'document_number' => ['nullable', 'string', 'max:20'],
            'contact_name'    => ['nullable', 'string', 'max:120'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:150'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del proveedor.',
            'email.email'   => 'El correo no es válido.',
        ];
    }
}
