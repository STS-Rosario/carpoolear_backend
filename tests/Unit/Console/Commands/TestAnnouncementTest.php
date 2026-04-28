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
            ->expectsOutputToContain('Test notification sent successfully!')
            ->expectsOutput('Devices: 1')
            ->assertExitCode(0);
    }
}
