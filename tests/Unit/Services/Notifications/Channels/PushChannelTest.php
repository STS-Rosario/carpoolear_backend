<?php

namespace Tests\Unit\Services\Notifications\Channels;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Models\Device;
use STS\Models\User;
use STS\Notifications\AnnouncementNotification;
use STS\Services\FirebaseService;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class PushChannelTest extends TestCase
{
    public function test_send_android_uses_firebase_service_with_expected_payload(): void
    {
        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->withArgs(function (string $token, array $message, array $data, string $platform): bool {
                return $token === 'android-direct-token'
                    && $platform === 'android'
                    && $message['title'] === 'Title A'
                    && $message['body'] === 'Body A'
                    && $message['icon'] === 'https://img.example/icon.png'
                    && $message['sound'] === 'default'
                    && $data['type'] === 'conversation'
                    && $data['conversation_id'] === '42'
                    && $data['url'] === 'https://example.com/app';
            })
            ->andReturn(['ok' => true]);

        $channel = new PushChannel(fn () => $firebase);
        $device = new Device([
            'id' => 5001,
            'device_id' => 'android-direct-token',
            'device_type' => 'android',
        ]);

        $result = $channel->sendAndroid($device, [
            'message' => 'Body A',
            'title' => 'Title A',
            'image' => 'https://img.example/icon.png',
            'type' => 'conversation',
            'extras' => ['conversation_id' => 42],
            'url' => 'https://example.com/app',
        ]);

        $this->assertSame(['ok' => true], $result);
    }

    public function test_send_browser_uses_firebase_service_with_click_action_when_url_present(): void
    {
        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->withArgs(function (string $token, array $message, array $extras, string $platform): bool {
                return $token === 'browser-direct-token'
                    && $platform === 'browser'
                    && $message['title'] === 'Carpoolear'
                    && $message['body'] === 'Browser body'
                    && $message['click_action'] === 'https://example.com/browser'
                    && $extras['k'] === 'v';
            })
            ->andReturn(['ok' => true]);

        $channel = new PushChannel(fn () => $firebase);
        $device = new Device([
            'device_id' => 'browser-direct-token',
            'device_type' => 'browser',
        ]);

        $channel->sendBrowser($device, [
            'message' => 'Browser body',
            'url' => 'https://example.com/browser',
            'extras' => ['k' => 'v'],
        ]);

        $this->assertTrue(true);
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
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow($this->fcmInternalErrorException());

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

        (new PushChannel(fn () => $firebase))->send($notification, $user);

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
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow($this->fcmInternalErrorException());

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

        (new PushChannel(fn () => $firebase))->send($notification, $user);

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
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow($this->fcmInternalErrorException());

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

        (new PushChannel(fn () => $firebase))->send($notification, $user);

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
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow($this->fcmInternalErrorException());

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

        (new PushChannel(fn () => $firebase))->send($notification, $user);

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

    public function test_send_deactivates_android_device_when_fcm_returns_not_registered(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $logged = [];
        Log::shouldReceive('error')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context, 'level' => 'error'];
            });
        Log::shouldReceive('warning')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context, 'level' => 'warning'];
            });

        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/carpoolear-production/messages:send');
        $payload = [
            'error' => [
                'code' => 404,
                'message' => 'NotRegistered',
                'status' => 'NOT_FOUND',
            ],
        ];
        $response = new Response(404, [], json_encode($payload));
        $fcmException = new ClientException('NotRegistered', $request, $response);

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow($fcmException);

        $user = User::factory()->create();
        $device = Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'stale-android-token',
            'device_type' => 'android',
            'session_id' => 's-stale-android',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello');
        $notification->setAttribute('title', 'T');

        (new PushChannel(fn () => $firebase))->send($notification, $user);

        $device->refresh();
        $this->assertFalse($device->notifications);
        $this->assertSame([], $logged);
    }

    public function test_send_deactivates_android_device_when_fcm_returns_not_found_with_unregistered_details(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $logged = [];
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });

        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/carpoolear-production/messages:send');
        $payload = [
            'error' => [
                'code' => 404,
                'message' => 'Requested entity was not found.',
                'status' => 'NOT_FOUND',
                'details' => [
                    [
                        '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                        'errorCode' => 'UNREGISTERED',
                    ],
                ],
            ],
        ];
        $response = new Response(404, [], json_encode($payload));
        $fcmException = new ClientException('Requested entity was not found.', $request, $response);

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow($fcmException);

        $user = User::factory()->create();
        $device = Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'stale-android-token-v2',
            'device_type' => 'android',
            'session_id' => 's-stale-android-v2',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello');
        $notification->setAttribute('title', 'T');

        (new PushChannel(fn () => $firebase))->send($notification, $user);

        $device->refresh();
        $this->assertFalse($device->notifications);
        $this->assertSame([], $logged);
    }

    public function test_send_deactivates_android_device_when_fcm_returns_sender_id_mismatch(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $logged = [];
        Log::shouldReceive('error')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context, 'level' => 'error'];
            });
        Log::shouldReceive('warning')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context, 'level' => 'warning'];
            });

        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/carpoolear-production/messages:send');
        $payload = [
            'error' => [
                'code' => 403,
                'message' => 'SenderId mismatch',
                'status' => 'PERMISSION_DENIED',
            ],
        ];
        $response = new Response(403, [], json_encode($payload));
        $fcmException = new ClientException('SenderId mismatch', $request, $response);

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow($fcmException);

        $user = User::factory()->create();
        $device = Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'wrong-sender-android-token',
            'device_type' => 'Android',
            'session_id' => 's-sender-mismatch',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello');
        $notification->setAttribute('title', 'T');

        (new PushChannel(fn () => $firebase))->send($notification, $user);

        $device->refresh();
        $this->assertFalse($device->notifications);
        $this->assertSame([], $logged);
    }

    public function test_send_deactivates_browser_device_when_fcm_returns_not_registered(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $logged = [];
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });

        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/carpoolear-production/messages:send');
        $payload = [
            'error' => [
                'code' => 404,
                'message' => 'NotRegistered',
                'status' => 'NOT_FOUND',
            ],
        ];
        $response = new Response(404, [], json_encode($payload));
        $fcmException = new ClientException('NotRegistered', $request, $response);

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow($fcmException);

        $user = User::factory()->create();
        $device = Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'stale-browser-token',
            'device_type' => 'browser',
            'session_id' => 's-stale-browser',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello browser');
        $notification->setAttribute('title', 'TB');

        (new PushChannel(fn () => $firebase))->send($notification, $user);

        $device->refresh();
        $this->assertFalse($device->notifications);
        $this->assertSame([], $logged);
    }

    public function test_is_apns_unregistered_error_detects_http_410_unregistered(): void
    {
        $exception = new \RuntimeException(
            'APNs returned HTTP 410: {"reason":"Unregistered","timestamp":1770046554255}'
        );

        $this->assertTrue(PushChannel::isApnsUnregisteredError($exception));
    }

    public function test_is_apns_unregistered_error_returns_false_for_other_apns_errors(): void
    {
        $exception = new \RuntimeException('APNs returned HTTP 403: {"reason":"BadCertificate"}');

        $this->assertFalse(PushChannel::isApnsUnregisteredError($exception));
        $this->assertFalse(PushChannel::isApnsUnregisteredError(new \RuntimeException('network down')));
    }

    public function test_is_apns_bad_device_token_error_detects_http_400_bad_device_token(): void
    {
        $exception = new \RuntimeException(
            'APNs returned HTTP 400: {"reason":"BadDeviceToken"}'
        );

        $this->assertTrue(PushChannel::isApnsBadDeviceTokenError($exception));
    }

    public function test_is_apns_bad_device_token_error_returns_false_for_other_apns_errors(): void
    {
        $exception = new \RuntimeException('APNs returned HTTP 410: {"reason":"Unregistered"}');

        $this->assertFalse(PushChannel::isApnsBadDeviceTokenError($exception));
        $this->assertFalse(PushChannel::isApnsBadDeviceTokenError(new \RuntimeException('network down')));
    }

    public function test_send_deactivates_ios_device_when_apns_returns_bad_device_token(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $logged = [];
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });

        $channel = new class extends PushChannel
        {
            public function sendIOS($device, $data)
            {
                throw new \Exception('APNs returned HTTP 400: {"reason":"BadDeviceToken"}');
            }
        };

        $user = User::factory()->create();
        $device = Device::query()->create([
            'user_id' => $user->id,
            'device_id' => str_repeat('c', 64),
            'device_type' => 'iOS',
            'session_id' => 's-bad-ios-token',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello ios');
        $notification->setAttribute('title', 'TI');

        $channel->send($notification, $user);

        $device->refresh();
        $this->assertFalse($device->notifications);
        $this->assertSame([], $logged);
    }

    public function test_send_deactivates_ios_device_when_apns_returns_unregistered(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $logged = [];
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('warning')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });

        $channel = new class extends PushChannel
        {
            public function sendIOS($device, $data)
            {
                throw new \Exception('APNs returned HTTP 410: {"reason":"Unregistered","timestamp":1770046554255}');
            }
        };

        $user = User::factory()->create();
        $device = Device::query()->create([
            'user_id' => $user->id,
            'device_id' => str_repeat('b', 64),
            'device_type' => 'iOS',
            'session_id' => 's-stale-ios',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello ios');
        $notification->setAttribute('title', 'TI');

        $channel->send($notification, $user);

        $device->refresh();
        $this->assertFalse($device->notifications);
        $this->assertSame([], $logged);
    }

    public function test_send_still_logs_error_when_android_send_fails_for_non_stale_token(): void
    {
        Config::set('carpoolear.send_push_notifications_to_device_activity_days', 0);

        $logged = [];
        Log::shouldReceive('error')
            ->zeroOrMoreTimes()
            ->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
                $logged[] = ['message' => $message, 'context' => $context];
            });
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/myproj/messages:send');
        $payload = [
            'error' => [
                'code' => 500,
                'message' => 'Internal error',
                'status' => 'INTERNAL',
            ],
        ];
        $response = new Response(500, [], json_encode($payload));
        $fcmException = new ClientException('Internal error', $request, $response);

        $firebase = Mockery::mock(FirebaseService::class);
        $firebase->shouldReceive('sendNotification')
            ->once()
            ->andThrow($fcmException);

        $user = User::factory()->create();
        $device = Device::query()->create([
            'user_id' => $user->id,
            'device_id' => 'valid-android-token',
            'device_type' => 'android',
            'session_id' => 's-valid-android',
            'notifications' => true,
            'last_activity' => now()->toDateTimeString(),
        ]);

        $user->load('devices');

        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello');
        $notification->setAttribute('title', 'T');

        (new PushChannel(fn () => $firebase))->send($notification, $user);

        $device->refresh();
        $this->assertTrue($device->notifications);
        $this->assertNotNull($this->firstLogMatching($logged, 'PushChannel: Error sending push notification'));
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

    private function fcmInternalErrorException(): ClientException
    {
        $request = new Request('POST', 'https://fcm.googleapis.com/v1/projects/myproj/messages:send');
        $payload = [
            'error' => [
                'code' => 500,
                'message' => 'Internal error',
                'status' => 'INTERNAL',
            ],
        ];
        $response = new Response(500, [], json_encode($payload));

        return new ClientException('Internal error', $request, $response);
    }
}
