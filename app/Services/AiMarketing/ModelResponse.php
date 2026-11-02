<?php

namespace App\Services\AiMarketing;

/** Provider-neutral result of one structured-output call. */
final class ModelResponse
{
    public function __construct(
        public readonly ?array $data,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheReadTokens = 0,
        public readonly int $cacheWriteTokens = 0,
        public readonly ?string $stopReason = null,
    ) {
    }

    public function refused(): bool
    {
        return $this->stopReason === 'refusal';
    }
}
