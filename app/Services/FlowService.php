<?php

namespace App\Services;

use App\Models\PendingCheckout;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlowService
{
    public function isConfigured(Store $store): bool
    {
        return $store->flow_enabled && filled($store->flow_api_key) && filled($store->flow_secret_key);
    }

    public function baseUrl(Store $store): string
    {
        return $store->flow_mode === 'live' ? 'https://www.flow.cl/api' : 'https://sandbox.flow.cl/api';
    }

    public function sign(array $parameters, string $secret): array
    {
        unset($parameters['s']);
        ksort($parameters, SORT_STRING);
        $message = '';
        foreach ($parameters as $key => $value) {
            $message .= $key . $value;
        }
        $parameters['s'] = hash_hmac('sha256', $message, $secret);
        return $parameters;
    }

    // Keep connections independent. This is not a fix for DNS, TLS or firewall errors.
    private function freshConnectionOptions(): array
    {
        if (!extension_loaded('curl')) {
            return []; // Guzzle also supports PHP streams; curl constants may not exist.
        }
        return ['curl' => [CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true]];
    }

    /**
     * Starts a Flow payment for a checkout that has not happened yet. Nothing in
     * `orders` or product stock exists at this point — see PendingCheckout::materialize().
     * The checkout is only ever turned into a real order from reconcile() below, once
     * Flow actually reports a definitive result.
     */
    public function createPayment(Store $store, PendingCheckout $pending): string
    {
        $data = $pending->payload;
        if (!$this->isConfigured($store) || ($data['currency'] ?? null) !== $store->flow_currency || $pending->chargeAmount() <= 0) {
            throw new RuntimeException('Flow configuration or currency mismatch.');
        }
        // Use the platform host: custom-domain middleware redirects /api POST requests.
        $platform = rtrim(config('app.url'), '/');
        $parameters = $this->sign([
            'apiKey' => $store->flow_api_key,
            'commerceOrder' => $pending->reference,
            'subject' => 'Pedido ' . $pending->reference . ' en ' . $store->name,
            'currency' => $data['currency'],
            // The total, or just the deposit of a made-to-order cart.
            'amount' => number_format($pending->chargeAmount(), 2, '.', ''),
            'email' => $data['customer_email'],
            'paymentMethod' => 9,
            'urlConfirmation' => $platform . '/api/flow/' . $store->id . '/confirmation',
            'urlReturn' => $platform . '/api/flow/' . $store->id . '/return',
        ], $store->flow_secret_key);

        // Do not retry creation automatically: a timeout may hide a created payment.
        // A ConnectionException here propagates as-is so FlowFailure can classify it.
        $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(20)
            ->withOptions($this->freshConnectionOptions())
            ->post($this->baseUrl($store) . '/payment/create', $parameters)->throw()->json();
        $url = $response['url'] ?? '';
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($response['token']) || empty($response['flowOrder']) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || !in_array($host, ['www.flow.cl', 'sandbox.flow.cl', 'api.flow.cl'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || !in_array(parse_url($url, PHP_URL_PORT), [null, 443], true)) {
            throw new RuntimeException('Invalid Flow payment response.');
        }
        $pending->update([
            'gateway_ref' => $response['token'],
            'gateway_meta' => ['flowOrder' => (string) $response['flowOrder']],
        ]);
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['token' => $response['token']]);
    }

    /**
     * Verifies a Flow token against Flow's own /payment/getStatus and, only on a
     * definitive result (paid or rejected/cancelled), materializes the pending checkout
     * into a real order — a still-pending status never creates anything, matching an
     * abandoned checkout for the other gateways. Returns a plain array rather than an
     * Order because there may not be one yet (see receipt()).
     */
    public function reconcile(Store $store, string $token): array
    {
        // Token is only a lookup key; the callback's claimed status is never trusted.
        $pending = PendingCheckout::where('store_id', $store->id)->where('gateway', 'flow')
            ->where('gateway_ref', $token)->firstOrFail();
        $payload = $pending->payload;
        $data = Http::acceptJson()->connectTimeout(5)->timeout(15)
            ->withOptions($this->freshConnectionOptions())
            ->get($this->baseUrl($store) . '/payment/getStatus', $this->sign([
                'apiKey' => $store->flow_api_key, 'token' => $token,
            ], $store->flow_secret_key))->throw()->json();

        $expectedFlowOrder = $pending->gateway_meta['flowOrder'] ?? null;
        if (($data['commerceOrder'] ?? null) !== $pending->reference
            || (string) ($data['flowOrder'] ?? '') !== $expectedFlowOrder
            || ($data['currency'] ?? null) !== $payload['currency']
            || !isset($data['amount']) || !is_numeric($data['amount'])
            || (int) round((float) $data['amount'] * 100) !== (int) round($pending->chargeAmount() * 100)
            || !in_array($data['status'] ?? null, [1, 2, 3, 4], true)) {
            throw new RuntimeException('Flow payment verification mismatch.');
        }

        if ($data['status'] === 2) {
            $order = $pending->materialize('paid', 'confirmed', decrementStock: true);
            return $this->receipt('paid', $order->order_number, $order->currency, (float) $order->total, $token);
        }
        if (in_array($data['status'], [3, 4], true)) {
            $order = $pending->materialize('failed', 'pending', decrementStock: false);
            return $this->receipt('failed', $order->order_number, $order->currency, (float) $order->total, $token);
        }
        // status 1 (pendiente): nada definitivo todavía. Si una llamada anterior ya
        // materializó algo (p.ej. Flow reportó rechazado y luego vuelve a "pendiente"),
        // se refleja ese estado real en vez de inventar uno nuevo; si no, no se crea nada.
        if ($pending->order_id) {
            $order = $pending->order;
            return $this->receipt($order->payment_status, $order->order_number, $order->currency, (float) $order->total, $token);
        }
        return $this->receipt('pending', $pending->reference, $payload['currency'], (float) $payload['total'], $token);
    }

    private function receipt(string $paymentStatus, string $orderNumber, string $currency, float $total, string $flowToken): array
    {
        return compact('paymentStatus', 'orderNumber', 'currency', 'total', 'flowToken');
    }
}
