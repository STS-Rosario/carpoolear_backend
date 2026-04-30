<?php

namespace Tests\Unit\Helpers;

use STS\Helpers\OldCordovaAppHelper;
use Tests\TestCase;

class OldCordovaAppHelperTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        parent::tearDown();
    }

    public function test_is_old_cordova_app_returns_false_without_sec_ch_ua_or_user_agent(): void
    {
        $_SERVER = $this->originalServer;
        unset($_SERVER['HTTP_SEC_CH_UA'], $_SERVER['HTTP_USER_AGENT']);

        $this->assertFalse(OldCordovaAppHelper::isOldCordovaApp());
    }

    public function test_is_old_cordova_app_returns_false_when_capacitor_headers_present(): void
    {
        $_SERVER = array_merge($this->originalServer, [
            'HTTP_SEC_CH_UA' => '"Chromium";v="119", "Android WebView";v="119"',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 12; wv) AppleWebKit Chrome/119',
            'HTTP_X_APP_PLATFORM' => 'android',
            'HTTP_X_APP_VERSION' => '2.4.1',
        ]);

        $this->assertFalse(OldCordovaAppHelper::isOldCordovaApp());
    }

    public function test_is_old_cordova_app_requires_both_capacitor_headers_to_short_circuit(): void
    {
        $_SERVER = array_merge($this->originalServer, [
            'HTTP_SEC_CH_UA' => '"Chromium";v="119", "Android WebView";v="119"',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 12; wv) AppleWebKit Chrome/119',
            'HTTP_X_APP_VERSION' => '1.0.0',
        ]);

        $this->assertTrue(OldCordovaAppHelper::isOldCordovaApp());
    }

    public function test_is_old_cordova_app_returns_false_for_instagram_in_app_browser(): void
    {
        $_SERVER = array_merge($this->originalServer, [
            'HTTP_SEC_CH_UA' => '"Chromium";v="119", "Android WebView";v="119"',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Instagram/275.0.0.27.98 Android WebView',
        ]);

        $this->assertFalse(OldCordovaAppHelper::isOldCordovaApp());
    }

    public function test_is_old_cordova_app_returns_true_for_webview_without_instagram(): void
    {
        $_SERVER = array_merge($this->originalServer, [
            'HTTP_SEC_CH_UA' => '"Chromium";v="119", "Android WebView";v="119"',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 12; wv) AppleWebKit Chrome/119 Safari',
        ]);

        $this->assertTrue(OldCordovaAppHelper::isOldCordovaApp());
    }

    public function test_get_fake_trip_data_exposes_stable_update_banner_contract(): void
    {
        $data = OldCordovaAppHelper::getFakeTripData();

        $this->assertSame(0, $data['id']);
        $this->assertSame('ACTUALIZA TU APP', $data['from_town']);
        $this->assertSame('para usar Carpoolear', $data['to_town']);
        $this->assertSame('2026-12-31 12:00:00', $data['trip_date']);
        $this->assertSame('ready', $data['state']);
        $this->assertSame('', $data['request']);
        $this->assertSame(1, $data['total_seats']);
        $this->assertSame(1, $data['seats_available']);
        $this->assertSame(0, $data['passengerPending_count']);
        $this->assertIsArray($data['points']);
        $this->assertIsArray($data['ratings']);
        $this->assertIsArray($data['passenger']);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['updated_at']);

        $this->assertFalse($data['allow_kids']);
        $this->assertFalse($data['allow_animals']);
        $this->assertFalse($data['allow_smoking']);
        $this->assertFalse($data['needs_sellado']);

        $user = $data['user'];
        $this->assertSame(123, $user['id']);
        $this->assertSame('Carpoolear', $user['name']);
        $this->assertSame('carpoolear_logo.png', $user['image']);
        $this->assertSame(0, $user['positive_ratings']);
        $this->assertSame(0, $user['negative_ratings']);
        $this->assertFalse($user['has_pin']);
        $this->assertFalse($user['is_member']);
        $this->assertFalse($user['monthly_donate']);
        $this->assertFalse($user['do_not_alert_request_seat']);
        $this->assertFalse($user['do_not_alert_accept_passenger']);
        $this->assertFalse($user['do_not_alert_pending_rates']);
        $this->assertFalse($user['do_not_alert_pricing']);
        $this->assertFalse($user['autoaccept_requests']);
        $this->assertFalse($user['driver_is_verified']);
        $this->assertSame(0, $user['conversation_opened_count']);
        $this->assertSame(0, $user['conversation_answered_count']);
        $this->assertSame(0, $user['answer_delay_sum']);
        $this->assertArrayHasKey('last_connection', $user);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $user['last_connection']);

        $expectedTopLevel = [
            'id', 'from_town', 'to_town', 'trip_date', 'weekly_schedule', 'weekly_schedule_time',
            'description', 'total_seats', 'friendship_type_id', 'distance', 'estimated_time',
            'seat_price_cents', 'recommended_trip_price_cents', 'total_price', 'state', 'is_passenger',
            'passenger_count', 'seats_available', 'points', 'ratings', 'updated_at', 'allow_kids',
            'allow_animals', 'allow_smoking', 'payment_id', 'needs_sellado', 'request', 'passenger',
            'user', 'passengerPending_count',
        ];
        foreach ($expectedTopLevel as $key) {
            $this->assertArrayHasKey($key, $data, "Missing top-level key: {$key}");
        }

        $expectedUserKeys = [
            'id', 'name', 'descripcion', 'private_note', 'image', 'positive_ratings', 'negative_ratings',
            'last_connection', 'accounts', 'has_pin', 'is_member', 'monthly_donate', 'do_not_alert_request_seat',
            'do_not_alert_accept_passenger', 'do_not_alert_pending_rates', 'do_not_alert_pricing',
            'autoaccept_requests', 'driver_is_verified', 'driver_data_docs', 'conversation_opened_count',
            'conversation_answered_count', 'answer_delay_sum', 'identity_validated_at',
        ];
        foreach ($expectedUserKeys as $key) {
            $this->assertArrayHasKey($key, $user, "Missing user key: {$key}");
        }
    }
}
