<?php

namespace App\Services\Marketing\Meta;

use App\Models\MarketingEventLog;
use App\Models\Store;
use App\Models\StoreMarketingIntegration;
use App\Services\Marketing\MarketingSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A store's Meta settings: read (for the storefront, feed and tracking), save (from
 * Dashboard → Marketing) and the "send a test event" check.
 */
class MetaIntegrationService
{
    public function __construct(
        private MarketingSchema $schema,
        private ConversionsApi $api,
    ) {}

    public function available(): bool
    {
        return $this->schema->ready();
    }

    /** The saved settings (active or paused), or null if never configured / tables missing. */
    public function find(Store $store): ?StoreMarketingIntegration
    {
        if (!$this->schema->ready()) {
            return null;
        }

        return $store->metaIntegration; // lazy-loaded once per Store instance
    }

    /** Only settings the owner hasn't paused: what the storefront, feed and tracking use. */
    public function active(Store $store): ?StoreMarketingIntegration
    {
        $integration = $this->find($store);

        return $integration && $integration->is_active ? $integration : null;
    }

    /** @param array<string, mixed> $data validated UpdateMetaIntegrationRequest data */
    public function save(Store $store, array $data): StoreMarketingIntegration
    {
        $integration = StoreMarketingIntegration::firstOrNew([
            'store_id' => $store->id,
            'provider' => StoreMarketingIntegration::PROVIDER_META,
        ]);

        $integration->fill([
            'is_active'           => (bool) ($data['is_active'] ?? false),
            'pixel_id'            => $data['pixel_id'] ?? null,
            'test_event_code'     => $data['test_event_code'] ?? null,
            'domain_verification' => $data['domain_verification'] ?? null,
            'default_condition'   => $data['default_condition'] ?? 'new',
        ]);

        // The token is never sent back to the form: an empty field keeps the saved one.
        if (!empty($data['remove_capi_token'])) {
            $integration->capi_token = null;
        } elseif (!empty($data['capi_token'])) {
            $integration->capi_token = $data['capi_token'];
        }

        $integration->save();
        $store->setRelation('metaIntegration', $integration);

        return $integration;
    }

    /**
     * Sends a PageView flagged with the owner's test code, so it shows up only under
     * "Probar eventos" in Meta's Events Manager and never counts in real ads.
     *
     * @return array{ok: bool, message: string}
     */
    public function sendTestEvent(Store $store, StoreMarketingIntegration $integration, Request $request): array
    {
        $eventId = 'test_' . Str::uuid();
        $log = MarketingEventLog::create([
            'store_id' => $store->id, 'provider' => StoreMarketingIntegration::PROVIDER_META,
            'event_name' => 'PageView', 'event_id' => $eventId, 'is_test' => true,
            'status' => MarketingEventLog::STATUS_PENDING, 'attempts' => 1,
        ]);

        try {
            $response = $this->api->send($integration, [[
                'event_name'       => 'PageView',
                'event_time'       => now()->timestamp,
                'event_id'         => $eventId,
                'action_source'    => 'website',
                'event_source_url' => $store->url,
                'user_data'        => array_filter([
                    'client_ip_address' => $request->ip(),
                    'client_user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                ]),
            ]], test: true);
        } catch (\Throwable $e) {
            $message = ConversionsApi::describe($e, $integration);
            $log->update(['status' => MarketingEventLog::STATUS_FAILED, 'error' => $message]);
            $integration->recordError($message);

            return ['ok' => false, 'message' => $message];
        }

        $log->update(['status' => MarketingEventLog::STATUS_SENT, 'sent_at' => now(), 'response' => ConversionsApi::summary($response)]);
        $integration->recordSuccess();

        return ['ok' => true, 'message' => 'Meta recibió el evento de prueba. Revísalo en el Administrador de eventos → Probar eventos.'];
    }
}
