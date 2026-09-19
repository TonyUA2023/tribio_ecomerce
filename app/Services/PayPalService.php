<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wraps PayPal's Orders v2 REST API for storefront checkout. Each store brings its
 * own PayPal app credentials (client id/secret, stored on Store), unlike the
 * platform-level Culqi integration in CulqiService.
 *
 * PayPal does not settle in PEN — every amount here is USD (see
 * Product::resolvePrice('USD') at the call site), confirmed against PayPal's
 * currency-codes reference before building this.
 *
 * Flow: frontend (PayPal JS SDK v6) calls our createOrder endpoint -> this service's
 * createOrder() -> customer approves in the PayPal popup -> frontend calls our
 * capture endpoint -> captureOrder(). Both are synchronous; the optional webhook is
 * only a reconciliation safety net, not required for the core flow to work.
 */
class PayPalService
{
    public function isConfigured(Store $store): bool
    {
        return !empty($store->paypal_client_id) && !empty($store->paypal_client_secret);
    }

    public function baseUrl(Store $store): string
    {
        return $store->paypal_mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * The web SDK script origin differs between sandbox and live too.
     */
    public function sdkOrigin(Store $store): string
    {
        return $store->paypal_mode === 'live'
            ? 'https://www.paypal.com'
            : 'https://www.sandbox.paypal.com';
    }

    protected function getAccessToken(Store $store): string
    {
        $cacheKey = "paypal_token_{$store->id}_{$store->paypal_mode}";

        return Cache::remember($cacheKey, 480, function () use ($store) {
            $response = Http::asForm()
                ->withBasicAuth($store->paypal_client_id, $store->paypal_client_secret)
                ->timeout(15)
                ->post($this->baseUrl($store) . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful()) {
                Log::error('PayPal: fallo al obtener access token', [
                    'store_id' => $store->id,
                    'status'   => $response->status(),
                    'body'     => $response->json(),
                ]);
                throw new \RuntimeException('No se pudo conectar con PayPal. Verifica las credenciales de la tienda.');
            }

            return $response->json('access_token');
        });
    }

    protected function client(Store $store)
    {
        return Http::withToken($this->getAccessToken($store))
            ->acceptJson()
            ->timeout(20)
            ->baseUrl($this->baseUrl($store));
    }

    /**
     * @param array<int, array{name: string, unit_amount: float, quantity: int}> $items
     */
    public function createOrder(Store $store, string $referenceId, array $items, float $shippingUsd = 0.0): array
    {
        $itemTotal = round(array_sum(array_map(fn ($i) => round($i['unit_amount'], 2) * $i['quantity'], $items)), 2);
        $shippingUsd = round($shippingUsd, 2);
        $total = round($itemTotal + $shippingUsd, 2);

        $breakdown = [
            'item_total' => ['currency_code' => 'USD', 'value' => number_format($itemTotal, 2, '.', '')],
        ];
        if ($shippingUsd > 0) {
            $breakdown['shipping'] = ['currency_code' => 'USD', 'value' => number_format($shippingUsd, 2, '.', '')];
        }

        $purchaseUnit = [
            'reference_id' => $referenceId,
            'custom_id'    => Str::limit($referenceId, 127, ''),
            'amount' => [
                'currency_code' => 'USD',
                'value'         => number_format($total, 2, '.', ''),
                'breakdown'     => $breakdown,
            ],
            'items' => array_map(fn ($i) => [
                'name'        => Str::limit($i['name'], 127, ''),
                'quantity'    => (string) $i['quantity'],
                'unit_amount' => ['currency_code' => 'USD', 'value' => number_format(round($i['unit_amount'], 2), 2, '.', '')],
            ], $items),
        ];

        $response = $this->client($store)
            ->withHeaders(['PayPal-Request-Id' => 'tribio-order-' . $referenceId])
            ->post('/v2/checkout/orders', [
                'intent'         => 'CAPTURE',
                'purchase_units' => [$purchaseUnit],
                'application_context' => [
                    'brand_name'          => Str::limit($store->name, 127, ''),
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action'         => 'PAY_NOW',
                ],
            ]);

        return $this->handle($response, 'crear orden');
    }

    public function captureOrder(Store $store, string $paypalOrderId): array
    {
        $response = $this->client($store)
            ->withHeaders(['PayPal-Request-Id' => 'tribio-capture-' . $paypalOrderId])
            ->post("/v2/checkout/orders/{$paypalOrderId}/capture", []);

        return $this->handle($response, 'capturar orden');
    }

    public function getOrder(Store $store, string $paypalOrderId): ?array
    {
        $response = $this->client($store)->get("/v2/checkout/orders/{$paypalOrderId}");

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Optional: only works once the store has configured a Webhook ID in their own
     * PayPal app dashboard (paypal_webhook_id). Not required for the core create+
     * capture flow, which confirms payment synchronously.
     */
    public function verifyWebhookSignature(Store $store, Request $request): bool
    {
        if (empty($store->paypal_webhook_id)) {
            return false;
        }

        $response = $this->client($store)->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url'          => $request->header('PAYPAL-CERT-URL'),
            'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id'        => $store->paypal_webhook_id,
            'webhook_event'     => $request->all(),
        ]);

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    protected function handle($response, string $action): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        $body = $response->json() ?? [];
        $message = $body['details'][0]['description'] ?? ($body['message'] ?? 'Ocurrió un error al procesar el pago con PayPal.');

        Log::error("PayPal: error al {$action}", [
            'status' => $response->status(),
            'body'   => $body,
        ]);

        throw new \RuntimeException($message, $response->status());
    }
}
