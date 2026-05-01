<?php

namespace Tests\Unit\Services\Notifications\Channels;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use STS\Models\Device;
use STS\Models\User;
use STS\Notifications\AnnouncementNotification;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class PushChannelTest extends TestCase
{
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

        $logged = [];
        Log::shouldReceive('error')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });

        $user = User::factory()->create();
        $device = Device::query()->create([
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

        $outer = $this->firstLogMatching($logged, 'PushChannel: Error sending push notification');
        $this->assertNotNull($outer, 'Expected outer PushChannel catch to log once.');
        $ctx = $outer['context'];
        $this->assertSame($user->id, $ctx['user_id']);
        $this->assertSame($device->id, $ctx['device_id']);
        $this->assertSame(substr((string) $device->device_id, 0, 20).'...', $ctx['device_token']);
        $this->assertSame('android', $ctx['device_type']);
        $this->assertIsString($ctx['error'] ?? null);
        $this->assertNotSame('', $ctx['error']);
        $this->assertIsString($ctx['error_trace'] ?? null);
        $this->assertGreaterThan(40, strlen($ctx['error_trace']));

        $inner = $this->firstLogMatching($logged, 'PushChannel: sendAndroid error');
        $this->assertNotNull($inner, 'Expected sendAndroid catch to log before rethrowing.');
        $ictx = $inner['context'];
        $this->assertSame($device->id, $ictx['device_id']);
        $this->assertSame(substr((string) $device->device_id, 0, 20).'...', $ictx['device_token']);
        $this->assertIsString($ictx['error'] ?? null);
        $this->assertNotSame('', $ictx['error']);
        $this->assertIsString($ictx['error_trace'] ?? null);
        $this->assertNotSame('', $ictx['error_trace']);
        $this->assertIsArray($ictx['input_data'] ?? null);
        $this->assertSame('Hello', $ictx['input_data']['message']);
        $this->assertSame('T', $ictx['input_data']['title']);
    }

    public function test_get_data_throws_when_notification_has_no_to_push(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Method toPush does't exists");

        $user = User::factory()->create();
        $device = new Device([
            'device_id' => 'x',
            'device_type' => 'android',
        ]);

        (new PushChannel)->getData(new \stdClass, $user, $device);
    }

    public function test_get_extra_data_returns_null_when_notification_has_no_get_extras(): void
    {
        $this->assertNull((new PushChannel)->getExtraData(new \stdClass));
    }

    public function test_send_logs_push_channel_error_when_browser_send_throws(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $logged = [];
        Log::shouldReceive('error')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });

        $user = User::factory()->create();
        $device = Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'browser-token-for-push-test',
            'device_type' => 'browser',
            'session_id' => 's-browser',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello browser');
        $notification->setAttribute('title', 'TB');

        (new PushChannel)->send($notification, $user);

        $outer = $this->firstLogMatching($logged, 'PushChannel: Error sending push notification');
        $this->assertNotNull($outer);
        $ctx = $outer['context'];
        $this->assertSame($user->id, $ctx['user_id']);
        $this->assertSame($device->id, $ctx['device_id']);
        $this->assertSame(substr((string) $device->device_id, 0, 20).'...', $ctx['device_token']);
        $this->assertSame('browser', $ctx['device_type']);
        $this->assertNotSame('', (string) ($ctx['error'] ?? ''));
    }

    public function test_send_logs_ios_inner_and_outer_errors_when_apns_certificate_missing(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);
        Config::set('push-notification.ios.certificate', base_path('nonexistent-apns-cert-'.uniqid('', true).'.pem'));

        $logged = [];
        Log::shouldReceive('error')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });

        $user = User::factory()->create();
        $device = Device::query()->create([
            'user_id' => $user->id,
            'device_id' => str_repeat('a', 64),
            'device_type' => 'ios',
            'session_id' => 's-ios',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello ios');
        $notification->setAttribute('title', 'TI');

        (new PushChannel)->send($notification, $user);

        $inner = $this->firstLogMatching($logged, 'PushChannel: sendIOS error');
        $this->assertNotNull($inner, 'Expected sendIOS catch to log before rethrowing.');
        $ictx = $inner['context'];
        $this->assertSame($device->id, $ictx['device_id']);
        $this->assertSame($device->device_id, $ictx['device_token']);
        $this->assertStringContainsString('APNs certificate not found', (string) ($ictx['error'] ?? ''));

        $outer = $this->firstLogMatching($logged, 'PushChannel: Error sending push notification');
        $this->assertNotNull($outer);
        $this->assertSame('ios', $outer['context']['device_type']);
        $this->assertSame($user->id, $outer['context']['user_id']);
    }

    public function test_send_android_error_input_data_includes_type_url_and_image_from_to_push(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $logged = [];
        Log::shouldReceive('error')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });

        $user = User::factory()->create();
        Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'android-rich-payload-token',
            'device_type' => 'android',
            'session_id' => 's-rich',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new class
        {
            public function toPush($user, $device): array
            {
                return [
                    'message' => 'Rich body',
                    'title' => 'Rich title',
                    'url' => 'https://example.com/deeplink',
                    'type' => 'custom_type',
                    'image' => 'https://cdn.example/push.png',
                ];
            }

            public function getExtras(): array
            {
                return ['from_get_extras' => '1'];
            }
        };

        (new PushChannel)->send($notification, $user);

        $inner = $this->firstLogMatching($logged, 'PushChannel: sendAndroid error');
        $this->assertNotNull($inner);
        $data = $inner['context']['input_data'];
        $this->assertSame('Rich body', $data['message']);
        $this->assertSame('Rich title', $data['title']);
        $this->assertSame('https://example.com/deeplink', $data['url']);
        $this->assertSame('custom_type', $data['type']);
        $this->assertSame('https://cdn.example/push.png', $data['image']);
        $this->assertSame(['from_get_extras' => '1'], $data['extras']);
    }

    /**
     * @param  list<array{message: string, context: array}>  $logged
     * @return array{message: string, context: array}|null
     */
    private function firstLogMatching(array $logged, string $message): ?array
    {
        foreach ($logged as $entry) {
            if (($entry['message'] ?? '') === $message) {
                return $entry;
            }
        }

        return null;
    }
}
