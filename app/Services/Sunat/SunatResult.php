<?php

namespace App\Services\Sunat;

/** What SUNAT answered to one send. `outcome` is an ElectronicDocument status (accepted, observed, rejected or error). */
final class SunatResult
{
    public function __construct(
        public readonly string $outcome,
        public readonly ?string $code = null,
        public readonly ?string $message = null,
        public readonly ?string $cdrZip = null,
    ) {
    }

    public static function error(string $message, ?string $code = null): self
    {
        return new self(\App\Models\ElectronicDocument::ERROR, $code, $message);
    }
}
