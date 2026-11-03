<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/** Monthly cap and per-message rates (PEN) for WhatsApp broadcasts (Fase 3c). */
class SaveWhatsappBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return [
            'monthly_budget'  => ['required', 'numeric', 'min:1', 'max:100000'],
            'rates'           => ['nullable', 'array'],
            'rates.marketing' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'rates.utility'   => ['nullable', 'numeric', 'min:0', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'monthly_budget.required' => 'Escribe cuánto puedes gastar como máximo al mes en WhatsApp.',
            'monthly_budget.min'      => 'El tope mensual debe ser de al menos S/ 1.',
            'rates.*.max'             => 'Ese precio por mensaje parece muy alto: revisa tu factura de Meta.',
        ];
    }
}
