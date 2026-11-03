<?php

namespace App\Services\Whatsapp;

use App\Jobs\SendWhatsappMessage;
use App\Jobs\StartWhatsappBroadcast;
use App\Models\Contact;
use App\Models\Order;
use App\Models\Store;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use App\Models\WhatsappConnection;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\Crm\ContactService;
use App\Services\Crm\MessageTemplates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * WhatsApp broadcasts (ERP Fase 3c): one approved template to a segment of customers.
 * Before sending the owner sees who is reached, who is left out and why, the estimated
 * cost against the monthly cap and Meta's 24 h limit. Marketing only reaches customers who
 * accepted offers (Ley 29733). Messages are queued per customer and leave at
 * `services.whatsapp.broadcast_per_minute`, so the number's quality rating is protected.
 */
class BroadcastService
{
    /** Customers who got a broadcast this recently can be left out (Meta also caps marketing per customer). */
    public const RECENT_DAYS = 7;

    public const MAX_RECIPIENTS = 10000;

    /** Days after the broadcast in which a customer's order counts as a result. */
    public const RESULT_DAYS = 7;

    public function __construct(
        private WhatsappBudget $budget,
        private InboxService $inbox,
        private MessageTemplates $templates,
        private ContactService $contacts,
    ) {
    }

    /** @return array<string, string> where a variable's text comes from */
    public function sources(): array
    {
        return collect(MessageTemplates::PLACEHOLDERS)->except('producto')->all() + ['fixed' => 'Texto fijo (igual para todos)'];
    }

    /**
     * The template's variables with their default source: the customer field the Tribio
     * template was made from, else text the owner types.
     *
     * @return list<array{key: string, label: string, source: string, text: string}>
     */
    public function variables(WhatsappTemplate $template): array
    {
        $sources = $this->sources();

        return collect($template->variables())->map(function (string $variable) use ($template, $sources) {
            $param = $template->params && ctype_digit($variable) ? ($template->params[(int) $variable - 1] ?? null) : $variable;

            return [
                'key'    => $variable,
                'label'  => MessageTemplates::PLACEHOLDERS[$param] ?? 'Dato {{' . $variable . '}}',
                'source' => $param !== 'fixed' && isset($sources[$param]) ? $param : 'fixed',
                'text'   => '',
            ];
        })->values()->all();
    }

