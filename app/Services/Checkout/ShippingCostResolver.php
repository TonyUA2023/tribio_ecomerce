<?php

namespace App\Services\Checkout;

use App\Models\Store;

/**
 * Base shipping rate for a destination, before free-shipping thresholds and express.
 * Moved verbatim from StoreController::resolveShippingCostForStore() (which now
 * delegates here). Resolution order is part of the contract with store owners:
 * national flat rate for Peru → per-country cost → state zone → country zone → 'ALL'.
 */
class ShippingCostResolver
{
    public function resolve(Store $store, string $country = 'PE', ?string $state = null): float
    {
        $country = strtoupper(trim($country ?: 'PE'));

        // 1. Envío Nacional Plano para Perú (Tarifa Única para todo el país)
        if ($country === 'PE') {
            if ($state) {
                $rate = $store->shippingRates()->where('is_active', true)
                              ->where('country_code', 'PE')
                              ->where('state', $state)
                              ->first();
                if ($rate) {
                    return (float) $rate->cost;
                }
            }
            if ($store->national_shipping_cost !== null && (float) $store->national_shipping_cost >= 0) {
                return (float) $store->national_shipping_cost;
            }
        }

        // 2. Tarifa específica configurada por país en country_shipping_costs
        $countryCosts = is_array($store->country_shipping_costs) ? $store->country_shipping_costs : json_decode($store->country_shipping_costs ?? '[]', true);
        if (!empty($countryCosts) && isset($countryCosts[$country]) && is_numeric($countryCosts[$country])) {
            return (float) $countryCosts[$country];
        }

        // 3. Consulta por departamento / estado en shipping_rates
        if ($state) {
            $rate = $store->shippingRates()->where('is_active', true)
                          ->where('country_code', $country)
                          ->where('state', $state)
                          ->first();
            if ($rate) {
                return (float) $rate->cost;
            }
        }

        // 4. Consulta por país predeterminado en shipping_rates
        $rate = $store->shippingRates()->where('is_active', true)
                      ->where('country_code', $country)
                      ->whereNull('state')
                      ->first();
        if ($rate) {
            return (float) $rate->cost;
        }

        // 5. Fallback Internacional General (ALL)
        $rate = $store->shippingRates()->where('is_active', true)
                      ->where('country_code', 'ALL')
                      ->first();

        return $rate ? (float) $rate->cost : 0.0;
    }
}
