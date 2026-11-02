<?php

namespace App\Http\Requests\Dashboard;

use App\Services\AiMarketing\FlyerTemplates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller looks the product up inside the owner's current store
    }

    public function rules(): array
    {
        return [
            'image'      => ['required', 'file', 'mimes:png', 'max:8192', 'dimensions:min_width=1000,max_width=1100,min_height=1000,max_height=2000'],
            'product_id' => ['nullable', 'integer'],
            'template'   => ['required', Rule::in(array_keys(FlyerTemplates::TEMPLATES))],
            'format'     => ['required', Rule::in(array_keys(FlyerTemplates::FORMATS))],
        ];
    }
}
