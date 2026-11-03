<?php

namespace App\Services\Whatsapp;

use App\Models\Contact;
use App\Models\Store;
use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappTemplate;
use App\Services\Crm\MessageTemplates;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Meta-approved templates (Fase 3b). Tribio's templates (Fase 2d) are sent to review with
 * the store's name and link written in (they never change) and the customer's data as
 * variables {{1}}, {{2}}… whose placeholder is remembered, so sending fills them alone.
 * Templates made in Meta's own screens are synced too; their variables are typed by hand.
 */
class TemplateService
{
    public const LANGUAGE = 'es';

    /** Placeholders that are the same for every customer: written into the template text. */
    private const FIXED = ['tienda', 'link_tienda'];

    /** Sample values Meta requires to review a template with variables. */
    public const EXAMPLES = [
        'nombre'          => 'Rosa',
        'nombre_completo' => 'Rosa Quispe',
        'producto'        => 'Batido de fresa',
        'pedido'          => '#NV-1024',
        'saldo'           => 'S/ 70.00',
    ];

    private const REJECTION_REASONS = [
        'INVALID_FORMAT'       => 'El formato no es válido.',
        'ABUSIVE_CONTENT'      => 'Meta consideró el contenido no permitido.',
        'INCORRECT_CATEGORY'   => 'La categoría no corresponde: prueba como Marketing.',
        'TAG_CONTENT_MISMATCH' => 'El texto no coincide con la categoría elegida.',
        'PROMOTIONAL'          => 'Es promocional: envíala como Marketing.',
        'SCAM'                 => 'Meta la consideró engañosa.',
    ];

    public function __construct(private CloudApi $api, private MessageTemplates $templates)
    {
    }

    /** Mirrors the WABA's templates; the ones deleted in Meta disappear here too. */
    public function sync(WhatsappConnection $connection): int
    {
        try {
            $remote = $this->api->templates($connection);
        } catch (CloudApiException|ConnectionException $e) {
            throw ValidationException::withMessages(['template' => CloudApi::describe($e, $connection)]);
        }

        $kept = [];
        foreach ($remote as $template) {
            if (blank($template['name'] ?? null) || blank($template['language'] ?? null)) {
                continue;
            }
            $row = WhatsappTemplate::firstOrNew(['connection_id' => $connection->id, 'name' => $template['name'], 'language' => $template['language']]);
            $status = strtoupper((string) ($template['status'] ?? 'PENDING'));
            $row->fill([
                'store_id'        => $connection->store_id,
                'meta_id'         => isset($template['id']) ? (string) $template['id'] : $row->meta_id,
                'category'        => strtoupper((string) ($template['category'] ?? 'UTILITY')),
                'status'          => $status,
                'body'            => $this->bodyOf($template['components'] ?? []),
                'components'      => $template['components'] ?? [],
                'rejected_reason' => $status === 'REJECTED' ? $this->reason($template['rejected_reason'] ?? null) : null,
                'synced_at'       => now(),
            ])->save();
            $kept[] = $row->id;
        }

        WhatsappTemplate::where('connection_id', $connection->id)->whereNotNull('meta_id')->whereNotIn('id', $kept)->delete();

        return count($kept);
    }

    /**
     * Sends one of the store's templates to Meta's review (or the corrected text of a
     * rejected one, which Meta edits in place).
     */
    public function submit(WhatsappConnection $connection, Store $store, string $key, string $category): WhatsappTemplate
    {
        $source = $this->templates->find($store, $key);
        if (!$source) {
            throw ValidationException::withMessages(['template' => 'Esa plantilla ya no existe.']);
        }

        [$text, $params] = $this->convert($store, $source['body']);
        $component = ['type' => 'BODY', 'text' => $text]
            + ($params ? ['example' => ['body_text' => [array_map(fn ($p) => self::EXAMPLES[$p], $params)]]] : []);
        $name = 'tribio_' . str_replace('-', '_', $key);

        $existing = WhatsappTemplate::where('connection_id', $connection->id)->where('name', $name)->where('language', self::LANGUAGE)->first();
        if ($existing && !in_array($existing->status, ['REJECTED', 'PAUSED', 'APPROVED'], true)) {
            throw ValidationException::withMessages(['template' => 'Esta plantilla ya está en revisión en Meta.']);
        }

        try {
            $answer = $existing?->meta_id
                ? $this->api->editTemplate($connection, $existing->meta_id, [$component])
                : $this->api->createTemplate($connection, ['name' => $name, 'language' => self::LANGUAGE, 'category' => $category, 'components' => [$component]]);
        } catch (CloudApiException|ConnectionException $e) {
            throw ValidationException::withMessages(['template' => CloudApi::describe($e, $connection)]);
        }

        return WhatsappTemplate::updateOrCreate(
            ['connection_id' => $connection->id, 'name' => $name, 'language' => self::LANGUAGE],
            [
                'store_id'        => $store->id,
                'meta_id'         => isset($answer['id']) ? (string) $answer['id'] : $existing?->meta_id,
                'category'        => strtoupper((string) ($answer['category'] ?? $existing?->category ?? $category)),
                'status'          => strtoupper((string) ($answer['status'] ?? 'PENDING')),
                'body'            => $text,
                'components'      => [$component],
                'params'          => $params,
                'source_key'      => $key,
                'rejected_reason' => null,
                'synced_at'       => now(),
            ],
        );
    }

