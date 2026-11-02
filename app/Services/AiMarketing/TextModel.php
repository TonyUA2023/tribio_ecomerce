<?php

namespace App\Services\AiMarketing;

/** The language model behind copy generation (bound in AppServiceProvider; faked in tests). */
interface TextModel
{
    public function available(): bool;

    /**
     * One request that must answer with JSON matching $schema.
     *
     * @throws AiMarketingException when the provider cannot be reached
     */
    public function structured(string $system, string $prompt, array $schema, int $maxTokens = 4000): ModelResponse;
}
