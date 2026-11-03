<?php

namespace App\Services\Whatsapp;

use App\Models\Store;
use App\Models\WhatsappConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Connects a store's own Cloud API number (manual setup: the owner pastes the IDs, a
 * permanent system-user token and the app secret from their Meta app). Embedded Signup
 * through Tribio as Tech Provider will fill the same row later.
 */
class ConnectionService
{
    public function __construct(private CloudApi $api, private WhatsappSchema $schema)
    {
    }

    public function save(Store $store, array $data): WhatsappConnection
    {
        $connection = $store->whatsappConnection()->first() ?? new WhatsappConnection([
            'store_id'     => $store->id,
            'verify_token' => Str::random(40),
            'webhook_key'  => Str::random(40),
        ]);

        $needsSecrets = !$connection->exists || $connection->status === 'disconnected';
        foreach (['access_token' => 'Pega el token de acceso permanente.', 'app_secret' => 'Pega la clave secreta de la app de Meta.'] as $field => $message) {
            if ($needsSecrets && blank($data[$field] ?? null)) {
                throw ValidationException::withMessages([$field => $message]);
            }
        }

        $connection->fill([
            'waba_id'         => trim($data['waba_id']),
            'phone_number_id' => trim($data['phone_number_id']),
            'status'          => 'pending',
        ]);
        foreach (['access_token', 'app_secret'] as $field) {
            if (filled($data[$field] ?? null)) {
                $connection->{$field} = trim($data[$field]);
            }
        }
        $connection->save();

        $this->verify($connection);

        return $connection;
    }

    /** Reads the number from Meta and subscribes the app to the WABA's webhooks. */
    public function verify(WhatsappConnection $connection): bool
    {
        try {
            $number = $this->api->phoneNumber($connection);
            $this->api->subscribeApp($connection);
        } catch (CloudApiException|ConnectionException $e) {
            $connection->forceFill(['status' => 'error', 'last_error' => CloudApi::describe($e, $connection)])->save();

            return false;
        }

        $connection->forceFill([
            'display_phone_number' => $number['display_phone_number'] ?? $connection->display_phone_number,
            'verified_name'        => $number['verified_name'] ?? $connection->verified_name,
            'quality_rating'       => $number['quality_rating'] ?? null,
            'status'               => 'connected',
            'last_error'           => null,
            'connected_at'         => $connection->connected_at ?? now(),
        ])->save();

        $this->refreshLimit($connection);

        return true;
    }

    /** Broadcasts respect it; a failure here never blocks the connection (the screen then assumes the lowest tier). */
    private function refreshLimit(WhatsappConnection $connection): void
    {
        if (!$this->schema->broadcastsReady()) {
            return;
        }

        try {
            [$tier, $limit] = WhatsappBudget::parseTier($this->api->messagingLimit($connection));
        } catch (CloudApiException|ConnectionException $e) {
            return;
        }
        if ($tier) {
            $connection->forceFill(['messaging_limit_tier' => $tier, 'messaging_limit' => $limit])->save();
        }
    }

    /** Keeps the chat history; the secrets go, so webhooks and sends stop at once. */
    public function disconnect(WhatsappConnection $connection): void
    {
        $connection->forceFill(['status' => 'disconnected', 'access_token' => '', 'app_secret' => '', 'last_error' => null])->save();
    }
}
