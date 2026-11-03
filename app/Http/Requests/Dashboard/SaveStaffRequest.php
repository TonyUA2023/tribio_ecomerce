<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Pos\StaffService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A cashier or manager of the register (ERP Fase 4d). The PIN is required only when adding one. */
class SaveStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        $creating = $this->routeIs('dashboard.pos.staff.store');

        return [
            'name'      => [$creating ? 'required' : 'sometimes', 'string', 'max:80'],
            'role'      => [$creating ? 'required' : 'sometimes', Rule::in(array_keys(StaffService::ROLES))],
            'pin'       => [$creating ? 'required' : 'nullable', 'digits_between:4,6', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'Escribe el nombre de la persona.',
            'pin.required'        => 'Elige un PIN de 4 a 6 números.',
            'pin.digits_between'  => 'El PIN tiene de 4 a 6 números.',
            'pin.confirmed'       => 'Los dos PIN no coinciden.',
        ];
    }
}
