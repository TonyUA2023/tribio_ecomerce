<?php

namespace App\Services\Payments;

use App\Models\Store;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Asks Mercado Pago itself what happened to a payment. A buyer's browser returning with
 * `?collection_status=approved` proves nothing — anyone can type that URL — so no
 * return handler may credit money without this lookup (the webhook already did it).
 */
class MercadoPagoPayments
{
    public function token(Store $store): ?string
    {
        $token = trim((string) ($store->mp_access_token ?: $store->gateway_access_token));

        return $token !== '' ? $token : null;
    }

    /**
     * The payment as Mercado Pago reports it, or null when it can't be fetched or does
     * not belong to $reference (our external_reference for that checkout).
     */
    public function verifiedPayment(Store $store, ?string $paymentId, string $reference): ?array
    {
        $token = $this->token($store);
        if (!$token || !is_string($paymentId) || !ctype_digit($paymentId)) {
            return null;
        }

        try {
            $client = app()->makeWith(Client::class, ['config' => ['timeout' => 10]]);
            $response = $client->get("https://api.mercadopago.com/v1/payments/{$paymentId}", [
                'headers' => ['Authorization' => 'Bearer ' . $token],
            ]);
            $payment = json_decode($response->getBody()->getContents(), true);
        } catch (\Throwable $e) {
            Log::warning('Mercado Pago: no se pudo verificar el pago al volver de la pasarela.', [
                'store_id' => $store->id, 'reference' => $reference, 'payment_id' => $paymentId, 'error' => $e->getMessage(),
            ]);
            return null;
        }

        return is_array($payment) && ($payment['external_reference'] ?? null) === $reference ? $payment : null;
    }
}
