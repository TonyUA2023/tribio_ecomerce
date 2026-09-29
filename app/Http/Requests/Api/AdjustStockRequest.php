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
        ];
    }
}
