<?php

namespace App\Services\Marketing\Meta;

class ConversionsApiException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message, $status);
    }

    /**
     * A 4xx answer (bad token, wrong Pixel ID, malformed event) fails the same way on
     * every retry; only 429 and 5xx are worth trying again.
     */
    public function isPermanent(): bool
    {
        return $this->status >= 400 && $this->status < 500 && $this->status !== 429;
    }
}
