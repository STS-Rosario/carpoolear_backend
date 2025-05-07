<?php

return [
    'donation_month_days' => env('DONATION_MONTH_DAYS', '0'),
    'donation_trips_count' => env('DONATION_TRIPS_COUNT', '20'),
    'donation_trips_offset' => env('DONATION_TRIPS_OFFSET', '0'),
    'donation_trips_rated' => env('DONATION_TRIPS_RATED', '2'),
    'donation_ammount_needed' => env('DONATION_AMMOUNT_NEEDED', '1000'),
    'banner_url' => env('BANNER_URL', ''),
    'banner_image' => env('BANNER_IMAGE', ''),
    'banner_url_cordova' => env('BANNER_URL_CORDOVA', ''),
    'banner_image_cordova' => env('BANNER_IMAGE_CORDOVA', ''),
    'target_app' => env('TARGET_APP', 'carpoolear'),
    'module_coordinate_by_message' => env('MODULE_COORDINATE_BY_MESSAGE', false),
    'module_user_request_limited_enabled' => env('MODULE_USER_REQUEST_LIMITED_ENABLED', false),
    'module_user_request_limited_hours_range' => (int) env('MODULE_USER_REQUEST_LIMITED_HOURS_RANGE', 2),
        
    // List of banned words that will trigger user ban if found in their name or in trip descriptions
    'banned_words' => [
        'admin',
        'administrator',
        'moderator',
        'support',
        'helpdesk',
        'carpoolear',
        'staff',
        'system',
        'robot',
        'carpy'
    ],

    // List of banned phone numbers that will trigger user ban if found in their profile or in trip descriptions
    'banned_phones' => [
        '1151415054'
    ]
];
