<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(\App\Http\Middleware\HandleCustomDomain::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
        $middleware->encryptCookies(except: [
            'user_country',
            'store_currency',
            'store_lang',
            'googtrans',
            // Written in the browser (Meta Pixel / resources/js/marketing.js), read at
            // checkout by App\Services\Marketing\TrackingContext.
            '_fbp',
            '_fbc',
            'tribio_consent',
            'tribio_attr',
        ]);
        $middleware->validateCsrfTokens(except: [
            '/plan/webhook',
            'tienda/*/checkout',
            'checkout',
            'customer/*',
            'tienda/*/checkout/paypal/webhook',
            'checkout/paypal/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['flow_api_key', 'flow_secret_key']);
        //
    })->create();
