<?php

namespace App\Services\Marketing\Google;

use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\MarketingSchema;

/**
 * A store's Google settings: Merchant Center (catalog feed + site verification),
 * Analytics 4 and the Google Ads purchase conversion. Everything is read-only from
 * Google's side in this stage — nothing is sent to a Google API.
 */
class GoogleIntegrationService
{
    public function __construct(private MarketingSchema $schema) {}

    public function available(): bool
    {
        return $this->schema->googleReady();
    }

    /** The saved settings (active or paused), or null if never configured / columns missing. */
    public function find(Store $store): ?StoreMarketingIntegration
    {
        if (!$this->schema->googleReady()) {
            return null;
        }

        return $store->googleIntegration; // lazy-loaded once per Store instance
    }

    /** Only settings the owner hasn't paused: what the storefront and the feed use. */
    public function active(Store $store): ?StoreMarketingIntegration
    {
        $integration = $this->find($store);

        return $integration && $integration->is_active ? $integration : null;
    }

    /** @param array<string, mixed> $data validated UpdateGoogleIntegrationRequest data */
    public function save(Store $store, array $data): StoreMarketingIntegration
    {
        $integration = StoreMarketingIntegration::firstOrNew([
            'store_id' => $store->id,
            'provider' => StoreMarketingIntegration::PROVIDER_GOOGLE,
        ]);

        $mode = $data['shopping_mode'] ?? null;
        $integration->fill([
            'is_active'            => (bool) ($data['is_active'] ?? false),
            'shopping_mode'        => in_array($mode, [StoreMarketingIntegration::SHOPPING_VIA_TRIBIO, StoreMarketingIntegration::SHOPPING_OWN_ACCOUNT], true) ? $mode : null,
            'domain_verification'  => $data['domain_verification'] ?? null,
            'default_condition'    => $data['default_condition'] ?? 'new',
            'measurement_id'       => $data['measurement_id'] ?? null,
            'ads_conversion_id'    => $data['ads_conversion_id'] ?? null,
            'ads_conversion_label' => $data['ads_conversion_label'] ?? null,
        ]);
        $integration->save();
        $store->setRelation('googleIntegration', $integration);

        return $integration;
    }

    /**
     * The one-click "Mostrar mis productos en Google" of Marketing → Resumen: Tribio
     * publishes the store; any other Google setting already saved is kept.
     */
    public function publishViaTribio(Store $store): StoreMarketingIntegration
    {
        $integration = StoreMarketingIntegration::firstOrNew([
            'store_id' => $store->id,
            'provider' => StoreMarketingIntegration::PROVIDER_GOOGLE,
        ]);
        $integration->fill([
            'is_active'         => true,
            'shopping_mode'     => StoreMarketingIntegration::SHOPPING_VIA_TRIBIO,
            'default_condition' => $integration->default_condition ?: 'new',
        ]);
        $integration->save();
        $store->setRelation('googleIntegration', $integration);

        return $integration;
    }
}
