<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller scopes the contact and the deal to the current store
    }

    public function rules(): array
    {
        return [
            'contact_id'        => [$this->isMethod('post') ? 'required' : 'prohibited', 'integer',
                Rule::exists('contacts', 'id')->where('store_id', $this->user()->currentStore()?->id)],
            'title'             => ['required', 'string', 'max:120'],
            'amount'            => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'expected_close_at' => ['nullable', 'date'],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.required' => 'Elige el cliente de la oportunidad.',
            'contact_id.exists'   => 'Ese cliente no es de tu tienda.',
            'title.required'      => 'Describe la oportunidad en pocas palabras.',
            'amount.numeric'      => 'El monto debe ser un número.',
        ];
    }
}
