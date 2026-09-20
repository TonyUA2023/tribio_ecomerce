<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

/** Safe checkout diagnostics: never return provider messages or credentials to a buyer. */
class FlowFailure
{
    public static function details(Throwable $error, Store $store): array
    {
        $details = ['exception' => get_class($error), 'kind' => 'flow_internal_error'];
        if ($error instanceof ConnectionException) {
            $details['kind'] = 'flow_connection_error';
            if (preg_match('/cURL error (\d+)/', $error->getMessage(), $match)) {
                $details['curl_code'] = (int) $match[1];
            }
        } elseif ($error instanceof RequestException) {
            $details['kind'] = 'flow_api_rejected';
            $details['http_status'] = $error->response->status();
            $code = $error->response->json('code');
            $details['flow_code'] = is_numeric($code) ? (int) $code : null;
            $message = $error->response->json('message');
            if (is_string($message)) {
                foreach ([$store->flow_api_key, $store->flow_secret_key] as $secret) {
                    if (filled($secret)) $message = str_replace($secret, '[redacted]', $message);
                }
                $message = preg_replace('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/i', '[email]', $message);
                $message = preg_replace('/\b(token|apiKey|secretKey|s)\s*[:=]\s*\S+/i', '$1=[redacted]', $message);
                $details['flow_message'] = mb_substr(preg_replace('/[\r\n\x00-\x1F]/', ' ', $message), 0, 300);
            }
        } elseif ($error->getMessage() === 'Invalid Flow payment response.') {
            $details['kind'] = 'flow_response_invalid';
        }
        return $details;
    }

    public static function buyerMessage(string $kind): string
    {
        return match ($kind) {
            'flow_connection_error' => 'No pudimos comunicarnos con Flow. Tu carrito sigue disponible. Si el problema continúa, contacta a la tienda.',
            'flow_api_rejected' => 'Flow no aceptó la solicitud de pago. Tu carrito sigue disponible; contacta a la tienda para revisar el pago.',
            default => 'No se pudo abrir Flow. Tu carrito sigue disponible; contacta a la tienda si el problema continúa.',
        };
    }
}
