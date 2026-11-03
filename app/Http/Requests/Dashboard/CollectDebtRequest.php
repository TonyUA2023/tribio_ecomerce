<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A payment of a customer's credit debt (ERP Fase 4b). */
class CollectDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return [
            'uuid'                => ['required', 'uuid'],
            'amount'              => ['required', 'numeric', 'min:0.10', 'max:1000000'],
            'method'              => ['required', Rule::in(array_keys(PosSaleService::METHODS))],
            'register_session_id' => ['nullable', 'integer'],
            'order_id'            => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Escribe cuánto te pagó.',
            'amount.min'      => 'El monto debe ser de al menos S/ 0.10.',
            'method.in'       => 'Elige cómo te pagó.',
        ];
    }
}
