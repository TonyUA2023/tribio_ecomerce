<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesCurrentStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateGatewayRequest;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Pasarela de pago. A store has ONE active gateway (ADR 0003). Secrets are write-only:
 * the phone only ever learns whether one is saved and its last four characters.
 */
class PaymentGatewayController extends Controller
{
    use ResolvesCurrentStore;

    public function show(): JsonResponse
    {
        return response()->json(['gateway' => $this->present($this->currentStore())]);
    }

    public function update(UpdateGatewayRequest $request): JsonResponse
    {
        $store = $this->currentStore();
        $gateway = $request->input('payment_gateway') ?: null;

        $data = [
            'checkout_mode'   => $request->checkout_mode,
            'payment_gateway' => $gateway,
            'flow_enabled'    => $gateway === 'flow',
            'paypal_mode'     => $request->input('paypal_mode') === 'live' ? 'live' : 'sandbox',
        ];

        // Non-secret fields follow what the app sends; absent keys are left alone.
        foreach (['mp_public_key', 'paypal_client_id', 'paypal_webhook_id', 'flow_mode', 'flow_currency'] as $field) {
            if ($request->exists($field)) {
                $data[$field] = $request->input($field) ? trim((string) $request->input($field)) : null;
            }
        }
        if (array_key_exists('mp_public_key', $data)) {
            $data['gateway_public_key'] = $data['mp_public_key'];
        }

        // Secrets: empty means "keep the saved one".
        if ($request->filled('mp_access_token')) {
            $data['mp_access_token'] = $data['gateway_access_token'] = trim($request->input('mp_access_token'));
        }
        foreach (['paypal_client_secret', 'flow_api_key', 'flow_secret_key'] as $secret) {
            if ($request->filled($secret)) {
                $data[$secret] = trim($request->input($secret));
            }
        }

        if ($gateway === 'flow') {
            $apiKey = $data['flow_api_key'] ?? $store->flow_api_key;
            $secretKey = $data['flow_secret_key'] ?? $store->flow_secret_key;
            if (empty($apiKey) || empty($secretKey)) {
                throw ValidationException::withMessages(['payment_gateway' => 'Seleccionaste Flow: ingresa tu API Key y Secret Key para activarlo.']);
            }
        }

        $store->update($data);

        return response()->json([
            'message' => 'Configuración de pasarela de pago actualizada correctamente.',
            'gateway' => $this->present($store->refresh()),
        ]);
    }

    private function present(Store $s): array
    {
        return [
            'checkout_mode'   => $s->checkout_mode ?: 'whatsapp',
            'payment_gateway' => $s->payment_gateway,
            'mercado_pago' => [
                'public_key'       => $s->mp_public_key ?: $s->gateway_public_key,
                'has_access_token' => filled($s->mp_access_token ?: $s->gateway_access_token),
                'token_hint'       => $this->hint($s->mp_access_token ?: $s->gateway_access_token),
            ],
            'paypal' => [
                'client_id'          => $s->paypal_client_id,
                'mode'               => $s->paypal_mode ?: 'sandbox',
                'webhook_id'         => $s->paypal_webhook_id,
                'has_client_secret'  => filled($s->paypal_client_secret),
                'secret_hint'        => $this->hint($s->paypal_client_secret),
            ],
            'flow' => [
                'mode'          => $s->flow_mode ?: 'sandbox',
                'currency'      => $s->flow_currency ?: 'PEN',
                'has_api_key'   => filled($s->flow_api_key),
                'has_secret_key' => filled($s->flow_secret_key),
                'key_hint'      => $this->hint($s->flow_api_key),
            ],
        ];
    }

    private function hint(?string $secret): ?string
    {
        $secret = (string) $secret;

        return $secret === '' ? null : '••••' . substr($secret, -4);
    }
}
