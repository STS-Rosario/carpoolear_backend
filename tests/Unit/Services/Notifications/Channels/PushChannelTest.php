<?php

namespace Tests\Unit\Services\Notifications\Channels;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Models\Device;
use STS\Models\User;
use STS\Notifications\AnnouncementNotification;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class PushChannelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_skips_push_pipeline_when_device_notifications_disabled(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $errors = 0;
        Log::shouldReceive('error')->andReturnUsing(function () use (&$errors): void {
            $errors++;
        });

        $user = User::factory()->create();
        Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'test-token-notifications-off',
            'device_type' => 'android',
            'session_id' => 's1',
            'notifications' => false,
            'last_activity' => now()->subDays(400)->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello');
        $notification->setAttribute('title', 'T');

        (new PushChannel)->send($notification, $user);

        $this->assertSame(0, $errors);
    }

    public function test_send_excludes_device_outside_activity_window_when_days_positive(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 7);

        $errors = 0;
        Log::shouldReceive('error')->andReturnUsing(function () use (&$errors): void {
            $errors++;
        });

        $user = User::factory()->create();
        Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'test-token-stale-activity',
            'device_type' => 'android',
            'session_id' => 's2',
            'notifications' => true,
            'last_activity' => now()->subDays(30)->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello');
        $notification->setAttribute('title', 'T');

        (new PushChannel)->send($notification, $user);

        $this->assertSame(0, $errors);
    }

    public function test_send_keeps_device_when_activity_days_zero_even_if_last_activity_stale(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $errors = 0;
        Log::shouldReceive('error')
            ->andReturnUsing(function () use (&$errors): void {
                $errors++;
            });

        $user = User::factory()->create();
        Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'test-token-activity-zero',
            'device_type' => 'android',
            'session_id' => 's3',
            'notifications' => true,
            'last_activity' => now()->subYears(5)->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello');
        $notification->setAttribute('title', 'T');

        (new PushChannel)->send($notification, $user);

        $this->assertGreaterThan(0, $errors);
    }

    public function test_send_logs_push_channel_error_when_android_send_throws(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'PushChannel: Error sending push notification',
                Mockery::on(function (array $context): bool {
                    $this->assertArrayHasKey('error', $context);
                    $this->assertNotSame('', $context['error']);

                    return true;
                })
            );

        $user = User::factory()->create();
        Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'test-token-android-throw',
            'device_type' => 'android',
            'session_id' => 's4',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello');
        $notification->setAttribute('title', 'T');

        (new PushChannel)->send($notification, $user);
    }
}
