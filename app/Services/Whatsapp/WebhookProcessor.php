<?php

namespace App\Services\Whatsapp;

use App\Jobs\DownloadWhatsappMedia;
use App\Models\Contact;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappWebhookEvent;
use App\Services\Crm\ContactService;
use App\Services\Crm\LifecycleClassifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns one stored webhook into inbox rows: incoming messages (customer matched by phone,
 * or by business-scoped user ID when Meta omits the phone) and delivery statuses of what
 * the store sent. Events for another phone number are ignored; replays are no-ops.
 */
class WebhookProcessor
{
    private const MEDIA_LABELS = [
        'image'    => '📷 Foto',
        'video'    => '🎬 Video',
        'audio'    => '🎤 Audio',
        'document' => '📄 Documento',
        'sticker'  => 'Sticker',
    ];

    /**
     * A whole message that means "stop sending me offers" (also Meta's marketing opt-out
     * button). Compared after lowercasing and removing accents and punctuation.
     */
    private const OPT_OUT = [
        'stop', 'baja', 'darme de baja', 'dar de baja', 'de baja', 'parar', 'detener', 'cancelar', 'desuscribir', 'desuscribirme',
        'unsubscribe', 'no mas', 'no mas mensajes', 'no quiero mas mensajes', 'no me escriban', 'no enviar mas',
        'stop promotions', 'detener promociones', 'no mas promociones',
    ];

    public function __construct(
        private ContactService $contacts,
        private LifecycleClassifier $stages,
        private TemplateService $templates,
        private WhatsappSchema $schema,
    ) {
    }

