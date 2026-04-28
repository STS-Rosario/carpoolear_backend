<?php

namespace Tests\Unit\Helpers;

use Carbon\Carbon;
use STS\Helpers\OldCordovaAppHelper;
use Tests\TestCase;

class OldCordovaAppHelperTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalServer = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_is_old_cordova_app_returns_false_without_required_headers(): void
    {
        $_SERVER = [];

        $this->assertFalse(OldCordovaAppHelper::isOldCordovaApp());
    }

    public function test_is_old_cordova_app_returns_false_for_capacitor_headers(): void
    {
        $_SERVER['HTTP_SEC_CH_UA'] = '"Android WebView";v="124"';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SERVER['HTTP_X_APP_PLATFORM'] = 'android';
        $_SERVER['HTTP_X_APP_VERSION'] = '3.2.1';

        $this->assertFalse(OldCordovaAppHelper::isOldCordovaApp());
    }

    public function test_is_old_cordova_app_returns_true_for_old_webview_non_instagram(): void
    {
        $_SERVER['HTTP_SEC_CH_UA'] = '"Android WebView";v="124"';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 14)';

        $this->assertTrue(OldCordovaAppHelper::isOldCordovaApp());
    }

    public function test_is_old_cordova_app_returns_false_for_instagram_webview(): void
    {
        $_SERVER['HTTP_SEC_CH_UA'] = '"Android WebView";v="124"';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Instagram 325.0.0.0';

        $this->assertFalse(OldCordovaAppHelper::isOldCordovaApp());
    }

    public function test_get_fake_trip_data_returns_expected_payload_shape(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 9, 15, 0));

        $data = OldCordovaAppHelper::getFakeTripData();

        $this->assertSame(0, $data['id']);
        $this->assertSame('ACTUALIZA TU APP', $data['from_town']);
        $this->assertSame('para usar Carpoolear', $data['to_town']);
        $this->assertSame('ready', $data['state']);
        $this->assertSame('2026-04-28 09:15:00', $data['updated_at']);
        $this->assertSame([], $data['points']);
        $this->assertSame([], $data['ratings']);
        $this->assertSame([], $data['passenger']);
        $this->assertArrayHasKey('user', $data);
        $this->assertSame(123, $data['user']['id']);
        $this->assertSame('Carpoolear', $data['user']['name']);
        $this->assertSame('carpoolear_logo.png', $data['user']['image']);
        $this->assertSame('2026-04-28 09:15:00', $data['user']['last_connection']);
        $this->assertFalse($data['user']['has_pin']);
        $this->assertFalse($data['user']['driver_is_verified']);
    }
}
