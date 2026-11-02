<?php

namespace App\Http\Requests\Dashboard;

use App\Services\AiMarketing\CopyGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller looks the product up inside the owner's current store
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer'],
            'objective'  => ['required', Rule::in(array_keys(CopyGenerator::OBJECTIVES))],
            'channels'   => ['required', 'array', 'min:1', 'max:3'],
            'channels.*' => ['distinct', Rule::in(array_keys(CopyGenerator::CHANNELS))],
            'notes'      => ['nullable', 'string', 'max:400'],
        ];
    }

    public function messages(): array
    {
        return [
            'channels.required' => 'Elige al menos una red social.',
            'channels.min'      => 'Elige al menos una red social.',
            'channels.max'      => 'Elige como máximo 3 redes a la vez.',
            'notes.max'         => 'Las indicaciones pueden tener hasta :max caracteres.',
        ];
    }
}
