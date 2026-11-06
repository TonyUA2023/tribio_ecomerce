<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A counter sale posted by the register screen (JSON). */
class SavePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return [
            'uuid'                => ['required', 'uuid'],
            'items'               => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id'  => ['required', 'integer'],
            'items.*.variant_id'  => ['nullable', 'integer'],
            'items.*.unit_id'     => ['nullable', 'integer'],
            'items.*.quantity'    => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.price'       => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'discount'            => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'contact_id'          => ['nullable', 'integer'],
            'customer_name'       => ['nullable', 'string', 'max:120'],
            'customer_phone'      => ['nullable', 'string', 'max:20'],
            'customer_document'   => ['nullable', 'regex:/^(\d{8}|\d{11})$/'],
            'customer_address'    => ['nullable', 'string', 'max:255'],
            'document_type'       => ['nullable', Rule::in(array_keys(\App\Services\Pos\SalesDocuments::TYPES))],
            'send_sunat'          => ['nullable', 'boolean'],
            'payments'            => ['nullable', 'array', 'max:5'],
            'payments.*.method'   => ['required', Rule::in(array_keys(PosSaleService::METHODS))],
            'payments.*.amount'   => ['required', 'numeric', 'min:0', 'max:1000000'],
            'notes'               => ['nullable', 'string', 'max:255'],
            'price_list'          => ['nullable', Rule::in(['menor', 'mayorista', 'distribuidor'])],
            'quote_id'            => ['nullable', 'integer'],
            'approver_pin'        => ['nullable', 'digits_between:4,6'],
            'offline_at'          => ['nullable', 'date'],
            'credit'              => ['nullable', 'array'],
            'credit.installments' => ['nullable', 'integer', 'min:1', 'max:' . \App\Services\Pos\ReceivableService::MAX_INSTALLMENTS],
            'credit.first_due'    => ['nullable', 'date_format:Y-m-d'],
            'credit.every_days'   => ['nullable', Rule::in(array_keys(\App\Services\Pos\ReceivableService::FREQUENCIES))],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'           => 'Agrega al menos un producto.',
            'items.*.quantity.min'     => 'La cantidad debe ser al menos 1.',
            'customer_document.regex'  => 'El DNI tiene 8 dígitos y el RUC 11.',
            'payments.*.method.in'     => 'Elige cómo te pagan.',
            'document_type.in'         => 'Elige nota de venta, boleta o factura.',
        ];
    }
}
