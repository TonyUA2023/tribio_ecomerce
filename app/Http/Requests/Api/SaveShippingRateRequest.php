<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\Geo\GeoCatalog;

class SaveShippingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->currentStore();
    }

    protected function prepareForValidation(): void
    {
        $country = strtoupper(trim((string) $this->input('country_code')));
        $this->merge([
            'country_code' => $country,
            'state'        => $country === 'ALL' ? null : app(GeoCatalog::class)->canonical($country, $this->input('state')),
        ]);
    }

    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', function ($attr, $value, $fail) {
                if ($value !== 'ALL' && !app(GeoCatalog::class)->isValidCountry($value)) {
                    $fail('Ese país no existe.');
                }
            }],
            'state'        => ['nullable', 'string', 'max:100', function ($attr, $value, $fail) {
                if ($value && !app(GeoCatalog::class)->isValidState($this->input('country_code'), $value)) {
                    $fail('Ese departamento/estado no existe para el país elegido.');
                }
            }],
            'cost'         => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
