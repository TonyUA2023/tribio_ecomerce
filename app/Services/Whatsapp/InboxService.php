<?php

namespace App\Services\Whatsapp;

use App\Jobs\SendWhatsappMessage;
use App\Models\Contact;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\Crm\ContactService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Replies from the inbox. Free text only inside the 24 h customer service window (Meta's
 * rule); the message is stored first and sent on the queue, so a slow Meta never blocks
 * the screen and a failure stays visible on the bubble.
 */
class InboxService
{
    public function __construct(private CloudApi $api, private ContactService $contacts, private WhatsappBudget $budget)
    {
    }

    /** The chat with a customer of the store (created to write first when they never wrote). */
    public function conversationFor(WhatsappConnection $connection, Contact $contact): WhatsappConversation
    {
        $conversation = WhatsappConversation::where('connection_id', $connection->id)->where('wa_id', $contact->phone)->first()
            ?? WhatsappConversation::create(['store_id' => $connection->store_id, 'connection_id' => $connection->id, 'contact_id' => $contact->id, 'wa_id' => $contact->phone]);
        if (!$conversation->contact_id) {
            $conversation->forceFill(['contact_id' => $contact->id])->save();
        }

        return $conversation;
    }

    public function reply(WhatsappConversation $conversation, string $body, ?int $userId): WhatsappMessage
    {
        if (!$conversation->connection?->isConnected()) {
            throw ValidationException::withMessages(['body' => 'Tu número de WhatsApp no está conectado. Revisa la conexión.']);
        }
        if (!$conversation->windowOpen()) {
            throw ValidationException::withMessages(['body' => 'Pasaron más de 24 h desde el último mensaje del cliente: Meta solo permite escribirle con una plantilla aprobada.']);
        }

        return DB::transaction(function () use ($conversation, $body, $userId) {
            $message = WhatsappMessage::create([
                'store_id'        => $conversation->store_id,
                'conversation_id' => $conversation->id,
                'direction'       => 'out',
                'type'            => 'text',
                'body'            => $body,
                'status'          => 'queued',
                'user_id'         => $userId,
                'wa_timestamp'    => now(),
            ]);
            $conversation->forceFill(['last_message_at' => now(), 'last_message_preview' => Str::limit('Tú: ' . $body, 150), 'unread_count' => 0])->save();
            SendWhatsappMessage::dispatch($message->id)->afterCommit();

            return $message;
        });
    }

    /**
     * A Meta-approved template: the only way to write first or after the 24 h window.
     * Marketing templates need the customer's consent (Ley 29733 and Meta's opt-in policy).
     *
     * @param  array<string, string>  $values  variable → text
     */
    public function sendTemplate(WhatsappConversation $conversation, WhatsappTemplate $template, array $values, ?int $userId): WhatsappMessage
    {
        if (!$conversation->connection?->isConnected()) {
            throw ValidationException::withMessages(['template_id' => 'Tu número de WhatsApp no está conectado. Revisa la conexión.']);
        }
        if ($template->connection_id !== $conversation->connection_id || !$template->isSendable()) {
            throw ValidationException::withMessages(['template_id' => 'Esa plantilla no está aprobada por Meta o no se puede enviar desde aquí.']);
        }
        if ($template->category === 'MARKETING' && !$conversation->contact?->accepts_marketing) {
            throw ValidationException::withMessages(['template_id' => "{$conversation->display_name} no aceptó recibir ofertas (Ley 29733): solo puedes enviarle plantillas de utilidad."]);
        }

        $clean = [];
        foreach ($template->variables() as $variable) {
            // Meta refuses line breaks, tabs and long runs of spaces inside a variable.
            $clean[$variable] = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($values[$variable] ?? ''))), 0, 1000);
            if ($clean[$variable] === '') {
                throw ValidationException::withMessages(['values' => 'Completa todos los datos de la plantilla.']);
            }
        }

        return DB::transaction(function () use ($conversation, $template, $clean, $userId) {
            $body = $template->render($clean);
            $message = WhatsappMessage::create([
                'store_id'        => $conversation->store_id,
                'conversation_id' => $conversation->id,
                'direction'       => 'out',
                'type'            => 'template',
                'body'            => $body,
                'payload'         => ['template_id' => $template->id, 'template' => $template->name, 'language' => $template->language, 'values' => $clean],
                'status'          => 'queued',
                'user_id'         => $userId,
                'wa_timestamp'    => now(),
            ]);
            $conversation->forceFill(['last_message_at' => now(), 'last_message_preview' => Str::limit('Tú: ' . $body, 150), 'unread_count' => 0])->save();
            if ($conversation->contact) {
                $this->contacts->log($conversation->contact, 'whatsapp', "Plantilla «{$template->name}» por WhatsApp: " . Str::limit($body, 120), $userId);
            }
            SendWhatsappMessage::dispatch($message->id)->afterCommit();

            return $message;
        });
    }

    /** Called by the job. A permanent Meta error marks the bubble failed; others are retried. */
    public function deliver(WhatsappMessage $message): void
    {
        if ($message->status !== 'queued') {
            return;
        }

        $conversation = $message->conversation;
        $connection = $conversation->connection;
        if (!$connection->isConnected()) {
            $this->fail($message, 'Tu número de WhatsApp no está conectado.');

            return;
        }

        if ($message->broadcast_id) {
            if ($message->broadcast?->status === 'cancelled') {
                $this->fail($message, 'Cancelaste la difusión antes de que saliera este mensaje.');

                return;
            }
            if ($this->budget->exceeded($connection)) {
                $this->fail($message, 'Se alcanzó tu tope mensual de gasto en WhatsApp.');

                return;
            }
        }

        if ($message->type === 'template' && !($template = WhatsappTemplate::find($message->payload['template_id'] ?? 0))) {
            $this->fail($message, 'La plantilla ya no existe en Meta.');

            return;
        }

        try {
            $wamid = $message->type === 'template'
                ? $this->api->sendTemplate($connection, $conversation, $template, $message->payload['values'] ?? [])
                : $this->api->sendText($connection, $conversation, (string) $message->body);
        } catch (CloudApiException $e) {
            if (!$e->isPermanent()) {
                throw $e;
            }
            $this->fail($message, CloudApi::describe($e, $connection));

            return;
        }

        // A status webhook may already have moved it forward; only fill what is missing.
        $message->forceFill(['wamid' => $wamid, 'sent_at' => $message->sent_at ?? now()])->save();
        WhatsappMessage::whereKey($message->id)->where('status', 'queued')->update(['status' => 'sent']);
    }

    public function fail(WhatsappMessage $message, string $reason): void
    {
        $message->forceFill(['status' => 'failed', 'error' => mb_substr($reason, 0, 255)])->save();
    }

    public function markRead(WhatsappConversation $conversation): void
    {
        if ($conversation->unread_count > 0) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }
    }
}
