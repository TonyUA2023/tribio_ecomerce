<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->currentStore();
    }

    public function rules(): array
    {
        return [
            'type'     => 'required|in:in,out,adjustment,loss',
            'quantity' => 'required|integer|min:1',
            'reason'   => 'nullable|string|max:255',
            // Entry lot data (optional, Fase 1): cost per unit, supplier lot code, expiry.
            'unit_cost'  => 'nullable|numeric|min:0|max:9999999',
            'lot_code'   => 'nullable|string|max:40',
            'expires_at' => 'nullable|date',
        ];
    }
}
