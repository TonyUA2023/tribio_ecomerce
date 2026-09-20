<?php

namespace App\Console\Commands;

use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DiagnoseFlow extends Command
{
    protected $signature = 'flow:diagnose {store? : Slug de la tienda} {--network : Probar conexión sin credenciales ni crear pagos}';
    protected $description = 'Revisa configuración y conectividad de Flow sin cobrar ni mostrar claves';

    public function handle(): int
    {
        $ok = true;
        $url = config('app.url');
        $host = parse_url($url, PHP_URL_HOST);
        $publicHttps = parse_url($url, PHP_URL_SCHEME) === 'https' && $host
            && !in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $this->line('APP_URL público con HTTPS: ' . ($publicHttps ? 'sí' : 'no; corregir antes de recibir pagos reales'));
        $ok = $publicHttps;
        $mode = 'live';
        if ($slug = $this->argument('store')) {
            try {
                if (!Schema::hasColumns('stores', ['flow_api_key', 'flow_secret_key', 'flow_mode', 'flow_enabled', 'flow_currency'])
                    || !Schema::hasColumns('orders', ['flow_order_id', 'flow_token'])) {
                    $this->error('Falta la migración de Flow en esta base de datos.');
                    return self::FAILURE;
                }
                $store = Store::where('slug', $slug)->first();
                if (!$store) {
                    $this->error('No se encontró la tienda.');
                    return self::FAILURE;
                }
                $mode = $store->flow_mode;
                $active = $store->payment_gateway === 'flow' && $store->flow_enabled
                    && in_array($store->checkout_mode, ['card', 'mixed'], true);
                $keys = filled($store->flow_api_key) && filled($store->flow_secret_key);
                $this->line('Flow seleccionado y habilitado: ' . ($active ? 'sí' : 'no'));
                $this->line('Entorno: ' . $mode . ' | Moneda: ' . $store->flow_currency);
                $this->line('Ambas claves presentes y descifrables: ' . ($keys ? 'sí' : 'no'));
                $ok = $ok && $active && $keys;
            } catch (Throwable $e) {
                $this->error('No se pudo leer la configuración. Revisar conexión a BD, migraciones y APP_KEY. Tipo: ' . get_class($e));
                return self::FAILURE;
            }
        }
        if ($this->option('network')) {
            $endpoint = $mode === 'live' ? 'https://www.flow.cl/api/payment/create' : 'https://sandbox.flow.cl/api/payment/create';
            $this->line('Prueba POST sin claves ni comprador a ' . $endpoint);
            try {
                // Intentionally missing apiKey/signature: this cannot create a payment.
                $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(10)
                    ->withOptions(['allow_redirects' => false])->post($endpoint, []);
                $json = $response->json();
                $apiReachable = in_array($response->status(), [400, 401], true)
                    && is_array($json) && array_key_exists('code', $json) && array_key_exists('message', $json);
                $this->line('HTTP: ' . $response->status());
                if ($apiReachable) {
                    $this->info('Flow respondió al POST sin autenticar. Conectividad disponible; esto NO valida las claves ni los medios contratados.');
                } else {
                    $this->warn('Respuesta no esperada de la API. Revisar proxy, firewall y disponibilidad de Flow.');
                    $ok = false;
                }
            } catch (Throwable $e) {
                preg_match('/cURL error (\d+)/', $e->getMessage(), $match);
                $this->error('No se pudo contactar con Flow. Código cURL: ' . ($match[1] ?? 'no disponible') . '. Revisar DNS, TLS y salida HTTPS del servidor.');
                $ok = false;
            }
        }
        $this->line('Ejecuta este diagnóstico dentro del contenedor de producción para comprobar su entorno real.');
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
