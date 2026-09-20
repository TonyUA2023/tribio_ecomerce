<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
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

    public function createPayment(Store $store, Order $order): string
    {
        if (!$this->isConfigured($store) || $order->currency !== $store->flow_currency || $order->total <= 0) {
            throw new RuntimeException('Flow configuration or currency mismatch.');
        }
        // Use the platform host: custom-domain middleware redirects /api POST requests.
        $platform = rtrim(config('app.url'), '/');
        $parameters = $this->sign([
            'apiKey' => $store->flow_api_key,
            'commerceOrder' => $order->order_number,
            'subject' => 'Pedido ' . $order->order_number . ' en ' . $store->name,
            'currency' => $order->currency,
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'email' => $order->customer_email,
            'paymentMethod' => 9,
            'urlConfirmation' => $platform . '/api/flow/' . $store->id . '/confirmation',
            'urlReturn' => $platform . '/api/flow/' . $store->id . '/return',
        ], $store->flow_secret_key);

        // Do not retry creation automatically: a timeout may hide a created payment.
        // A ConnectionException here propagates as-is so FlowFailure can classify it.
        $data = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(20)
            ->withOptions($this->freshConnectionOptions())
            ->post($this->baseUrl($store) . '/payment/create', $parameters)->throw()->json();
        $url = $data['url'] ?? '';
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($data['token']) || empty($data['flowOrder']) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || !in_array($host, ['www.flow.cl', 'sandbox.flow.cl', 'api.flow.cl'], true)
            || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null
            || !in_array(parse_url($url, PHP_URL_PORT), [null, 443], true)) {
            throw new RuntimeException('Invalid Flow payment response.');
        }
        $order->update(['flow_token' => $data['token'], 'flow_order_id' => (string) $data['flowOrder']]);
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['token' => $data['token']]);
    }

    public function reconcile(Store $store, string $token): Order
    {
        // Token is only a lookup key; the callback's claimed status is never trusted.
        $order = Order::where('store_id', $store->id)->where('payment_method', 'flow')
            ->where('flow_token', $token)->firstOrFail();
        $data = Http::acceptJson()->connectTimeout(5)->timeout(15)
            ->withOptions($this->freshConnectionOptions())
            ->get($this->baseUrl($store) . '/payment/getStatus', $this->sign([
                'apiKey' => $store->flow_api_key, 'token' => $token,
            ], $store->flow_secret_key))->throw()->json();

        if (($data['commerceOrder'] ?? null) !== $order->order_number
            || (string) ($data['flowOrder'] ?? '') !== $order->flow_order_id
            || ($data['currency'] ?? null) !== $order->currency
            || !isset($data['amount']) || !is_numeric($data['amount'])
            || (int) round((float) $data['amount'] * 100) !== (int) round((float) $order->total * 100)
            || !in_array($data['status'] ?? null, [1, 2, 3, 4], true)) {
            throw new RuntimeException('Flow payment verification mismatch.');
        }

        return DB::transaction(function () use ($order, $data) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            // Repeated/out-of-order callbacks cannot undo paid/refunded orders or fulfillment.
            if (in_array($locked->payment_status, ['paid', 'refunded'], true)) {
                return $locked;
            }
            if ($data['status'] === 2) {
                $locked->payment_status = 'paid';
                if ($locked->status === 'pending') {
                    $locked->status = 'confirmed';
                }
                $locked->save();
            } elseif (in_array($data['status'], [3, 4], true)) {
                $locked->update(['payment_status' => 'failed']);
            }
            return $locked;
        });
    }
}
