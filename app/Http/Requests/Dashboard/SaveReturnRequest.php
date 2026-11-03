<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Pos\PosSaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A return of a counter sale (ERP Fase 4c). */
class SaveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return [
            'items'                 => ['required', 'array', 'max:100'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity'      => ['nullable', 'integer', 'min:0', 'max:100000'],
            'items.*.restock'       => ['nullable', 'boolean'],
            'reason'                => ['required', 'string', 'max:255'],
            'method'                => ['nullable', Rule::in(array_keys(PosSaleService::METHODS))],
            'register_session_id'   => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Anota el motivo (por ejemplo: llegó vencido, talla equivocada).'];
    }
}
