<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The owner's login e-mail (Tribio Pass). Needs the current password, like a password change. */
class UpdateAccountEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Google-linked accounts sign in with Google and have no usable password to confirm.
        return $this->user() !== null && !$this->user()->google_id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'email_current_password' => ['required', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Escribe tu nuevo correo.',
            'email.email' => 'Ese correo no es válido.',
            'email.unique' => 'Ese correo ya pertenece a otra cuenta.',
            'email_current_password.required' => 'Confirma el cambio con tu contraseña actual.',
            'email_current_password.current_password' => 'La contraseña actual no es correcta.',
        ];
    }
}
