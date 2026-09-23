<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mercadopago' => [
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN', 'TEST-7253579047247781-062603-f38b2d713a21644ca7cb4b6b69fa0670-137901037'),
        'public_key'   => env('MERCADO_PAGO_PUBLIC_KEY', 'TEST-238d2f59-a292-4217-bc1a-64152df6e5ab'),
    ],

    // Culqi — used for Tribio's own subscription billing (charging store owners their
    // monthly plan fee). Unrelated to `stores.gateway_*`, which is a store's own checkout
    // gateway for charging its customers. Get these from CulqiPanel > Desarrollo > API Keys
    // after creating a Culqi account (a personal DNI is enough to register, no RUC required).
    'culqi' => [
        'public_key' => env('CULQI_PUBLIC_KEY'),
        'secret_key' => env('CULQI_SECRET_KEY'),
    ],

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
    ],

    // Google Sign-In for Tribio Pass (web only — see tribio_brain vault). One OAuth
    // client, "Web application" type, from Google Cloud Console > APIs & Services >
    // Credentials. Authorized redirect URI must match GOOGLE_REDIRECT_URI exactly.
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // Meta (Facebook/Instagram) — Conversions API. Each store brings its own Pixel and
    // token (Dashboard → Marketing); only the Graph API version is global. Meta retires
    // a version about two years after release (v23.0 died in June 2026): bump this pin.
    'meta' => [
        'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),
    ],

];
