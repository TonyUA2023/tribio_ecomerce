<?php

namespace App\Http\Requests\Api;

use App\Helpers\CurrencyHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveShippingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->currentStore();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country_code' => strtoupper(trim((string) $this->input('country_code')))]);
    }

    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', Rule::in(array_keys(CurrencyHelper::supportedCountries()))],
            'state'        => 'nullable|string|max:100',
            'cost'         => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return ['country_code.in' => 'Ese país todavía no está disponible para envíos.'];
    }
}
