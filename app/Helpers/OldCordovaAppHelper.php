<?php

namespace STS\Helpers;

class OldCordovaAppHelper
{
    /**
     * Detects if the request comes from an old Cordova/WebView app (excluding Instagram in-app browser).
     * Same logic as AuthController::getConfig for the "update app" banner.
     */
    public static function isOldCordovaApp(): bool
    {
        if (! isset($_SERVER['HTTP_SEC_CH_UA'], $_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        $secChUa = $_SERVER['HTTP_SEC_CH_UA'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        return strpos($secChUa, 'WebView') !== false && strpos($userAgent, 'Instagram') === false;
    }

    /**
     * Returns the fake trip data structure for old app responses (list item or single item).
     * Used by /trips (search) and /trips/{id} (show) to prompt users to update the app.
     */
    public static function getFakeTripData(): array
    {
        $avatarUrl = 'carpoolear_logo.png';
        $now = now()->toDateTimeString();

        $userData = [
            'id' => 123,
            'name' => 'Carpoolear',
            'descripcion' => null,
            'private_note' => null,
            'image' => $avatarUrl,
            'positive_ratings' => 0,
            'negative_ratings' => 0,
            'last_connection' => $now,
            'accounts' => null,
            'has_pin' => false,
            'is_member' => false,
            'monthly_donate' => false,
            'do_not_alert_request_seat' => false,
            'do_not_alert_accept_passenger' => false,
            'do_not_alert_pending_rates' => false,
            'do_not_alert_pricing' => false,
            'autoaccept_requests' => false,
            'driver_is_verified' => false,
            'driver_data_docs' => null,
            'conversation_opened_count' => 0,
            'conversation_answered_count' => 0,
            'answer_delay_sum' => 0,
            'identity_validated_at' => null,
        ];

        return [
            'id' => 0,
            'from_town' => 'ACTUALIZA TU APP',
            'to_town' => 'para usar Carpoolear',
            'trip_date' => '2026-12-31 12:00:00',
            'weekly_schedule' => 0,
            'weekly_schedule_time' => null,
            'description' => null,
            'total_seats' => 1,
            'friendship_type_id' => 0,
            'distance' => 0,
            'estimated_time' => null,
            'seat_price_cents' => 0,
            'recommended_trip_price_cents' => 0,
            'total_price' => null,
            'state' => 'ready',
            'is_passenger' => false,
            'passenger_count' => 0,
            'seats_available' => 1,
            'points' => [],
            'ratings' => [],
            'updated_at' => $now,
            'allow_kids' => false,
            'allow_animals' => false,
            'allow_smoking' => false,
            'payment_id' => null,
            'needs_sellado' => false,
            'request' => '',
            'passenger' => [],
            'user' => $userData,
            'passengerPending_count' => 0,
        ];
    }
}
