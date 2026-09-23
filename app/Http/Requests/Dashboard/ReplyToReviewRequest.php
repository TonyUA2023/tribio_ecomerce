<?php

namespace App\Http\Requests\Dashboard;

use App\Models\ProductReview;
use Illuminate\Foundation\Http\FormRequest;

class ReplyToReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the controller checks the review belongs to the owner's current store
    }

    public function rules(): array
    {
        return [
            'reply' => ['required', 'string', 'max:' . ProductReview::MAX_REPLY],
        ];
    }

    public function messages(): array
    {
        return [
            'reply.required' => 'Escribe tu respuesta antes de publicarla.',
            'reply.max'      => 'Tu respuesta puede tener como máximo :max caracteres.',
        ];
    }
}
