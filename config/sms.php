<?php

return [
    'default' => env('SMS_PROVIDER', 'local'),

    'providers' => [
        'local' => [],
        'whatsapp' => [
            'app_id' => env('WHATSAPP_APP_ID'),
            'app_secret' => env('WHATSAPP_APP_SECRET'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'default_graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v22.0'),
        ],
        'smsmasivos' => [],
    ],

    'verification' => [
        'expires_in_minutes' => (int) env('SMS_VERIFICATION_EXPIRES_MINUTES', 5),
        'resend_cooldown_minutes' => (int) env('SMS_VERIFICATION_RESEND_COOLDOWN_MINUTES', 2),
        'max_failed_attempts' => (int) env('SMS_VERIFICATION_MAX_FAILED_ATTEMPTS', 5),
    ],

    'templates' => [
        'verification' => env('SMS_VERIFICATION_TEMPLATE', 'Your code is {code}. It expires in {expires} minutes.'),
    ],
];
