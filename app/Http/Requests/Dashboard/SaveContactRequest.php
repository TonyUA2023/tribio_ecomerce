<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ContactService saves into the owner's current store and checks duplicates
    }

    protected function prepareForValidation(): void
    {
        // "vip, mayorista" → ['vip', 'mayorista']
        $tags = collect(preg_split('/[,\n]/', (string) $this->input('tags_text')))
            ->map(fn ($tag) => mb_strtolower(trim($tag)))->filter()->unique()->values()->all();

        $this->merge(['tags' => $tags, 'accepts_marketing' => $this->boolean('accepts_marketing')]);
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:120'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:150'],
            'document_type'     => ['nullable', Rule::in(['dni', 'ruc', 'ce', 'pasaporte'])],
            'document_number'   => ['nullable', 'string', 'max:20'],
            'city'              => ['nullable', 'string', 'max:100'],
            'birthday'          => ['nullable', 'date', 'before:today'],
            'relationship'      => ['nullable', Rule::in(array_keys(Contact::RELATIONSHIPS))],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'tags'              => ['array', 'max:15'],
            'tags.*'            => ['string', 'max:30'],
            'accepts_marketing' => ['boolean'],
            'first_note'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Escribe el nombre del cliente.',
            'email.email'     => 'El correo no es válido.',
            'birthday.before' => 'El cumpleaños debe ser una fecha pasada.',
            'tags.max'        => 'Puedes poner hasta 15 etiquetas.',
        ];
    }
}
