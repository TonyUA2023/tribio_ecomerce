<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Google-linked accounts have no usable password to confirm with.
        return $this->user() !== null && !$this->user()->google_id;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.min'                      => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'                => 'La confirmación no coincide con la nueva contraseña.',
        ];
    }
}
