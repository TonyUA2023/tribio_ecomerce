<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class StartStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // StockCountService checks the category belongs to the current store
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['blind' => $this->boolean('blind')]);
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer'],
            'blind'       => ['boolean'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
