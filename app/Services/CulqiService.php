<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wraps the Culqi API (https://apidocs.culqi.com) for Tribio's own subscription
 * billing — charging store owners their monthly plan fee. Not related to
 * `stores.gateway_*`, which is a store's own checkout gateway for its customers.
 *
 * Flow: client-side Culqi Checkout tokenizes the card (token never touches our
 * server) -> createCustomer() -> createCard() -> getOrCreatePlan() -> createSubscription().
 * Culqi then bills the card automatically every cycle and notifies us via webhook.
 */
class CulqiService
{
    protected const BASE_URL = 'https://api.culqi.com/v2';

    public function isConfigured(): bool
    {
        return !empty(config('services.culqi.public_key')) && !empty(config('services.culqi.secret_key'));
    }

    public function publicKey(): ?string
    {
        return config('services.culqi.public_key');
    }

    protected function client()
    {
        return Http::withToken(config('services.culqi.secret_key'))
            ->acceptJson()
            ->timeout(20)
            ->baseUrl(self::BASE_URL);
    }

    public function createCustomer(array $data): array
    {
        $response = $this->client()->post('/customers', [
            'address'      => $data['address'],
            'address_city' => $data['address_city'],
            'country_code' => $data['country_code'] ?? 'PE',
            'email'        => $data['email'],
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'phone_number' => $data['phone_number'],
        ]);

        return $this->handle($response, 'crear cliente');
    }

    public function createCard(string $customerId, string $tokenId): array
    {
        $response = $this->client()->post('/cards', [
            'customer_id' => $customerId,
            'token_id'    => $tokenId,
        ]);

        return $this->handle($response, 'crear tarjeta');
    }

    /**
     * A new price needs a new Culqi plan. Include the amount in the cache key so
     * newly created subscriptions never reuse a plan with the previous price.
     */
    public function getOrCreatePlan(string $planKey): array
    {
        $planConfig = config("tribio.plans.{$planKey}");
        if (!$planConfig) {
            throw new \InvalidArgumentException("Plan Tribio desconocido: {$planKey}");
        }

        $amount = (int) round($planConfig['price'] * 100);
        $cacheKey = "culqi_plan_id_{$planKey}_{$amount}";
        $cachedId = Cache::get($cacheKey);

        if ($cachedId) {
            $existing = $this->client()->get("/plans/{$cachedId}");
            if ($existing->successful() && (int) $existing->json('amount') === $amount) {
                return $existing->json();
            }
            // Stale cache entry (plan deleted in CulqiPanel, key rotated to another Culqi
            // account, etc.) — fall through and recreate instead of failing the checkout.
            Cache::forget($cacheKey);
        }

        $response = $this->client()->post('/plans', [
            'name'               => Str::limit("Tribio - {$planConfig['label']}", 50, ''),
            'short_name'         => Str::limit("Tribio {$planConfig['label']}", 50, ''),
            'description'        => Str::limit("Suscripción mensual Tribio, plan {$planConfig['label']}.", 200, ''),
            'amount'             => $amount,
            'currency'           => 'PEN',
            'interval_unit_time' => 3, // Mensual
            'interval_count'     => 0, // 0 = cobro indefinido hasta cancelación
            'initial_cycles'     => [
                'count'              => 0,
                'has_initial_charge' => false,
                'amount'             => 0,
            ],
        ]);

        $plan = $this->handle($response, 'crear plan');
        Cache::forever($cacheKey, $plan['id']);

        return $plan;
    }

    public function createSubscription(string $cardId, string $planId): array
    {
        $response = $this->client()->post('/subscriptions', [
            'card_id' => $cardId,
            'plan_id' => $planId,
            'tyc'     => true,
        ]);

        return $this->handle($response, 'crear suscripción');
    }

    public function getSubscription(string $subscriptionId): ?array
    {
        $response = $this->client()->get("/subscriptions/{$subscriptionId}");

        return $response->successful() ? $response->json() : null;
    }

    protected function handle($response, string $action): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        $body = $response->json() ?? [];
        $message = $body['user_message'] ?? $body['merchant_message'] ?? $body['message'] ?? 'Ocurrió un error al procesar el pago con Culqi.';

        Log::error("Culqi: error al {$action}", [
            'status' => $response->status(),
            'body'   => $body,
        ]);

        throw new \RuntimeException($message, $response->status());
    }
}
