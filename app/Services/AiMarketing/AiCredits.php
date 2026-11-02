<?php

namespace App\Services\AiMarketing;

use App\Models\AiUsageLog;
use App\Models\Store;

/** Monthly AI credits per plan (config tribio.ai) and the usage ledger behind them. */
class AiCredits
{
    public function allowance(Store $store): int
    {
        $credits = config('tribio.ai.monthly_credits', []);

        return (int) ($credits[$store->plan] ?? $credits['basic'] ?? 0);
    }

    public function used(Store $store): int
    {
        return (int) AiUsageLog::where('store_id', $store->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('credits');
    }

    public function remaining(Store $store): int
    {
        return max(0, $this->allowance($store) - $this->used($store));
    }

    public function cost(string $feature): int
    {
        return (int) config("tribio.ai.credit_cost.{$feature}", 1);
    }

    public function ensure(Store $store, string $feature): void
    {
        if ($this->remaining($store) < $this->cost($feature)) {
            throw new AiMarketingException('Usaste todos tus créditos de IA de este mes. Se renuevan el día 1, o mejora tu plan para tener más.');
        }
    }

    /** Only a successful call spends credits; failures are logged at 0 for the cost report. */
    public function record(Store $store, ?int $userId, string $feature, ModelResponse $response, string $status = 'ok', ?string $error = null): AiUsageLog
    {
        return AiUsageLog::create([
            'store_id'           => $store->id,
            'user_id'            => $userId,
            'feature'            => $feature,
            'provider'           => $response->provider,
            'model'              => $response->model,
            'input_tokens'       => $response->inputTokens,
            'output_tokens'      => $response->outputTokens,
            'cache_read_tokens'  => $response->cacheReadTokens,
            'cache_write_tokens' => $response->cacheWriteTokens,
            'cost_usd'           => $this->usd($response),
            'credits'            => $status === 'ok' ? $this->cost($feature) : 0,
            'status'             => $status,
            'error'              => $error ? mb_substr($error, 0, 250) : null,
        ]);
    }

    private function usd(ModelResponse $response): float
    {
        $price = config("services.anthropic.prices.{$response->model}");
        if (!$price) {
            return 0.0;
        }

        return ($response->inputTokens * $price['input']
            + $response->outputTokens * $price['output']
            + $response->cacheReadTokens * $price['cache_read']
            + $response->cacheWriteTokens * $price['cache_write']) / 1_000_000;
    }
}
