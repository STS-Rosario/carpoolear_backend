<?php

return [

    'ios'     => [
        'environment' => env('IOS_ENVIRONMENT',''),
        'certificate' => env('IOS_CERTIFICATE',''),
        'passPhrase'  => env('IOS_PASSPHRASE',''),
        'service'     => 'apns',
    ],
    'android' => [
        'environment' => env('ANDROID_ENVIRONMENT',''),
        'apiKey'      => env('ANDROID_KEY',''),
        'service'     => 'gcm',
    ],

];
