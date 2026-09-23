<?php

namespace App\Http\Requests\Reviews;

use App\Helpers\TranslationHelper;
use App\Models\ProductReview;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape of a review submission, shared by the storefront form, the web hub and the
 * mobile API. Whether the buyer may review is NOT decided here (see ProductReviewService).
 */
abstract class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating'  => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:' . ProductReview::MAX_COMMENT],
        ];
    }

    public function messages(): array
    {
        $en = TranslationHelper::isEn();

        return [
            'rating.required' => $en ? 'Choose a rating from 1 to 5 stars.' : 'Elige una calificación de 1 a 5 estrellas.',
            'rating.integer'  => $en ? 'Choose a rating from 1 to 5 stars.' : 'Elige una calificación de 1 a 5 estrellas.',
            'rating.between'  => $en ? 'The rating must be between 1 and 5 stars.' : 'La calificación debe estar entre 1 y 5 estrellas.',
            'comment.max'     => $en ? 'Your comment can be at most :max characters.' : 'Tu comentario puede tener como máximo :max caracteres.',
        ];
    }
}
