<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsappWebhook;
use App\Models\WhatsappConnection;
use App\Models\WhatsappWebhookEvent;
use App\Services\Whatsapp\WhatsappSchema;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Meta → /webhooks/whatsapp/{key}. GET answers the subscription check with the store's
 * verify token; POST must carry X-Hub-Signature-256 (HMAC of the raw body with the app
 * secret). The event is stored as is and processed on the queue: Meta wants a fast 200.
 */
class WhatsappWebhookController extends Controller
{
    public function verify(Request $request, WhatsappSchema $schema, string $key): Response
    {
        $connection = $this->connection($schema, $key);

        // PHP turns "hub.mode" into "hub_mode".
        abort_unless($request->query('hub_mode') === 'subscribe'
            && hash_equals($connection->verify_token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsappSchema $schema, string $key): Response
    {
        $connection = $this->connection($schema, $key);
        $raw = $request->getContent();
        $secret = (string) $connection->app_secret;

        abort_if($secret === '' || $raw === '', 403);
        abort_unless(hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), (string) $request->header('X-Hub-Signature-256')), 403);

        try {
            $event = WhatsappWebhookEvent::firstOrCreate(['payload_hash' => hash('sha256', $raw)], ['connection_id' => $connection->id, 'payload' => $raw]);
        } catch (UniqueConstraintViolationException $e) {
            return response('EVENT_RECEIVED', 200); // the same delivery arrived twice at once
        }

        if ($event->wasRecentlyCreated) {
            ProcessWhatsappWebhook::dispatch($event->id);
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function connection(WhatsappSchema $schema, string $key): WhatsappConnection
    {
        abort_unless($schema->ready(), 404);

        return WhatsappConnection::where('webhook_key', $key)->firstOrFail();
    }
}