    public function process(WhatsappWebhookEvent $event): void
    {
        $connection = $event->connection;
        $payload = json_decode($event->payload, true);
        if (!$connection || !is_array($payload)) {
            return;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                if (($change['field'] ?? null) === 'message_template_status_update') {
                    if ($this->schema->templatesReady() && (string) ($entry['id'] ?? $connection->waba_id) === $connection->waba_id) {
                        $this->templates->statusUpdate($connection, $value);
                    }
                    continue;
                }
                if (($change['field'] ?? null) !== 'messages'
                    || (string) ($value['metadata']['phone_number_id'] ?? '') !== $connection->phone_number_id) {
                    continue;
                }
                foreach ($value['messages'] ?? [] as $message) {
                    $this->inbound($connection, $message, $value['contacts'] ?? []);
                }
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->status($connection, $status);
                }
            }
        }

        $connection->forceFill(['last_webhook_at' => now()])->save();
    }

    private function inbound(WhatsappConnection $connection, array $message, array $profiles): void
    {
        $wamid = (string) ($message['id'] ?? '');
        if ($wamid === '' || WhatsappMessage::where('wamid', $wamid)->exists()) {
            return;
        }

        $userId = $message['from_user_id'] ?? null;
        $phone = $this->phone($message['from'] ?? null);
        if (($message['type'] ?? null) === 'system') {
            $this->identityChanged($connection, $message, $userId, $phone);

            return;
        }

        $profile = collect($profiles)->first(fn ($p) => ($userId && ($p['user_id'] ?? null) === $userId) || ($phone && ($p['wa_id'] ?? null) === $phone));
        $userId ??= $profile['user_id'] ?? null;
        if (!$userId && !$phone) {
            return;
        }

        $at = isset($message['timestamp']) ? Carbon::createFromTimestamp((int) $message['timestamp']) : now();
        [$type, $body, $extra] = $this->content($message);

        $stored = DB::transaction(function () use ($connection, $wamid, $userId, $phone, $profile, $at, $type, $body, $extra) {
            $conversation = $this->conversation($connection, $userId, $phone, $profile['profile']['name'] ?? null);
            $quiet = !$conversation->last_inbound_at || $conversation->last_inbound_at->lt($at->copy()->subHours(WhatsappConversation::WINDOW_HOURS));

            $stored = WhatsappMessage::create([
                'store_id'        => $connection->store_id,
                'conversation_id' => $conversation->id,
                'direction'       => 'in',
                'wamid'           => $wamid,
                'type'            => $type,
                'body'            => $body,
                'payload'         => $extra ?: null,
                'status'          => 'received',
                'wa_timestamp'    => $at,
            ]);

            $conversation->forceFill([
                'last_inbound_at'      => $conversation->last_inbound_at?->gt($at) ? $conversation->last_inbound_at : $at,
                'last_message_at'      => $conversation->last_message_at?->gt($at) ? $conversation->last_message_at : $at,
                'last_message_preview' => Str::limit((string) $body, 150),
                'unread_count'         => $conversation->unread_count + 1,
            ])->save();

            // The timeline gets the start of each conversation, not every message.
            if ($quiet && $conversation->contact) {
                $this->contacts->log($conversation->contact, 'whatsapp', 'Te escribió por WhatsApp: «' . Str::limit((string) $body, 120) . '»', null, $at);
            }

            return $stored;
        });

        if (!empty($extra['media_id'])) {
            DownloadWhatsappMedia::dispatch($stored->id);
        }
        if (in_array($type, ['text', 'button', 'interactive'], true)) {
            $this->optOut($stored, (string) $body, $at);
        }
    }

    /** Marketing consent is withdrawn the moment the customer asks (Ley 29733); utility messages still reach them. */
    private function optOut(WhatsappMessage $message, string $body, Carbon $at): void
    {
        $text = trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii($body))));
        $contact = $message->conversation?->contact;
        if (!in_array($text, self::OPT_OUT, true) || !$contact?->accepts_marketing) {
            return;
        }

        $contact->forceFill(['accepts_marketing' => false])->save();
        $this->contacts->log($contact, 'whatsapp', 'Pidió por WhatsApp no recibir más ofertas: ya no le llegarán difusiones de marketing.', null, $at);
    }

    private function status(WhatsappConnection $connection, array $status): void
    {
        $message = WhatsappMessage::where('store_id', $connection->store_id)->where('wamid', (string) ($status['id'] ?? ''))->first();
        if (!$message || $message->isInbound()) {
            return;
        }

        $state = $status['status'] ?? null;
        $at = isset($status['timestamp']) ? Carbon::createFromTimestamp((int) $status['timestamp']) : now();

        // Meta bills per delivered message; `pricing` rides on sent/delivered/read statuses.
        if (isset($status['pricing']) && $message->cost === null && $this->schema->broadcastsReady()) {
            $category = strtolower((string) ($status['pricing']['category'] ?? ''));
            $billable = isset($status['pricing']['type']) ? $status['pricing']['type'] === 'regular' : (bool) ($status['pricing']['billable'] ?? false);
            $message->forceFill([
                'billing_category' => $category !== '' ? mb_substr($category, 0, 40) : null,
                'cost'             => $billable && $category !== '' ? $connection->rate($category) : 0,
            ])->save();
        }

        if ($state === 'failed') {
            $error = $status['errors'][0] ?? [];
            $message->forceFill([
                'status' => 'failed',
                'error'  => mb_substr(CloudApi::describeCode(isset($error['code']) ? (int) $error['code'] : null, $error['error_data']['details'] ?? $error['title'] ?? null), 0, 255),
            ])->save();

            return;
        }

        $order = WhatsappMessage::STATUS_ORDER;
        if (!isset($order[$state]) || $message->status === 'failed' || $order[$state] <= ($order[$message->status] ?? -1)) {
            return; // statuses can arrive out of order: never step back
        }

        $message->forceFill(['status' => $state] + match ($state) {
            'sent'      => ['sent_at' => $message->sent_at ?? $at],
            'delivered' => ['delivered_at' => $at, 'sent_at' => $message->sent_at ?? $at],
            'read'      => ['read_at' => $at, 'delivered_at' => $message->delivered_at ?? $at],
            default     => [],
        })->save();
    }

    private function conversation(WhatsappConnection $connection, ?string $userId, ?string $phone, ?string $name): WhatsappConversation
    {
        $query = fn () => WhatsappConversation::where('connection_id', $connection->id);
        $conversation = ($userId ? $query()->where('wa_user_id', $userId)->first() : null)
            ?? ($phone ? $query()->where('wa_id', $phone)->first() : null);

        if (!$conversation) {
            return WhatsappConversation::create([
                'store_id'      => $connection->store_id,
                'connection_id' => $connection->id,
                'contact_id'    => $this->contact($connection, $phone, $name)->id,
                'wa_user_id'    => $userId,
                'wa_id'         => $phone,
                'profile_name'  => $name ? Str::limit($name, 120, '') : null,
            ]);
        }

        $conversation->fill(array_filter([
            'wa_user_id'   => $conversation->wa_user_id ?: $userId,
            'wa_id'        => $conversation->wa_id ?: $phone,
            'profile_name' => $name ? Str::limit($name, 120, '') : $conversation->profile_name,
        ]));
        if (!$conversation->contact_id) {
            $conversation->contact_id = $this->contact($connection, $phone ?: $conversation->wa_id, $name)->id;
        }
        $conversation->save();

        return $conversation;
    }

    /** The customer this chat belongs to: an existing contact with that phone, or a new lead. */
    private function contact(WhatsappConnection $connection, ?string $phone, ?string $name): Contact
    {
        $store = $connection->store;
        if ($phone && ($existing = $store->contacts()->where('phone', $phone)->first())) {
            return $existing;
        }

        try {
            $contact = $store->contacts()->create([
                'name'   => $name ? Str::limit(trim($name), 120, '') : ($phone ? '+' . $phone : 'Cliente de WhatsApp'),
                'phone'  => $phone,
                'source' => 'whatsapp',
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return $store->contacts()->where('phone', $phone)->firstOrFail();
        }
        $this->stages->apply($contact);

        return $contact;
    }

    /** Meta reports a new phone or a new business-scoped ID for the same customer. */
    private function identityChanged(WhatsappConnection $connection, array $message, ?string $userId, ?string $phone): void
    {
        $system = $message['system'] ?? [];
        $conversation = WhatsappConversation::where('connection_id', $connection->id)
            ->where(fn ($q) => $q->when($userId, fn ($q) => $q->orWhere('wa_user_id', $userId))->when($phone, fn ($q) => $q->orWhere('wa_id', $phone)))
            ->first();
        if (!$conversation) {
            return;
        }

        $conversation->forceFill(array_filter([
            'wa_user_id' => $system['user_id'] ?? null,
            'wa_id'      => $this->phone($system['wa_id'] ?? null),
        ]))->save();
    }

    /** @return array{0: string, 1: ?string, 2: array} type, readable body, what to keep */
    private function content(array $message): array
    {
        $type = (string) ($message['type'] ?? 'unsupported');
        $data = $message[$type] ?? [];

        return match (true) {
            $type === 'text'        => ['text', $data['body'] ?? '', []],
            isset(self::MEDIA_LABELS[$type]) => [$type, filled($data['caption'] ?? null) ? self::MEDIA_LABELS[$type] . ' · ' . $data['caption'] : self::MEDIA_LABELS[$type],
                array_filter(['media_id' => $data['id'] ?? null, 'mime_type' => $data['mime_type'] ?? null, 'filename' => $data['filename'] ?? null])],
            $type === 'location'    => ['location', ($place = trim(($data['name'] ?? '') . ' ' . ($data['address'] ?? ''))) !== '' ? '📍 ' . $place : '📍 Ubicación',
                array_filter(['latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null])],
            $type === 'button'      => ['button', $data['text'] ?? '', []],
            $type === 'interactive' => ['interactive', $data['button_reply']['title'] ?? $data['list_reply']['title'] ?? 'Respuesta', []],
            $type === 'reaction'    => ['reaction', 'Reaccionó ' . ($data['emoji'] ?? ''), array_filter(['message_id' => $data['message_id'] ?? null])],
            $type === 'contacts'    => ['contacts', '👤 Contacto compartido', []],
            $type === 'order'       => ['order', '🛒 Pedido desde el catálogo', []],
            default                 => ['unsupported', 'Mensaje que aún no podemos mostrar', []],
        };
    }

    /** A real phone (digits); business-scoped IDs look like "PE.123…" and are not phones. */
    private function phone(?string $value): ?string
    {
        return $value !== null && preg_match('/^\+?\d{6,15}$/', $value) ? ltrim($value, '+') : null;
    }
}
