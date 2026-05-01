<?php

namespace Tests\Unit\Console\Commands;

use Carbon\Carbon;
use Mockery;
use STS\Models\Device;
use STS\Models\User;
use STS\Services\AnnouncementService;
use Tests\TestCase;

class TestAnnouncementTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        return [
            'total_users' => 10,
            'active_users' => 8,
            'users_with_devices' => 6,
            'total_devices' => 12,
            'active_devices' => 5,
        ];
    }

    public function test_handle_prints_error_when_specific_user_does_not_exist(): void
    {
        $service = Mockery::mock(AnnouncementService::class);
        $service->shouldReceive('getUserStats')->once()->andReturn($this->stats());
        $service->shouldNotReceive('sendToUser');
        $this->app->instance(AnnouncementService::class, $service);

        $this->artisan('announcement:test', ['--user-id' => 999999])
            ->expectsOutput('User with ID 999999 not found.')
            ->assertExitCode(0);
    }

    public function test_handle_warns_when_user_has_no_notifications_enabled_devices(): void
    {
        $service = Mockery::mock(AnnouncementService::class);
        $service->shouldReceive('getUserStats')->once()->andReturn($this->stats());
        $service->shouldNotReceive('sendToUser');
        $this->app->instance(AnnouncementService::class, $service);

        $user = User::factory()->create();
        Device::query()->create([
            'user_id' => $user->id,
            'session_id' => 'session-no-notif',
            'device_id' => 'device-no-notif',
            'device_type' => 'android',
            'app_version' => 1,
            'notifications' => false,
            'last_activity' => Carbon::now()->toDateTimeString(),
        ]);

        $this->artisan('announcement:test', ['--user-id' => $user->id])
            ->expectsOutput('User has no devices with notifications enabled.')
            ->assertExitCode(0);
    }

    public function test_handle_sends_notification_for_valid_user_and_enabled_device(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 9, 30, 0));

        $user = User::factory()->create(['name' => 'Tester']);
        Device::query()->create([
            'user_id' => $user->id,
            'session_id' => 'session-1',
            'device_id' => 'device-1',
            'device_type' => 'android',
            'app_version' => 1,
            'notifications' => true,
            'last_activity' => Carbon::now()->toDateTimeString(),
        ]);

        $service = Mockery::mock(AnnouncementService::class);
        $service->shouldReceive('getUserStats')->once()->andReturn($this->stats());
        $service->shouldReceive('sendToUser')
            ->once()
            ->with(
                Mockery::on(fn ($passedUser) => $passedUser instanceof User && $passedUser->id === $user->id),
                Mockery::on(fn (string $msg) => str_contains($msg, 'Test announcement - 2026-04-28 09:30:00')),
                Mockery::on(function (array $options): bool {
                    return $options['title'] === 'Test Announcement'
                        && $options['external_url'] === 'https://carpoolear.com.ar';
                })
            )
            ->andReturn([
                'success' => true,
                'devices_count' => 1,
            ]);
        $this->app->instance(AnnouncementService::class, $service);

        $this->artisan('announcement:test', ['--user-id' => $user->id])
            ->expectsOutputToContain('=== Testing Announcement System ===')
            ->expectsOutputToContain('System Statistics:')
            ->expectsOutputToContain('- Total users: 10')
            ->expectsOutputToContain('- Active users: 8')
            ->expectsOutputToContain('- Users with devices: 6')
            ->expectsOutputToContain('- Total devices: 12')
            ->expectsOutputToContain('- Active devices: 5')
            ->expectsOutputToContain('Testing with user: Tester')
            ->expectsOutputToContain('User has 1 devices with notifications enabled:')
            ->expectsOutputToContain('- Device ID: device-1')
            ->expectsOutputToContain('Type: android')
            ->expectsOutputToContain('Last Activity:')
            ->expectsOutputToContain('Notifications: Enabled')
            ->expectsOutputToContain('Testing notification sending...')
            ->expectsOutputToContain('Test notification sent successfully!')
            ->expectsOutputToContain('Message: Test announcement - 2026-04-28 09:30:00')
            ->expectsOutput('Devices: 1')
            ->expectsOutputToContain('Test completed!')
            ->assertExitCode(0);
    }

    public function test_handle_resolves_eligible_user_when_user_id_option_is_omitted(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 12, 0, 0));

        $inactive = User::factory()->create([
            'active' => false,
            'banned' => false,
            'name' => 'Inactive With Device',
        ]);
        Device::query()->create([
            'user_id' => $inactive->id,
            'session_id' => 'sess-inactive',
            'device_id' => 'dev-inactive',
            'device_type' => 'android',
            'app_version' => 1,
            'notifications' => true,
            'last_activity' => Carbon::now()->toDateTimeString(),
        ]);

        $eligible = User::factory()->create([
            'active' => true,
            'banned' => false,
            'name' => 'Eligible Driver',
        ]);
        Device::query()->create([
            'user_id' => $eligible->id,
            'session_id' => 'sess-eligible',
            'device_id' => 'dev-eligible',
            'device_type' => 'ios',
            'app_version' => 1,
            'notifications' => true,
            'last_activity' => Carbon::now()->toDateTimeString(),
        ]);

        $service = Mockery::mock(AnnouncementService::class);
        $service->shouldReceive('getUserStats')->once()->andReturn($this->stats());
        $service->shouldReceive('sendToUser')
            ->once()
            ->with(
                Mockery::on(fn ($u) => $u instanceof User && $u->id === $eligible->id),
                Mockery::type('string'),
                Mockery::type('array')
            )
            ->andReturn(['success' => true, 'devices_count' => 1]);
        $this->app->instance(AnnouncementService::class, $service);

        $this->artisan('announcement:test')
            ->expectsOutputToContain('Testing with user: Eligible Driver')
            ->assertExitCode(0);
    }

    public function test_handle_prints_service_error_when_send_to_user_fails(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 28, 8, 0, 0));

        $user = User::factory()->create(['name' => 'Failing User']);
        Device::query()->create([
            'user_id' => $user->id,
            'session_id' => 'sess-fail',
            'device_id' => 'dev-fail',
            'device_type' => 'android',
            'app_version' => 1,
            'notifications' => true,
            'last_activity' => Carbon::now()->toDateTimeString(),
        ]);

        $service = Mockery::mock(AnnouncementService::class);
        $service->shouldReceive('getUserStats')->once()->andReturn($this->stats());
        $service->shouldReceive('sendToUser')->once()->andReturn([
            'success' => false,
            'message' => 'push transport unavailable',
        ]);
        $this->app->instance(AnnouncementService::class, $service);

        $this->artisan('announcement:test', ['--user-id' => $user->id])
            ->expectsOutputToContain('✗ Test notification failed: push transport unavailable')
            ->assertExitCode(0);
    }
}
