<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return [
            'type'   => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'reason' => ['required', 'string', 'max:160'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Escribe el monto.',
            'amount.min'      => 'El monto debe ser mayor que cero.',
            'reason.required' => 'Anota el motivo (por ejemplo: pago al proveedor de gas).',
        ];
    }
}
