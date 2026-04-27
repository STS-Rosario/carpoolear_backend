<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\Device;
use STS\Models\User;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDevice(User $user, array $overrides = []): Device
    {
        $device = new Device;
        $device->forceFill(array_merge([
            'user_id' => $user->id,
            'device_id' => 'device-'.uniqid('', true),
            'device_type' => 'android',
            'session_id' => 'session-'.uniqid('', true),
            'app_version' => 100,
            'notifications' => true,
            'language' => 'es',
            'last_activity' => '2026-06-10',
        ], $overrides))->save();

        return $device->fresh();
    }

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $device = $this->makeDevice($user);

        $this->assertTrue($device->user->is($user));
    }

    public function test_notifications_casts_to_boolean(): void
    {
        $user = User::factory()->create();
        $on = $this->makeDevice($user, ['notifications' => 1]);
        $off = $this->makeDevice($user, ['notifications' => 0, 'device_id' => 'other-'.uniqid('', true)]);

        $this->assertTrue($on->fresh()->notifications);
        $this->assertFalse($off->fresh()->notifications);
    }

    public function test_last_activity_accessor_returns_carbon(): void
    {
        $user = User::factory()->create();
        $device = $this->makeDevice($user, ['last_activity' => '2026-03-20']);

        $this->assertInstanceOf(Carbon::class, $device->last_activity);
        $this->assertSame('2026-03-20', $device->last_activity->toDateString());
    }

    public function test_is_android_detects_substring_case_insensitively(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($this->makeDevice($user, ['device_type' => 'ANDROID'])->isAndroid());
        $this->assertTrue($this->makeDevice($user, ['device_type' => 'android', 'device_id' => 'a1'])->isAndroid());
        $this->assertFalse($this->makeDevice($user, ['device_type' => 'ios', 'device_id' => 'a2'])->isAndroid());
    }

    public function test_is_ios_detects_substring_case_insensitively(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($this->makeDevice($user, ['device_type' => 'IOS'])->isIOS());
        $this->assertTrue($this->makeDevice($user, ['device_type' => 'ios', 'device_id' => 'i1'])->isIOS());
        $this->assertFalse($this->makeDevice($user, ['device_type' => 'web', 'device_id' => 'i2'])->isIOS());
    }

    public function test_is_browser_detects_substring_case_insensitively(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($this->makeDevice($user, ['device_type' => 'Browser'])->isBrowser());
        $this->assertTrue($this->makeDevice($user, ['device_type' => 'browser', 'device_id' => 'b1'])->isBrowser());
        $this->assertFalse($this->makeDevice($user, ['device_type' => 'android', 'device_id' => 'b2'])->isBrowser());
    }

    public function test_table_name_is_users_devices(): void
    {
        $this->assertSame('users_devices', (new Device)->getTable());
    }
}
