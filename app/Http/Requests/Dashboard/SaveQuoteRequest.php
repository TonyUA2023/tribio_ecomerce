<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A quote ("proforma") saved from the register screen (JSON). */
class SaveQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.unit_id'    => ['nullable', 'integer'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.price'      => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'discount'           => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'price_list'         => ['nullable', Rule::in(['menor', 'mayorista', 'distribuidor'])],
            'contact_id'         => ['nullable', 'integer'],
            'customer_name'      => ['nullable', 'string', 'max:120'],
            'customer_phone'     => ['nullable', 'string', 'max:20'],
            'customer_document'  => ['nullable', 'regex:/^(\d{8}|\d{11})$/'],
            'valid_days'         => ['nullable', 'integer', 'min:1', 'max:90'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'          => 'Agrega al menos un producto.',
            'customer_document.regex' => 'El DNI tiene 8 dígitos y el RUC 11.',
        ];
    }
}
