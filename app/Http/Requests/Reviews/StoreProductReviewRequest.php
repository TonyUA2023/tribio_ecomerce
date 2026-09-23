<?php

namespace App\Http\Requests\Reviews;

/** The review form on a storefront product page (plain HTML form → redirect back). */
class StoreProductReviewRequest extends ReviewRequest
{
    /** Kept apart from the store's other forms (contact, checkout) on the same page. */
    protected $errorBag = 'review';

    /** A failed submit lands back on the reviews section, not at the top of the page. */
    protected function getRedirectUrl(): string
    {
        return strtok(parent::getRedirectUrl(), '#') . '#resenas';
    }
}
