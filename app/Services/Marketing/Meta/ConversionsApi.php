<?php

namespace App\Services\Marketing\Meta;

use App\Models\StoreMarketingIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Meta Conversions API: POST /{pixel_id}/events. The access token travels in the JSON
 * body, never in the URL — HTTP client errors quote the URL, and those messages end up
 * in logs and on the owner's dashboard.
 */
class ConversionsApi
{
    /**
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>  Meta's answer (events_received, fbtrace_id, messages)
     *
     * @throws ConversionsApiException when Meta rejects the request
     * @throws ConnectionException when Meta can't be reached
     */
    public function send(StoreMarketingIntegration $integration, array $events, bool $test = false): array
    {
        $body = ['data' => $events, 'access_token' => (string) $integration->capi_token];
        if ($test && filled($integration->test_event_code)) {
            $body['test_event_code'] = $integration->test_event_code;
        }

        $response = Http::timeout(10)->acceptJson()->asJson()->post($this->endpoint($integration), $body);

        if ($response->failed()) {
            throw new ConversionsApiException(
                (string) ($response->json('error.message') ?: 'Meta respondió con el código HTTP ' . $response->status() . '.'),
                $response->status(),
            );
        }

        return $response->json() ?? [];
    }

    public function endpoint(StoreMarketingIntegration $integration): string
    {
        $version = config('services.meta.graph_version', 'v26.0');

        return "https://graph.facebook.com/{$version}/" . rawurlencode((string) $integration->pixel_id) . '/events';
    }

    /** What gets stored from a successful answer (no user data ever comes back). */
    public static function summary(array $response): array
    {
        return array_intersect_key($response, array_flip(['events_received', 'fbtrace_id', 'messages']));
    }

    /** An owner-readable reason, with the token scrubbed just in case it was echoed. */
    public static function describe(\Throwable $e, StoreMarketingIntegration $integration): string
    {
        $message = $e instanceof ConnectionException
            ? 'No se pudo conectar con Meta. Se volverá a intentar.'
            : $e->getMessage();

        $token = (string) $integration->capi_token;
        if ($token !== '') {
            $message = str_replace($token, '***', $message);
        }

        return mb_substr($message, 0, 500);
    }
}
