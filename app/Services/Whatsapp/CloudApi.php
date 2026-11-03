<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API (graph.facebook.com). The access token travels in the Authorization
 * header, never in the URL: HTTP client errors quote the URL and end up in logs.
 */
class CloudApi
{
    /** Meta throttling codes: the same request may work a bit later. */
    public const RETRYABLE_CODES = [4, 80007, 130429, 131016];

    /** Meta error codes the owner can act on, in their words. */
    private const MESSAGES = [
        190    => 'El token de acceso no es válido o venció. Genera uno permanente (usuario del sistema) en Meta.',
        200    => 'El token no tiene permiso para este número (whatsapp_business_messaging).',
        368    => 'Meta bloqueó temporalmente el número por sus políticas.',
        131026 => 'Este número no puede recibir el mensaje (no tiene WhatsApp o no aceptó los términos nuevos).',
        131047 => 'Pasaron más de 24 h desde el último mensaje del cliente: solo se puede escribir con una plantilla aprobada.',
        131051 => 'Ese tipo de mensaje no es compatible.',
        131056 => 'Demasiados mensajes seguidos a este cliente. Espera un momento.',
        130429 => 'Meta está limitando los envíos de este número. Se reintentará.',
        133010 => 'El número aún no está registrado en la Cloud API.',
        131049 => 'Meta no entregó este mensaje de marketing para no saturar al cliente. Inténtalo otro día.',
        132000 => 'La cantidad de datos no coincide con la plantilla aprobada.',
        132001 => 'La plantilla no existe en Meta o aún no está aprobada en ese idioma.',
        132005 => 'El texto con los datos completados es demasiado largo.',
        132007 => 'El contenido infringe las políticas de WhatsApp.',
        132012 => 'Algún dato de la plantilla tiene un formato que Meta no acepta.',
        132015 => 'Meta pausó esta plantilla por su baja calidad.',
        132016 => 'Meta desactivó esta plantilla.',
        2388023 => 'Meta aún está eliminando una plantilla con ese nombre. Inténtalo en unos minutos.',
        2388024 => 'Ya existe una plantilla con ese nombre e idioma en tu cuenta de Meta.',
        2388299 => 'Meta no acepta que el mensaje empiece o termine con un dato variable.',
    ];

    /** Largest media file Tribio downloads into the inbox (Meta allows up to 100 MB for documents). */
    public const MAX_MEDIA_BYTES = 16 * 1024 * 1024;

    /** @return array{display_phone_number?: string, verified_name?: string, quality_rating?: string} */
    public function phoneNumber(WhatsappConnection $connection): array
    {
        return $this->call($connection, fn (PendingRequest $http) => $http->get(
            $this->url(rawurlencode($connection->phone_number_id)),
            ['fields' => 'display_phone_number,verified_name,quality_rating'],
        ));
    }

    /**
     * Meta's messaging limit (customers the business may write first to per moving 24 h),
     * shared by the business portfolio. Replaces the deprecated `messaging_limit_tier`.
     */
    public function messagingLimit(WhatsappConnection $connection): mixed
    {
        return $this->call($connection, fn (PendingRequest $http) => $http->get(
            $this->url(rawurlencode($connection->phone_number_id)),
            ['fields' => 'whatsapp_business_manager_messaging_limit'],
        ))['whatsapp_business_manager_messaging_limit'] ?? null;
    }

    /** Without this subscription Meta sends no webhooks for the WABA to the app. */
    public function subscribeApp(WhatsappConnection $connection): void
    {
        $this->call($connection, fn (PendingRequest $http) => $http->post($this->url(rawurlencode($connection->waba_id) . '/subscribed_apps')));
    }

