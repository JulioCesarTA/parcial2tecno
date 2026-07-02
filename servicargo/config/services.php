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

    /*
    | PagoFácil (pasarela de pago QR). Credenciales y parámetros de integración.
    | Regla del proyecto: nada de credenciales tipeadas en código, todo en .env.
    */
    'pagofacil' => [
        'base_url' => env('PAGOFACIL_BASE_URL', 'https://masterqr.pagofacil.com.bo/api/services/v2'),
        'token_service' => env('PAGOFACIL_TOKEN_SERVICE'),
        'token_secret' => env('PAGOFACIL_TOKEN_SECRET'),
        'payment_method_id' => env('PAGOFACIL_PAYMENT_METHOD_ID', '34'),
        'callback_url' => env('PAGOFACIL_CALLBACK_URL', 'https://tecnoweb-servicargo.abrdns.com/callback'),
        'currency' => (int) env('PAGOFACIL_CURRENCY', 2),
        'document_type' => (int) env('PAGOFACIL_DOCUMENT_TYPE', 1),
        // Verificación TLS. En producción déjalo en true; en local (Windows sin CA
        // bundle) puede ponerse en false para poder probar contra la pasarela.
        'verify_ssl' => filter_var(env('PAGOFACIL_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    ],

];