    /**
     * Tribio placeholders → Meta's text: fixed ones written in, customer ones as {{n}}.
     *
     * @return array{0: string, 1: list<string>}
     */
    public function convert(Store $store, string $body): array
    {
        $fixed = ['tienda' => $store->name, 'link_tienda' => $this->templates->storeUrl($store)];
        $params = [];
        $text = preg_replace_callback('/\{([a-z_]+)\}/u', function ($m) use ($fixed, &$params) {
            if (in_array($m[1], self::FIXED, true)) {
                return $fixed[$m[1]];
            }
            if (!isset(self::EXAMPLES[$m[1]])) {
                return $m[0];
            }
            $params[] = $m[1];

            return '{{' . count($params) . '}}';
        }, trim($body));

        if (preg_match('/^\{\{\d+\}\}|\{\{\d+\}\}$/u', $text)) {
            throw ValidationException::withMessages(['template' => 'Meta no acepta que el mensaje empiece o termine con un dato del cliente. Agrega una palabra antes o después.']);
        }
        if (mb_strlen($text) > 1024) {
            throw ValidationException::withMessages(['template' => 'El mensaje supera los 1024 caracteres que permite Meta.']);
        }

        return [$text, $params];
    }

    public function delete(WhatsappTemplate $template): void
    {
        if ($template->meta_id) {
            try {
                $this->api->deleteTemplate($template->connection, $template);
            } catch (CloudApiException|ConnectionException $e) {
                throw ValidationException::withMessages(['template' => CloudApi::describe($e, $template->connection)]);
            }
        }
        $template->delete();
    }

    /** Webhook `message_template_status_update`: approved, rejected, paused… */
    public function statusUpdate(WhatsappConnection $connection, array $value): void
    {
        $event = strtoupper((string) ($value['event'] ?? ''));
        $query = WhatsappTemplate::where('connection_id', $connection->id);
        $template = isset($value['message_template_id']) ? (clone $query)->where('meta_id', (string) $value['message_template_id'])->first() : null;
        $template ??= (clone $query)->where('name', (string) ($value['message_template_name'] ?? ''))
            ->where('language', (string) ($value['message_template_language'] ?? ''))->first();
        if (!$template || $event === '') {
            return; // unknown here: the next sync brings it
        }

        if ($event === 'DELETED') {
            $template->delete();

            return;
        }

        $template->forceFill([
            'status'          => $event,
            'rejected_reason' => $event === 'REJECTED' ? $this->reason($value['reason'] ?? null) : null,
            'synced_at'       => now(),
        ])->save();
    }

    /**
     * The sendable templates of the conversation's number, each with its variables
     * prefilled for this customer and whether consent blocks it.
     */
    public function forConversation(WhatsappConversation $conversation): Collection
    {
        $values = $conversation->contact ? $this->templates->values($conversation->contact) : [];

        return WhatsappTemplate::where('connection_id', $conversation->connection_id)->where('status', 'APPROVED')->orderBy('name')->get()
            ->filter->isSendable()
            ->map(fn (WhatsappTemplate $template) => [
                'id'        => $template->id,
                'name'      => $template->name,
                'category'  => $template->category_label,
                'body'      => $template->body,
                'blocked'   => $template->category === 'MARKETING' && !$conversation->contact?->accepts_marketing
                    ? 'No aceptó recibir ofertas (Ley 29733).' : null,
                'variables' => $this->variables($template, $values),
            ])->values();
    }

    /** @return list<array{key: string, label: string, value: string}> */
    public function variables(WhatsappTemplate $template, array $values = []): array
    {
        return collect($template->variables())->map(function (string $variable) use ($template, $values) {
            $source = $template->params && ctype_digit($variable)
                ? ($template->params[(int) $variable - 1] ?? null)
                : (isset(MessageTemplates::PLACEHOLDERS[$variable]) ? $variable : null);

            return [
                'key'   => $variable,
                'label' => $source ? MessageTemplates::PLACEHOLDERS[$source] : 'Dato {{' . $variable . '}}',
                'value' => $source ? (string) ($values[$source] ?? '') : '',
            ];
        })->all();
    }

    private function bodyOf(array $components): string
    {
        foreach ($components as $component) {
            if (strtoupper($component['type'] ?? '') === 'BODY') {
                return (string) ($component['text'] ?? '');
            }
        }

        return '';
    }

    private function reason(?string $reason): ?string
    {
        if (blank($reason) || strtoupper($reason) === 'NONE') {
            return null;
        }

        return mb_substr(self::REJECTION_REASONS[strtoupper($reason)] ?? $reason, 0, 255);
    }
}
