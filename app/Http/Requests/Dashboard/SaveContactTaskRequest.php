<?php

namespace App\Http\Requests\Dashboard;

use App\Models\ContactTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContactTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller scopes the contact to the current store
    }

    public function rules(): array
    {
        return [
            'title'      => ['required', 'string', 'max:160'],
            'due_at'     => ['required', 'date'],
            'kind'       => ['nullable', Rule::in(array_keys(ContactTask::KINDS))],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('store_id', $this->user()->currentStore()?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'    => '¿Qué hay que hacer? Escríbelo.',
            'due_at.required'   => 'Elige para cuándo es.',
            'contact_id.exists' => 'Ese cliente no es de tu tienda.',
        ];
    }
}
