<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pasarela de pago from the mobile app. Secrets never come back to the phone, so an
 * empty secret field means "keep what is saved" (see Api\PaymentGatewayController).
 */
class UpdateGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->currentStore();
    }

    public function rules(): array
    {
        return [
            'checkout_mode'        => 'required|string|in:whatsapp,card,mixed',
            'payment_gateway'      => 'nullable|string|in:mercado_pago,paypal,flow',
            'mp_access_token'      => 'nullable|string|max:255',
            'mp_public_key'        => 'nullable|string|max:255',
            'paypal_client_id'     => 'nullable|string|max:255',
            'paypal_client_secret' => 'nullable|string|max:255',
            'paypal_mode'          => 'nullable|string|in:sandbox,live',
            'paypal_webhook_id'    => 'nullable|string|max:255',
            'flow_api_key'         => 'nullable|string|max:255',
            'flow_secret_key'      => 'nullable|string|max:255',
            'flow_mode'            => 'nullable|in:sandbox,live',
            'flow_currency'        => 'nullable|in:PEN,USD,CLP,MXN',
        ];
    }
}
