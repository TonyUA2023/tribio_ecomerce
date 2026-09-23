<?php

namespace App\Services\Reviews;

use DomainException;

/** The buyer is not allowed to review this product (message is safe to show as-is). */
class ReviewNotAllowedException extends DomainException
{
    public function __construct(string $message, public readonly string $state)
    {
        parent::__construct($message);
    }
}
