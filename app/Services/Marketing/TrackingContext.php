<?php

namespace App\Services\Marketing;

use App\Models\Order;
use App\Models\OrderAttribution;
use App\Models\Store;
use App\Services\Marketing\Google\GoogleIntegrationService;
use App\Services\Marketing\Meta\MetaIntegrationService;
use Illuminate\Http\Request;

/**
 * What the checkout remembers for marketing, stored in the PendingCheckout payload
 * ('tracking') because the payment often confirms later from a webhook, with no
 * browser around: the buyer's cookie choice, Meta's browser ids and where the visit
 * came from. Captured only for stores with an active Meta Pixel or Google tag; Meta's
 * ids/IP only for Meta stores and only if the buyer accepted cookies
 * (resources/js/marketing.js).
 *
 * Never throws: marketing must not be able to break a checkout.
 */
class TrackingContext
{
    public const CONSENT_COOKIE = 'tribio_consent';
    public const ATTRIBUTION_COOKIE = 'tribio_attr';

    /**
     * Accepted cookie fields and their column sizes in order_attributions. gclid has no
     * column: it only decides the channel.
     */
    private const FIELD_LIMITS = [
        'utm_source' => 100, 'utm_medium' => 100, 'utm_campaign' => 150, 'utm_content' => 150,
        'utm_term' => 150, 'fbclid' => 255, 'gclid' => 255, 'landing_path' => 255,
    ];

    public function __construct(
        private MarketingSchema $schema,
        private MetaIntegrationService $meta,
        private GoogleIntegrationService $google,
    ) {}

    /** @return array<string, mixed>|null */
    public function capture(Request $request, Store $store): ?array
    {
        try {
            $metaPixel = (bool) $this->meta->active($store)?->hasPixel();
            if (!$metaPixel && !$this->google->active($store)?->hasGoogleTag()) {
                return null;
            }

            $choice = $request->cookie(self::CONSENT_COOKIE);
            $consent = in_array($choice, ['granted', 'denied'], true) ? $choice : null;
            $attribution = $this->attribution($request);

            $tracking = ['consent' => $consent, 'attribution' => $attribution];
            if ($consent !== 'granted' || !$metaPixel) {
                if ($attribution && $consent !== 'granted') {
                    // Ad click ids are personal data; the channel is decided before dropping them.
                    $tracking['attribution']['channel'] = $this->channel($attribution);
                    unset($tracking['attribution']['fbclid'], $tracking['attribution']['gclid']);
                }
                return $tracking;
            }

            return $tracking + array_filter([
                'fbp'               => $this->metaCookie($request->cookie('_fbp')),
                'fbc'               => $this->metaCookie($request->cookie('_fbc')) ?? $this->fbcFromClick($attribution),
                'client_ip_address' => $request->ip(),
                'client_user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
                'event_source_url'  => $this->pageUrl($request),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Called once per new order by PendingCheckout::materialize(), outside its transaction. */
    public function recordAttribution(Order $order, ?array $tracking): void
    {
        $attribution = $tracking['attribution'] ?? null;
        if (!is_array($attribution) || !$attribution || !$this->schema->ready()) {
            return;
        }

        try {
            OrderAttribution::firstOrCreate(['order_id' => $order->id], [
                'store_id'      => $order->store_id,
                'channel'       => $attribution['channel'] ?? $this->channel($attribution),
                'utm_source'    => $attribution['utm_source'] ?? null,
                'utm_medium'    => $attribution['utm_medium'] ?? null,
                'utm_campaign'  => $attribution['utm_campaign'] ?? null,
                'utm_content'   => $attribution['utm_content'] ?? null,
                'utm_term'      => $attribution['utm_term'] ?? null,
                'fbclid'        => $attribution['fbclid'] ?? null,
                'landing_path'  => $attribution['landing_path'] ?? null,
                'first_seen_at' => isset($attribution['ts']) ? now()->setTimestamp((int) $attribution['ts']) : null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The last campaign visit (cookie written by marketing.js), validated. */
    private function attribution(Request $request): ?array
    {
        $raw = $request->cookie(self::ATTRIBUTION_COOKIE);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return null;
        }

        $clean = [];
        foreach (self::FIELD_LIMITS as $key => $max) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                $clean[$key] = mb_substr(trim($data[$key]), 0, $max);
            }
        }
        if (!$clean) {
            return null;
        }
        if (isset($data['ts']) && is_numeric($data['ts'])) {
            $clean['ts'] = (int) $data['ts'];
        }

        return $clean;
    }

    private function channel(array $attribution): string
    {
        $source = mb_strtolower((string) ($attribution['utm_source'] ?? ''));

        return match (true) {
            !empty($attribution['fbclid']) || (bool) preg_match('/^(fb|facebook|ig|instagram|meta|an|msg)\b/', $source) => OrderAttribution::CHANNEL_META,
            !empty($attribution['gclid']) || (bool) preg_match('/^(google|adwords|gads|youtube)\b/', $source) => OrderAttribution::CHANNEL_GOOGLE,
            default => OrderAttribution::CHANNEL_OTHER,
        };
    }

    /** Meta's own cookies look like "fb.1.1712345678901.abc…"; anything else is ignored. */
    private function metaCookie(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^fb\.\d\.\d+\.[\w.\-]+$/', $value) && strlen($value) <= 500 ? $value : null;
    }

    /** Meta's documented _fbc format, built from the ad click when the Pixel didn't set it yet. */
    private function fbcFromClick(?array $attribution): ?string
    {
        if (empty($attribution['fbclid'])) {
            return null;
        }
        $millis = (($attribution['ts'] ?? null) ?: now()->timestamp) * 1000;

        return "fb.1.{$millis}.{$attribution['fbclid']}";
    }

    private function pageUrl(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer');

        return preg_match('#^https?://#i', $referer) ? mb_substr($referer, 0, 1000) : null;
    }
}
