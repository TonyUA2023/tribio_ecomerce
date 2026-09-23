<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Store;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

/**
 * Online payment of a made-to-order balance through Mercado Pago Checkout Pro (hosted:
 * cards, Yape, PagoEfectivo…). Reuses PendingCheckout so the existing webhook confirms
 * it; the draft carries `balance_for_order_id`, so PendingCheckout::settleBalance()
 * credits the existing order's ledger instead of creating a new order. Like the
 * storefront Checkout Pro flow, it charges in PEN or USD only.
 */
class MercadoPagoBalanceCheckout
{
    public function __construct(private readonly MercadoPagoPayments $payments)
    {
    }

    public function supports(Store $store, Order $order): bool
    {
        return $store->payment_gateway === 'mercado_pago'
            && $this->payments->token($store) !== null
            && in_array($order->currency ?: 'PEN', ['PEN', 'USD'], true);
    }

    /** @return string the Mercado Pago URL to send the buyer to */
    public function start(Store $store, Order $order): string
    {
        $amount = round((float) $order->balance_due, 2);
        if ($amount <= 0 || !$this->supports($store, $order)) {
            throw new \DomainException('Este pedido no tiene saldo que pagar en línea.');
        }

        for ($n = 1; PendingCheckout::where('reference', $reference = "{$order->order_number}-S{$n}")->exists(); $n++) {
        }
        $pending = PendingCheckout::create([
            'store_id' => $store->id,
            'reference' => $reference,
            'gateway' => 'mercadopago',
            // The back_url carries this secret: a guessed reference alone must not lead
            // anyone to the order's page (the return redirects to its signed link).
            'gateway_meta' => ['return_token' => Str::random(40)],
            'payload' => [
                'balance_for_order_id' => $order->id,
                'total' => $amount,
                'amount_due_now' => $amount,
                'currency' => $order->currency ?: 'PEN',
                'customer_email' => $order->customer_email,
                'items' => [],
            ],
        ]);

        $backUrl = route('store.balance.return', [$store->slug, $reference, $pending->gateway_meta['return_token']]);
        $body = [
            'items' => [[
                'title' => "Saldo del pedido {$order->order_number}",
                'quantity' => 1,
                'unit_price' => $amount,
                'currency_id' => ($order->currency ?: 'PEN') === 'USD' ? 'USD' : 'PEN',
            ]],
            'payer' => ['name' => $order->customer_name, 'email' => $order->customer_email],
            'back_urls' => ['success' => $backUrl, 'failure' => $backUrl, 'pending' => $backUrl],
            'external_reference' => $reference,
            'statement_descriptor' => Str::limit(preg_replace('/[^A-Za-z0-9 ]/', '', $store->name), 22),
        ];
        // Mercado Pago rejects auto_return / notification_url pointing at a non-public host.
        if (!str_contains($backUrl, 'localhost') && !str_contains($backUrl, '127.0.0.1')) {
            $body['auto_return'] = 'approved';
            $body['notification_url'] = route('api.mercadopago.webhook', [$store->id]);
        }

        try {
            $client = app()->makeWith(Client::class, ['config' => ['timeout' => 15]]);
            $response = $client->post('https://api.mercadopago.com/checkout/preferences', [
                'headers' => ['Authorization' => 'Bearer ' . $this->payments->token($store), 'Content-Type' => 'application/json'],
                'json' => $body,
            ]);
            $preference = json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            $pending->delete();
            throw $e;
        }

        $sandbox = str_starts_with((string) $this->payments->token($store), 'TEST-');
        $url = $sandbox ? ($preference['sandbox_init_point'] ?? $preference['init_point'] ?? null) : ($preference['init_point'] ?? null);
        if (!$url) {
            $pending->delete();
            throw new \RuntimeException('Mercado Pago no devolvió una URL de pago.');
        }
        $pending->update(['gateway_ref' => $preference['id'] ?? null]);

        return $url;
    }
}