    /** @return string the message id (wamid) */
    public function sendText(WhatsappConnection $connection, WhatsappConversation $conversation, string $body): string
    {
        $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $body]];
        // Phone when known; otherwise the business-scoped user ID (customers with a username).
        $payload += $conversation->wa_id ? ['to' => $conversation->wa_id] : ['recipient' => $conversation->wa_user_id];

        $answer = $this->call($connection, fn (PendingRequest $http) => $http->post($this->url(rawurlencode($connection->phone_number_id) . '/messages'), $payload));

        $wamid = $answer['messages'][0]['id'] ?? null;
        if (!$wamid) {
            throw new CloudApiException('Meta no devolvió el identificador del mensaje.', 502);
        }

        return $wamid;
    }

    /**
     * Every template of the WABA, following Meta's pagination.
     *
     * @return list<array<string, mixed>>
     */
    public function templates(WhatsappConnection $connection): array
    {
        $templates = [];
        $after = null;
        for ($page = 0; $page < 20; $page++) {
            $answer = $this->call($connection, fn (PendingRequest $http) => $http->get($this->url(rawurlencode($connection->waba_id) . '/message_templates'), array_filter([
                'fields' => 'id,name,language,status,category,components,rejected_reason',
                'limit'  => 100,
                'after'  => $after,
            ])));
            array_push($templates, ...($answer['data'] ?? []));
            $after = $answer['paging']['cursors']['after'] ?? null;
            if (empty($answer['paging']['next']) || !$after) {
                break;
            }
        }

        return $templates;
    }

    /** @return array{id?: string, status?: string, category?: string} */
    public function createTemplate(WhatsappConnection $connection, array $template): array
    {
        return $this->call($connection, fn (PendingRequest $http) => $http->post($this->url(rawurlencode($connection->waba_id) . '/message_templates'), $template));
    }

    /** Rejected (or approved) templates are corrected in place; Meta reviews them again. */
    public function editTemplate(WhatsappConnection $connection, string $metaId, array $components): array
    {
        return $this->call($connection, fn (PendingRequest $http) => $http->post($this->url(rawurlencode($metaId)), ['components' => $components]));
    }

    public function deleteTemplate(WhatsappConnection $connection, WhatsappTemplate $template): void
    {
        $this->call($connection, fn (PendingRequest $http) => $http->delete($this->url(rawurlencode($connection->waba_id) . '/message_templates')
            . '?' . http_build_query(array_filter(['name' => $template->name, 'hsm_id' => $template->meta_id]))));
    }

    /**
     * @param  array<string, string>  $values  variable ("1" or a name) → text
     * @return string the message id (wamid)
     */
    public function sendTemplate(WhatsappConnection $connection, WhatsappConversation $conversation, WhatsappTemplate $template, array $values): string
    {
        $parameters = collect($template->variables())->map(fn ($variable) => ['type' => 'text', 'text' => (string) ($values[$variable] ?? '')]
            + ($template->isNamed() ? ['parameter_name' => $variable] : []))->all();

        $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'type' => 'template', 'template' => array_filter([
            'name'       => $template->name,
            'language'   => ['code' => $template->language],
            'components' => $parameters ? [['type' => 'body', 'parameters' => $parameters]] : null,
        ])];
        $payload += $conversation->wa_id ? ['to' => $conversation->wa_id] : ['recipient' => $conversation->wa_user_id];

        $answer = $this->call($connection, fn (PendingRequest $http) => $http->post($this->url(rawurlencode($connection->phone_number_id) . '/messages'), $payload));

        return $answer['messages'][0]['id'] ?? throw new CloudApiException('Meta no devolvió el identificador del mensaje.', 502);
    }

    /** @return array{url?: string, mime_type?: string, file_size?: int} where to fetch an incoming file (the URL lasts 5 minutes) */
    public function media(WhatsappConnection $connection, string $mediaId): array
    {
        return $this->call($connection, fn (PendingRequest $http) => $http->get($this->url(rawurlencode($mediaId))));
    }

    /** The file's bytes; the media URL also needs the token. */
    public function download(WhatsappConnection $connection, string $url): string
    {
        $response = Http::withToken((string) $connection->access_token)->timeout(60)->get($url);
        if ($response->failed()) {
            throw new CloudApiException('No se pudo descargar el archivo de Meta (HTTP ' . $response->status() . ').', $response->status());
        }
        if (strlen($response->body()) > self::MAX_MEDIA_BYTES) {
            throw new CloudApiException('El archivo es demasiado grande.', 413);
        }

        return $response->body();
    }

    /** An owner-readable reason for a Meta error code (webhook statuses use the same codes). */
    public static function describeCode(?int $code, ?string $fallback = null): string
    {
        return self::MESSAGES[$code] ?? ($fallback ?: 'Meta rechazó la solicitud.');
    }

    public static function describe(\Throwable $e, WhatsappConnection $connection): string
    {
        $message = $e instanceof ConnectionException ? 'No se pudo conectar con Meta. Inténtalo de nuevo.' : $e->getMessage();
        foreach ([(string) $connection->access_token, (string) $connection->app_secret] as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '***', $message);
            }
        }

        return mb_substr($message, 0, 255);
    }

    private function call(WhatsappConnection $connection, \Closure $send): array
    {
        $http = Http::withToken((string) $connection->access_token)->timeout(15)->acceptJson()->asJson();
        $response = $send($http);

        if ($response->failed()) {
            // Template errors carry the precise reason in error_subcode (2388xxx); other subcodes add nothing.
            $code = is_numeric($response->json('error.code')) ? (int) $response->json('error.code') : null;
            $subcode = is_numeric($response->json('error.error_subcode')) ? (int) $response->json('error.error_subcode') : null;
            $code = $subcode && isset(self::MESSAGES[$subcode]) ? $subcode : $code;
            throw new CloudApiException(
                self::describeCode($code, $response->json('error.error_user_msg') ?: $response->json('error.message') ?: 'Meta respondió con el código HTTP ' . $response->status() . '.'),
                $response->status(),
                $code,
            );
        }

        return $response->json() ?? [];
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/' . config('services.meta.graph_version', 'v26.0') . '/' . $path;
    }
}
