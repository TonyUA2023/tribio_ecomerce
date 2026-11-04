<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Pos\SalesDocuments;
use Illuminate\Foundation\Http\FormRequest;

/** Caja → Comprobantes: the business's RUC and legal name, and the series of each document. */
class SaveSalesDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ruc'    => preg_replace('/\D+/', '', (string) $this->input('ruc')) ?: null,
            'series' => array_map(fn ($value) => strtoupper(trim((string) $value)), (array) $this->input('series')),
        ]);
    }

    public function rules(): array
    {
        return [
            'ruc'               => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (!SalesDocuments::validRuc((string) $value)) {
                    $fail('El RUC no es válido: tiene 11 dígitos y empieza con 10 o 20.');
                }
            }],
            'legal_name'        => ['nullable', 'string', 'max:160'],
            'name'              => ['sometimes', 'required', 'string', 'max:120'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'address'           => ['nullable', 'string', 'max:255'],
            'city'              => ['nullable', 'string', 'max:80'],
            'series'            => ['required', 'array'],
            'series.nota_venta' => ['required', 'regex:/^[A-Z0-9]{4}$/'],
            'series.boleta'     => ['required', 'regex:/^B[A-Z0-9]{3}$/'],
            'series.factura'    => ['required', 'regex:/^F[A-Z0-9]{3}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'El negocio necesita un nombre.',
            'series.nota_venta.regex' => 'La serie de la nota de venta tiene 4 letras o números (ej. NV01).',
            'series.boleta.regex'     => 'La serie de la boleta tiene 4 caracteres y empieza con B (ej. B001).',
            'series.factura.regex'    => 'La serie de la factura tiene 4 caracteres y empieza con F (ej. F001).',
        ];
    }
}
