<?php

namespace App\Http\Requests\Reviews;

/** JSON review submission from the Tribio Pass hub (session) and the mobile app (token). */
class CustomerReviewRequest extends ReviewRequest
{
    public function rules(): array
    {
        return ['product_id' => ['required', 'integer']] + parent::rules();
    }
}
