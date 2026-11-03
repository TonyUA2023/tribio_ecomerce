<?php

namespace App\Http\Requests\Dashboard;

use App\Models\ContactEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller looks the contact up inside the current store
    }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::in(ContactEvent::MANUAL_TYPES)],
            'body'        => ['required', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required'               => 'Escribe qué pasó.',
            'occurred_at.before_or_equal' => 'La fecha no puede ser futura: para algo pendiente crea un seguimiento.',
        ];
    }
}
