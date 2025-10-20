<?php

return [
    'donation_month_days' => env('DONATION_MONTH_DAYS', '0'),
    'donation_trips_count' => env('DONATION_TRIPS_COUNT', '20'),
    'donation_trips_offset' => env('DONATION_TRIPS_OFFSET', '0'),
    'donation_trips_rated' => env('DONATION_TRIPS_RATED', '2'),
    'donation_ammount_needed' => env('DONATION_AMMOUNT_NEEDED', '1000'),
    'banner_url' => env('BANNER_URL', ''),
    'banner_url_mobile' => env('BANNER_URL_MOBILE', ''),
    'banner_image' => env('BANNER_IMAGE', ''),
    'banner_image_mobile' => env('BANNER_IMAGE_MOBILE', ''),
    'banner_url_cordova' => env('BANNER_URL_CORDOVA', ''),
    'banner_url_cordova_mobile' => env('BANNER_URL_CORDOVA_MOBILE', ''),
    'banner_image_cordova' => env('BANNER_IMAGE_CORDOVA', ''),
    'banner_image_cordova_mobile' => env('BANNER_IMAGE_CORDOVA_MOBILE', ''),
    'target_app' => env('TARGET_APP', 'carpoolear'),
    'module_coordinate_by_message' => env('MODULE_COORDINATE_BY_MESSAGE', false),
    'module_user_request_limited_enabled' => env('MODULE_USER_REQUEST_LIMITED_ENABLED', false),
    'module_user_request_limited_hours_range' => (int) env('MODULE_USER_REQUEST_LIMITED_HOURS_RANGE', 2),
    'module_send_full_trip_message' => env('MODULE_SEND_FULL_TRIP_MESSAGE', false),
    'module_unaswered_message_limit' => env('MODULE_UNASWERED_MESSAGE_LIMIT', false),
    'module_trip_seats_payment' => env('MODULE_TRIP_SEATS_PAYMENT', false),
    'module_unique_doc_phone' => env('MODULE_UNIQUE_DOC_PHONE', false),
    'module_validated_drivers' => env('MODULE_VALIDATED_DRIVERS', false),
    'module_trip_creation_payment_enabled' => env('MODULE_TRIP_CREATION_PAYMENT_ENABLED', false),
    'module_trip_creation_payment_amount_cents' => (int) env('MODULE_TRIP_CREATION_PAYMENT_AMOUNT_CENTS', 1500),
    'module_trip_creation_payment_trips_threshold' => (int)env('MODULE_TRIP_CREATION_PAYMENT_TRIPS_THRESHOLD', 2),
    'module_seat_price_enabled' => env('MODULE_SEAT_PRICE_ENABLED', false),
    'module_max_price_enabled' => env('MODULE_MAX_PRICE_ENABLED', false),
    'module_max_price_fuel_price' => (int) env('MODULE_MAX_PRICE_FUEL_PRICE', 1500),
    'module_max_price_price_variance_tolls' => (int) env('MODULE_MAX_PRICE_PRICE_VARIANCE_TOLLS', 10),
    'module_max_price_price_variance_max_extra' => (int) env('MODULE_MAX_PRICE_PRICE_VARIANCE_MAX_EXTRA', 15),
    'module_max_price_kilometer_by_liter' => (int) env('MODULE_MAX_PRICE_KILOMETER_BY_LITER', 10),

    // List of banned words that will trigger user ban if found in their name or in trip descriptions
    'banned_words_names' => [
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

    'banned_words_trip_description' => [
        'carpy'
    ],

    // List of banned phone numbers that will trigger user ban if found in their profile or in trip descriptions
    'banned_phones' => [
        '1151415054'
    ],

    'trip_creation_limits' => [
        'max_trips' => 4,        // Maximum number of trips allowed
        'time_window_hours' => 24,     // Time window in hours
    ]
];
