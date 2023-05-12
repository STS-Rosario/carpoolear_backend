<?php

return [
    'donation_month_days' => env('DONATION_MONTH_DAYS', '0'),
    'donation_trips_count' => env('DONATION_TRIPS_COUNT', '20'),
    'donation_trips_offset' => env('DONATION_TRIPS_OFFSET', '0'),
    'donation_trips_rated' => env('DONATION_TRIPS_RATED', '2'),
    'donation_ammount_needed' => env('DONATION_AMMOUNT_NEEDED', '1000'),
    'banner_url' => env('BANNER_URL', ''),
    'banner_image' => env('BANNER_IMAGE', ''),
    'send_push_notifications_to_device_activity_days' => env('SEND_PUSH_NOTIFICATIONS_TO_DEVICE_ACTIVITY_DAYS', 90),
];
