<?php

namespace App\Services\Whatsapp;

class CloudApiException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status, public readonly ?int $metaCode = null)
    {
        parent::__construct($message, $status);
    }

    /** 4xx answers fail the same way on every retry; only 429, 5xx and Meta's throttling are worth retrying. */
    public function isPermanent(): bool
    {
        if (in_array($this->metaCode, CloudApi::RETRYABLE_CODES, true)) {
            return false;
        }

        return $this->status >= 400 && $this->status < 500 && $this->status !== 429;
    }
}
