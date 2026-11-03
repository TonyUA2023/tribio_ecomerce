<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWhatsappConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        $storeId = $this->user()->currentStore()?->id;

        return [
            'waba_id'         => ['required', 'regex:/^\d{6,32}$/'],
            'phone_number_id' => ['required', 'regex:/^\d{6,32}$/',
                Rule::unique('whatsapp_connections', 'phone_number_id')->where(fn ($q) => $q->where('store_id', '!=', $storeId))],
            // Empty on an edit keeps the saved secret (ConnectionService asks for it when missing).
            'access_token'    => ['nullable', 'string', 'min:20', 'max:1024'],
            'app_secret'      => ['nullable', 'string', 'regex:/^[a-f0-9]{32}$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'waba_id.required'         => 'Pega el identificador de la cuenta de WhatsApp Business (WABA).',
            'waba_id.regex'            => 'El identificador de la cuenta son solo números.',
            'phone_number_id.required' => 'Pega el identificador del número de teléfono.',
            'phone_number_id.regex'    => 'El identificador del número son solo números (no es el celular).',
            'phone_number_id.unique'   => 'Ese número ya está conectado a otra tienda.',
            'access_token.min'         => 'El token parece incompleto.',
            'app_secret.regex'         => 'La clave secreta de la app tiene 32 letras y números.',
        ];
    }
}
