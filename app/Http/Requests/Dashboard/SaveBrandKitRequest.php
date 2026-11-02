<?php

namespace App\Http\Requests\Dashboard;

use App\Models\BrandKit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBrandKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller saves into the owner's current store
    }

    protected function prepareForValidation(): void
    {
        // "dieta milagro, cura" → ['dieta milagro', 'cura']
        $words = collect(preg_split('/[,\n]/', (string) $this->input('banned_words_text')))
            ->map(fn ($word) => trim($word))->filter()->unique()->values()->all();

        $this->merge(['banned_words' => $words, 'show_whatsapp' => $this->boolean('show_whatsapp')]);
    }

    public function rules(): array
    {
        return [
            'tone'            => ['required', Rule::in(array_keys(BrandKit::TONES))],
            'slogan'          => ['nullable', 'string', 'max:120'],
            'audience'        => ['nullable', 'string', 'max:200'],
            'banned_words'    => ['array', 'max:30'],
            'banned_words.*'  => ['string', 'max:60'],
            'primary_color'   => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'show_whatsapp'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'primary_color.regex'   => 'Elige un color principal válido.',
            'secondary_color.regex' => 'Elige un color secundario válido.',
            'banned_words.max'      => 'Puedes guardar hasta 30 palabras prohibidas.',
        ];
    }
}
