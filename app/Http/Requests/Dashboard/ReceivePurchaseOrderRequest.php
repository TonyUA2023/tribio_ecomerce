<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller checks the order belongs to the owner's current store
    }

    public function rules(): array
    {
        return [
            'lines'              => ['required', 'array'],
            'lines.*.quantity'   => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.lot_code'   => ['nullable', 'string', 'max:40'],
            'lines.*.expires_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.quantity.required' => 'Indica cuánto llegó de cada producto (0 si no llegó).',
            'lines.*.expires_at.date'   => 'Una fecha de vencimiento no es válida.',
        ];
    }
}
