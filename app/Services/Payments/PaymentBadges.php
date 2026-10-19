<?php

namespace App\Services\Payments;

use App\Models\Store;

/** Which payment logos a storefront footer may show: only what the store really accepts. */
class PaymentBadges
{
    /** @return list<string> keys: visa, mastercard, paypal, mercadopago, yape, plin, bank, cash */
    public function forStore(Store $store): array
    {
        $cardMode = in_array($store->checkout_mode, ['card', 'mixed'], true);
        $badges = [];

        if ($cardMode && $store->payment_gateway === 'mercado_pago' && ($store->mp_access_token || $store->gateway_access_token)) {
            array_push($badges, 'visa', 'mastercard', 'mercadopago', 'yape');
        }
        if ($cardMode && $store->payment_gateway === 'flow') {
            array_push($badges, 'visa', 'mastercard');
        }
        if ($cardMode && $store->payment_gateway === 'paypal' && $store->paypal_client_id) {
            $badges[] = 'paypal';
        }

        $manual = app(ManualPayments::class)->enabledFor($store);
        if (isset($manual['manual_yape'])) {
            $badges[] = 'yape';
        }
        if (isset($manual['manual_plin'])) {
            $badges[] = 'plin';
        }
        if (isset($manual['manual_bank'])) {
            $badges[] = 'bank';
        }
        if (isset($manual['manual_cod'])) {
            $badges[] = 'cash';
        }

        return array_values(array_unique($badges));
    }
}