    /**
     * The customers a broadcast reaches and who is left out (no phone, no consent, recent).
     *
     * @param  array{stage?: ?string, tag?: ?string, segment?: ?string, skip_recent?: bool}  $filters
     * @return array{query: \Closure, matched: int, no_phone: int, no_consent: int, recent: int, recipients: int}
     */
    public function audience(Store $store, string $category, array $filters): array
    {
        $tag = mb_strtolower(trim((string) ($filters['tag'] ?? '')));
        $base = fn () => $store->contacts()
            ->when($filters['stage'] ?? null, fn ($q, $stage) => $q->where('lifecycle_stage', $stage))
            ->when($tag !== '', fn ($q) => $q->whereJsonContains('tags', $tag))
            ->inSegment($filters['segment'] ?? null);
        $withPhone = fn () => $base()->whereNotNull('phone')->where('phone', '!=', '');
        $consented = fn () => $withPhone()->when($category === 'MARKETING', fn ($q) => $q->where('accepts_marketing', true));
        $final = fn () => $consented()->when($filters['skip_recent'] ?? false, fn ($q) => $q->whereNotIn('id', WhatsappBroadcastRecipient::select('contact_id')
            ->where('status', 'queued')->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))
            ->whereHas('message', fn ($q) => $q->where('status', '!=', 'failed'))));

        $counts = [$base()->count(), $withPhone()->count(), $consented()->count(), $final()->count()];

        return [
            'query'      => $final,
            'matched'    => $counts[0],
            'no_phone'   => $counts[0] - $counts[1],
            'no_consent' => $counts[1] - $counts[2],
            'recent'     => $counts[2] - $counts[3],
            'recipients' => $counts[3],
        ];
    }

    /**
     * Variable → where its text comes from, completed with the template's defaults.
     *
     * @return array<string, array{source: string, text: string}>
     */
    public function mapping(WhatsappTemplate $template, array $input): array
    {
        $sources = $this->sources();

        return collect($this->variables($template))->mapWithKeys(fn ($variable) => [$variable['key'] => [
            'source' => isset($sources[$input[$variable['key']]['source'] ?? null]) ? $input[$variable['key']]['source'] : $variable['source'],
            'text'   => mb_substr(trim((string) ($input[$variable['key']]['text'] ?? '')), 0, 1000),
        ]])->all();
    }

    /**
     * What the owner sees before sending, and what stops the broadcast.
     *
     * @param  array  $data  filters + `variables` (raw input) + `later` (scheduled for another day)
     */
    public function estimate(Store $store, WhatsappConnection $connection, WhatsappTemplate $template, array $data, ?int $except = null): array
    {
        $audience = $this->audience($store, $template->category, $data);
        $mapping = $this->mapping($template, $data['variables'] ?? []);
        $rate = $connection->rate($template->category);
        $cost = round($audience['recipients'] * $rate, 2);
        $budget = $this->budget->summary($connection, $except);
        $limit = $this->budget->limit($connection);
        $used = $this->budget->limitUsed($connection);
        $left = $limit === null ? null : max(0, $limit - $used);

        $problems = [];
        foreach ($mapping as $variable => $map) {
            if ($map['source'] === 'fixed' && $map['text'] === '') {
                $problems[] = 'Escribe el texto del dato {{' . $variable . '}}.';
            }
        }
        if ($audience['recipients'] === 0) {
            $problems[] = match (true) {
                $audience['recent'] > 0 => 'Los clientes de ese grupo ya recibieron una difusión en los últimos ' . self::RECENT_DAYS . ' días. Desmarca esa opción si igual quieres enviarla.',
                $template->category === 'MARKETING' && $audience['no_consent'] > 0 => 'Ningún cliente de ese grupo aceptó recibir ofertas: con una plantilla de marketing no se le puede escribir.',
                default => 'Ningún cliente con celular cumple ese filtro.',
            };
        } elseif ($audience['recipients'] > self::MAX_RECIPIENTS) {
            $problems[] = 'Por ahora una difusión llega a ' . number_format(self::MAX_RECIPIENTS) . ' clientes como máximo. Filtra por etapa o etiqueta.';
        }
        if ($budget['budget'] === null) {
            $problems[] = 'Define tu tope mensual de gasto en WhatsApp antes de enviar difusiones.';
        } elseif ($cost > $budget['available']) {
            $problems[] = sprintf('Costaría ~S/ %s y te quedan S/ %s de tu tope mensual (S/ %s). Reduce el público o sube el tope.',
                number_format($cost, 2), number_format($budget['available'], 2), number_format($budget['budget'], 2));
        }
        if ($limit !== null && $audience['recipients'] > (($data['later'] ?? false) ? $limit : $left)) {
            $problems[] = ($data['later'] ?? false)
                ? sprintf('Meta te deja escribir primero a %s clientes cada 24 h: divide el público.', number_format($limit))
                : sprintf('Meta te deja escribir primero a %s clientes cada 24 h y ya usaste %s. Reduce el público a %s o prográmala para mañana.',
                    number_format($limit), number_format($used), number_format($left));
        }

        $sample = $audience['recipients'] > 0 ? $audience['query']()->orderBy('id')->first() : null;
        [$values] = $sample ? $this->valuesFor($sample, $mapping, true) : [[]];

        return [
            'matched'    => $audience['matched'],
            'no_phone'   => $audience['no_phone'],
            'no_consent' => $audience['no_consent'],
            'recent'     => $audience['recent'],
            'recipients' => $audience['recipients'],
            'category'   => $template->category_label,
            'rate'       => $rate,
            'cost'       => $cost,
            'budget'     => $budget,
            'limit'      => $limit,
            'limit_used' => $used,
            'sample'     => $sample ? ['name' => $sample->name, 'text' => $template->render($values)] : null,
            'problems'   => $problems,
            'mapping'    => $mapping,
        ];
    }

    /** Creates the broadcast; sent at once (on the queue) or at `scheduled_at` (Lima time). */
    public function create(Store $store, WhatsappConnection $connection, WhatsappTemplate $template, array $data, ?int $userId): WhatsappBroadcast
    {
        if (!$connection->isConnected()) {
            throw ValidationException::withMessages(['broadcast' => 'Tu número de WhatsApp no está conectado. Revisa la conexión.']);
        }
        if ($template->connection_id !== $connection->id || !$template->isSendable()) {
            throw ValidationException::withMessages(['template_id' => 'Esa plantilla no está aprobada por Meta o no se puede enviar desde aquí.']);
        }

        $later = filled($data['scheduled_at'] ?? null);
        $estimate = $this->estimate($store, $connection, $template, $data + ['later' => $later]);
        if ($estimate['problems']) {
            throw ValidationException::withMessages(['broadcast' => $estimate['problems'][0]]);
        }

        $broadcast = WhatsappBroadcast::create([
            'store_id'         => $store->id,
            'connection_id'    => $connection->id,
            'template_id'      => $template->id,
            'user_id'          => $userId,
            'name'             => trim($data['name']),
            'template_name'    => $template->name,
            'category'         => $template->category,
            'audience'         => [
                'stage'       => $data['stage'] ?? null,
                'tag'         => filled($data['tag'] ?? null) ? mb_strtolower(trim($data['tag'])) : null,
                'segment'     => $data['segment'] ?? null,
                'skip_recent' => (bool) ($data['skip_recent'] ?? false),
            ],
            'variables'        => $estimate['mapping'],
            'status'           => 'scheduled',
            'recipients_count' => $estimate['recipients'],
            'rate'             => $estimate['rate'],
            'estimated_cost'   => $estimate['cost'],
            'scheduled_at'     => $later ? Carbon::parse($data['scheduled_at'], WhatsappBudget::TIMEZONE)->utc() : now(),
        ]);

        if (!$later) {
            StartWhatsappBroadcast::dispatch($broadcast->id);
        }

        return $broadcast;
    }

    /** Due scheduled broadcasts (called every minute by `whatsapp:broadcasts`). */
    public function dispatchDue(): int
    {
        $ids = WhatsappBroadcast::where('status', 'scheduled')->where('scheduled_at', '<=', now())->pluck('id');
        $ids->each(fn ($id) => StartWhatsappBroadcast::dispatch($id));

        return $ids->count();
    }

    /**
     * Queues one message per customer. Audience, consent, budget and limit are checked again:
     * hours may have passed since it was scheduled.
     */
    public function start(WhatsappBroadcast $broadcast): void
    {
        if (!WhatsappBroadcast::whereKey($broadcast->id)->where('status', 'scheduled')->update(['status' => 'sending', 'updated_at' => now()])) {
            return; // already started or cancelled
        }
        $broadcast->refresh();
        $connection = $broadcast->connection;
        $store = $connection->store;
        $template = $broadcast->template;
        $stop = fn (string $reason) => $broadcast->forceFill(['status' => 'failed', 'error' => mb_substr($reason, 0, 255), 'finished_at' => now()])->save();

        if (!$connection->isConnected()) {
            $stop('Tu número de WhatsApp no estaba conectado a la hora del envío.');

            return;
        }
        if (!$template || !$template->isSendable()) {
            $stop('La plantilla ya no está aprobada por Meta.');

            return;
        }
        $estimate = $this->estimate($store, $connection, $template, $broadcast->audience + ['variables' => $broadcast->variables], $broadcast->id);
        if ($estimate['problems']) {
            $stop($estimate['problems'][0]);

            return;
        }

        $perMinute = max(1, (int) config('services.whatsapp.broadcast_per_minute', 60));
        $queued = 0;
        $skipped = 0;
        $this->audience($store, $template->category, $broadcast->audience)['query']()
            ->chunkById(200, function ($contacts) use ($broadcast, $connection, $store, $template, $perMinute, &$queued, &$skipped) {
                DB::transaction(function () use ($contacts, $broadcast, $connection, $store, $template, $perMinute, &$queued, &$skipped) {
                    foreach ($contacts as $contact) {
                        $contact->setRelation('store', $store);
                        [$values, $missing] = $this->valuesFor($contact, $broadcast->variables);
                        if ($missing) {
                            WhatsappBroadcastRecipient::create(['broadcast_id' => $broadcast->id, 'contact_id' => $contact->id, 'status' => 'skipped', 'reason' => "Sin dato: {$missing}"]);
                            $skipped++;
                            continue;
                        }

                        $conversation = $this->inbox->conversationFor($connection, $contact);
                        $body = $template->render($values);
                        $message = WhatsappMessage::create([
                            'store_id'        => $store->id,
                            'conversation_id' => $conversation->id,
                            'broadcast_id'    => $broadcast->id,
                            'direction'       => 'out',
                            'type'            => 'template',
                            'body'            => $body,
                            'payload'         => ['template_id' => $template->id, 'template' => $template->name, 'language' => $template->language, 'values' => $values],
                            'status'          => 'queued',
                            'user_id'         => $broadcast->user_id,
                            'wa_timestamp'    => now(),
                        ]);
                        $conversation->forceFill(['last_message_at' => now(), 'last_message_preview' => Str::limit('Tú: ' . $body, 150)])->save();
                        WhatsappBroadcastRecipient::create(['broadcast_id' => $broadcast->id, 'contact_id' => $contact->id, 'message_id' => $message->id, 'status' => 'queued']);
                        $this->contacts->log($contact, 'whatsapp', "Difusión «{$broadcast->name}» por WhatsApp: " . Str::limit($body, 120), $broadcast->user_id);

                        // Spread over time: Meta and the number's quality rating dislike bursts.
                        SendWhatsappMessage::dispatch($message->id)->delay(now()->addSeconds(intdiv($queued * 60, $perMinute)))->afterCommit();
                        $queued++;
                    }
                });
            });

        $broadcast->forceFill([
            'recipients_count' => $queued,
            'skipped_count'    => $skipped,
            'estimated_cost'   => round($queued * (float) $broadcast->rate, 2),
            'started_at'       => now(),
        ])->save();
        $this->settle($broadcast->id);
    }

    /** Done once no message of it is waiting in the queue. */
    public function settle(int $broadcastId): void
    {
        WhatsappBroadcast::whereKey($broadcastId)->where('status', 'sending')->whereNotNull('started_at')
            ->whereDoesntHave('messages', fn ($q) => $q->where('status', 'queued'))
            ->update(['status' => 'done', 'finished_at' => now(), 'updated_at' => now()]);
    }

    /** Messages still in the queue never leave; the ones already sent stay. */
    public function cancel(WhatsappBroadcast $broadcast): void
    {
        if (!$broadcast->isOpen()) {
            return;
        }
        $broadcast->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
        $broadcast->messages()->where('status', 'queued')
            ->update(['status' => 'failed', 'error' => 'Cancelaste la difusión antes de que saliera este mensaje.', 'updated_at' => now()]);
    }

    /** Delivery, cost, replies and orders that followed. */
    public function stats(WhatsappBroadcast $broadcast): array
    {
        $counts = $broadcast->messages()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $replied = $broadcast->messages()->whereExists(fn ($q) => $q->from('whatsapp_messages as answer')
            ->whereColumn('answer.conversation_id', 'whatsapp_messages.conversation_id')
            ->where('answer.direction', 'in')
            ->whereColumn('answer.wa_timestamp', '>=', 'whatsapp_messages.wa_timestamp'))
            ->distinct()->count('conversation_id');

        $orders = $broadcast->started_at
            ? Order::where('store_id', $broadcast->store_id)
                ->whereIn('contact_id', $broadcast->recipients()->where('status', 'queued')->select('contact_id'))
                ->where('created_at', '>', $broadcast->started_at)->where('created_at', '<=', $broadcast->started_at->copy()->addDays(self::RESULT_DAYS))
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->selectRaw('COUNT(*) as total, COALESCE(SUM(total), 0) as revenue')->first()
            : null;

        return [
            'queued'    => (int) ($counts['queued'] ?? 0),
            'sent'      => (int) (($counts['sent'] ?? 0) + ($counts['delivered'] ?? 0) + ($counts['read'] ?? 0)),
            'delivered' => (int) (($counts['delivered'] ?? 0) + ($counts['read'] ?? 0)),
            'read'      => (int) ($counts['read'] ?? 0),
            'failed'    => (int) ($counts['failed'] ?? 0),
            'cost'      => round((float) $broadcast->messages()->sum('cost'), 2),
            'replied'   => $replied,
            'orders'    => (int) ($orders->total ?? 0),
            'revenue'   => round((float) ($orders->revenue ?? 0), 2),
            'errors'    => $broadcast->messages()->where('status', 'failed')->whereNotNull('error')
                ->selectRaw('error, COUNT(*) as total')->groupBy('error')->orderByDesc('total')->limit(5)->pluck('total', 'error'),
        ];
    }

    /**
     * Each variable's text for one customer; a customer missing a field (no order yet…) is skipped.
     *
     * @return array{0: array<string, string>, 1: ?string} values, label of the missing field
     */
    private function valuesFor(Contact $contact, array $mapping, bool $preview = false): array
    {
        $fields = null;
        $values = [];
        foreach ($mapping as $variable => $map) {
            if ($map['source'] === 'fixed') {
                $text = $map['text'];
            } else {
                $fields ??= $this->templates->values($contact);
                $text = (string) ($fields[$map['source']] ?? '');
            }
            // Meta refuses line breaks, tabs and long runs of spaces inside a variable.
            $text = mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, 1000);
            if ($text === '') {
                if (!$preview) {
                    return [$values, $this->sources()[$map['source']] ?? $map['source']];
                }
                $text = '[' . ($this->sources()[$map['source']] ?? $variable) . ']';
            }
            $values[$variable] = $text;
        }

        return [$values, null];
    }
}
