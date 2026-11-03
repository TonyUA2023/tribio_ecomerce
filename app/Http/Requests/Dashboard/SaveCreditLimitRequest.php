<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class SaveCreditLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the dashboard middleware already limits this to store owners
    }

    public function rules(): array
    {
        return ['credit_limit' => ['nullable', 'numeric', 'min:0', 'max:1000000']];
    }
}
