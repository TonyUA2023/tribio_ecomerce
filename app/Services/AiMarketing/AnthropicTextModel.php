<?php

namespace App\Services\AiMarketing;

use Anthropic\Client;

/** Claude through the official Anthropic PHP SDK (config services.anthropic). */
class AnthropicTextModel implements TextModel
{
    public function available(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    public function structured(string $system, string $prompt, array $schema, int $maxTokens = 4000): ModelResponse
    {
        $model = config('services.anthropic.model', 'claude-opus-5-5');

        try {
            $client = new Client(
                apiKey: config('services.anthropic.api_key'),
                requestOptions: ['timeout' => 90, 'maxRetries' => 2],
            );

            // Beta namespace only for the server-side refusal fallback ("default" routing).
            $message = $client->beta->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: [
                    'effort' => config('services.anthropic.copy_effort', 'low'),
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (\Throwable $e) {
            report($e);
            throw new AiMarketingException('El asistente de IA no respondió. Inténtalo de nuevo en unos minutos.', previous: $e);
        }

        $data = null;
        if ($message->stopReason !== 'refusal') {
            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $data = json_decode($block->text, true);
                    break;
                }
            }
        }

        return new ModelResponse(
            data: is_array($data) ? $data : null,
            provider: 'anthropic',
            model: $message->model,
            inputTokens: (int) $message->usage->inputTokens,
            outputTokens: (int) $message->usage->outputTokens,
            cacheReadTokens: (int) ($message->usage->cacheReadInputTokens ?? 0),
            cacheWriteTokens: (int) ($message->usage->cacheCreationInputTokens ?? 0),
            stopReason: $message->stopReason,
        );
    }
}
