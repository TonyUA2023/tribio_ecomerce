<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Store;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCustomDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $mainDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';

        $excludedDomains = ['localhost', '127.0.0.1', '::1'];
        if (!in_array($mainDomain, $excludedDomains)) {
            $excludedDomains[] = $mainDomain;
        }

        $isExcluded = false;
        foreach ($excludedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                $isExcluded = true;
                break;
            }
        }

        if (!$isExcluded) {
            // Buscar la tienda por dominio personalizado
            $store = Store::where('custom_domain', $host)->active()->first();

            if (!$store) {
                abort(404, 'Tienda no encontrada o inactiva.');
            }

            $request->attributes->set('store', $store);

            // Redirigir el Dashboard, Login, Registro y Admin al dominio principal
            $path = $request->path();
            if (preg_match('/^(dashboard|login|registro|logout|admin|api)\b/i', $path)) {
                $mainDomainUrl = rtrim(config('app.url'), '/');
                return redirect()->to($mainDomainUrl . $request->getRequestUri());
            }

            // Inyectar el parámetro 'slug' en la ruta para compatibilidad con StoreController
            if ($request->route()) {
                $request->route()->setParameter('slug', $store->slug);
            }

            // Procesar la petición y obtener la respuesta
            $response = $next($request);

            // Reescribir enlaces en la respuesta de salida para remover el prefijo /tienda/{slug} (soporta HTML y JSON)
            if ($response instanceof Response) {
                $contentType = $response->headers->get('Content-Type');
                $isHtml = $contentType && str_contains($contentType, 'text/html');
                $isJson = $contentType && str_contains($contentType, 'application/json');

                if (($isHtml || $isJson) && method_exists($response, 'getContent')) {
                    $content = $response->getContent();
                    $slug = $store->slug;

                    $mainDomainUrl = rtrim(config('app.url'), '/');
                    $customDomainUrl = rtrim($request->getSchemeAndHttpHost(), '/');

                    // 1. Reemplazar URLs absolutas que apunten al dominio principal con el prefijo de la tienda
                    $content = str_replace("{$mainDomainUrl}/tienda/{$slug}/", "{$customDomainUrl}/", $content);
                    $content = str_replace("{$mainDomainUrl}/tienda/{$slug}", "{$customDomainUrl}/", $content);

                    // 2. Reemplazar enlaces relativos
                    $content = str_replace("/tienda/{$slug}/", "/", $content);
                    $content = preg_replace('#/tienda/' . preg_quote($slug, '#') . '(\b|$)#', '/', $content);

                    $response->setContent($content);
                }
            }

            return $response;
        }

        return $next($request);
    }
}
