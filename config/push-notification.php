<?php

return [

    'ios'     => [
        'environment' => 'development',
        'certificate' => '/path/to/certificate.pem',
        'passPhrase'  => 'password',
        'service'     => 'apns',
    ],
    'android' => [
        'environment' => 'production',
        'apiKey'      => 'yourAPIKey',
        'service'     => 'gcm',
    ],

];
