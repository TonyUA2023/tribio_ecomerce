<?php

namespace App\Services\Billing;

use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Tribio's OWN plan billing through Mercado Pago Suscripciones (preapproval): the store
 * owner authorizes a monthly charge once on Mercado Pago's page and is billed every month
 * automatically. Credentials are the platform's (MP_BILLING_ACCESS_TOKEN), unrelated to
 * `stores.mp_*`, which is a store's own checkout gateway for charging its customers.
 */
class MercadoPagoSubscriptions
{
    protected const BASE_URL = 'https://api.mercadopago.com';

    public function isConfigured(): bool
    {
        return !empty(config('services.mp_billing.access_token'));
    }

    protected function client()
    {
        return Http::withToken(trim((string) config('services.mp_billing.access_token')))
            ->acceptJson()
            ->timeout(20)
            ->baseUrl(self::BASE_URL);
    }

    /** Creates a pending monthly subscription and returns Mercado Pago's authorization page. */
    public function create(Store $store, array $plan, string $payerEmail, string $backUrl): array
    {
        $response = $this->client()->post('/preapproval', [
            'reason'             => 'Tribio · Plan ' . $plan['label'],
            'external_reference' => 'store-' . $store->id,
            'payer_email'        => $payerEmail,
            'back_url'           => $backUrl,
            'status'             => 'pending',
            'auto_recurring'     => [
                'frequency'          => 1,
                'frequency_type'     => 'months',
                'transaction_amount' => round((float) $plan['price'], 2),
                'currency_id'        => 'PEN',
            ],
        ]);

        if (!$response->successful() || empty($response['id']) || empty($response['init_point'])) {
            throw new \RuntimeException('No se pudo iniciar la suscripción con Mercado Pago. Intenta nuevamente en unos minutos.');
        }

        return $response->json();
    }

    public function get(string $preapprovalId): ?array
    {
        $response = $this->client()->get('/preapproval/' . urlencode($preapprovalId));

        return $response->successful() ? $response->json() : null;
    }

    public function authorizedPayment(string $id): ?array
    {
        $response = $this->client()->get('/authorized_payments/' . urlencode($id));

        return $response->successful() ? $response->json() : null;
    }

    /** Aligns the store with Mercado Pago's authoritative subscription state. */
    public function sync(Store $store, array $preapproval): void
    {
        $status = $preapproval['status'] ?? null;

        if ($status === 'authorized') {
            $next = !empty($preapproval['next_payment_date']) ? Carbon::parse($preapproval['next_payment_date']) : null;
            $store->update([
                'status'          => 'active',
                'plan_expires_at' => $next && $next->isFuture() ? $next : now()->addMonth(),
            ]);
        } elseif ($status === 'cancelled') {
            $store->update(['status' => 'cancelled']);
        }
        // pending / paused: nothing to change yet.
    }
}
