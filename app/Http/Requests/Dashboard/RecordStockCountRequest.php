<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class RecordStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller looks the count up inside the current store
    }

    public function rules(): array
    {
        return [
            'item_id'     => ['required', 'integer'],
            'counted_qty' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'counted_qty.integer' => 'Escribe una cantidad entera.',
            'counted_qty.min'     => 'La cantidad no puede ser negativa.',
        ];
    }
}
