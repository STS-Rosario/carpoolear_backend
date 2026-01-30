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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    
    'mercadopago' => [
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'qr_payment_access_token' => env('MERCADO_PAGO_QR_PAYMENT_ACCESS_TOKEN'),
        'qr_payment_client_id' => env('MERCADO_PAGO_QR_PAYMENT_CLIENT_ID'),
        'qr_payment_client_secret' => env('MERCADO_PAGO_QR_PAYMENT_CLIENT_SECRET'),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
        'reference_salt' => env('MERCADO_PAGO_REFERENCE_SALT', 'carpoolear_2024_secure_salt'),
        'client_id' => env('MERCADO_PAGO_CLIENT_ID'),
        'client_secret' => env('MERCADO_PAGO_CLIENT_SECRET'),
        'oauth_redirect_uri' => env('MERCADO_PAGO_OAUTH_REDIRECT_URI'),
        'oauth_frontend_redirect' => env('MERCADO_PAGO_OAUTH_FRONTEND_REDIRECT', env('APP_URL')),
        'oauth_pkce_enabled' => env('MERCADO_PAGO_OAUTH_PKCE_ENABLED', false),
        'oauth_auth_url_base' => env('MERCADO_PAGO_OAUTH_AUTH_URL_BASE', 'https://auth.mercadopago.com'),
    ],

    'whatsapp' => [
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'your_verify_token_here'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    ],
];
