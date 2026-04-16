<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'capacitor://localhost',
        'https://localhost',
        'http://localhost:8080',
        'http://localhost:8081',
        'http://127.0.0.1:8080',
        'http://127.0.0.1:8081',
        'https://carpoolear.com.ar',
        'https://www.carpoolear.com.ar',
        'https://carpoolear.gonzalogm.com',
        'https://www.carpoolear.gonzalogm.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-OSRM-Proxy-Cache', 'X-OSRM-Proxy-Error'],

    'max_age' => 0,

    'supports_credentials' => true,

];
