<?php

namespace Tests\Unit\Services\Notifications;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Events\Notification\NotificationSending;
use STS\Models\AppConfig;
use STS\Models\User;
use STS\Notifications\DummyNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

final class RecordingNotificationChannel
{
    /** @var list<array{0: mixed, 1: mixed}> */
    public static array $sent = [];

    public function send($notification, $user): void
    {
        self::$sent[] = [$notification, $user];
    }

    public static function reset(): void
    {
        self::$sent = [];
    }
}

final class ThrowingNotificationChannel
{
    public function send($notification, $user): void
    {
        throw new \RuntimeException('channel boom');
    }
}

class NotificationServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        AppConfig::query()->whereIn('key', [
            '_mutation_test_laravel_cfg',
            '_mutation_test_carpoolear_cfg',
        ])->delete();
        RecordingNotificationChannel::reset();
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_applies_app_config_rows_to_config_facade(): void
    {
        AppConfig::query()->create([
            'key' => '_mutation_test_laravel_cfg',
            'value' => json_encode('from_laravel_bucket'),
            'is_laravel' => true,
        ]);
        AppConfig::query()->create([
            'key' => '_mutation_test_carpoolear_cfg',
            'value' => json_encode('from_carpoolear_bucket'),
            'is_laravel' => false,
        ]);

        $svc = new NotificationServices;
        $user = User::factory()->create();

        $svc->send(new DummyNotification, $user, RecordingNotificationChannel::class);

        $this->assertSame('from_laravel_bucket', config('_mutation_test_laravel_cfg'));
        $this->assertSame('from_carpoolear_bucket', config('carpoolear._mutation_test_carpoolear_cfg'));
        $this->assertCount(1, RecordingNotificationChannel::$sent);
    }

    public function test_send_normalizes_single_user_to_iterable(): void
    {
        $svc = new NotificationServices;
        $user = User::factory()->create();

        $svc->send(new DummyNotification, $user, RecordingNotificationChannel::class);

        $this->assertCount(1, RecordingNotificationChannel::$sent);
        $this->assertSame($user, RecordingNotificationChannel::$sent[0][1]);
    }

    public function test_send_iterates_collection_without_wrapping(): void
    {
        $users = User::factory()->count(2)->create();
        $svc = new NotificationServices;

        $svc->send(new DummyNotification, $users, RecordingNotificationChannel::class);

        $this->assertCount(2, RecordingNotificationChannel::$sent);
        $ids = array_map(fn (array $row): int => $row[1]->id, RecordingNotificationChannel::$sent);
        sort($ids);
        $expected = $users->pluck('id')->sort()->values()->all();
        $this->assertSame($expected, $ids);
    }

    public function test_send_accepts_plain_array_of_users(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $svc = new NotificationServices;

        $svc->send(new DummyNotification, [$u1, $u2], RecordingNotificationChannel::class);

        $this->assertCount(2, RecordingNotificationChannel::$sent);
    }

    public function test_send_accepts_support_collection_of_users(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $svc = new NotificationServices;

        $svc->send(new DummyNotification, collect([$u1, $u2]), RecordingNotificationChannel::class);

        $this->assertCount(2, RecordingNotificationChannel::$sent);
    }

    public function test_send_skips_driver_when_notification_sending_returns_false(): void
    {
        Event::listen(NotificationSending::class, static fn () => false);

        $svc = new NotificationServices;
        $user = User::factory()->create();

        $svc->send(new DummyNotification, $user, RecordingNotificationChannel::class);

        $this->assertSame([], RecordingNotificationChannel::$sent);
    }

    public function test_send_logs_label_and_exception_when_driver_throws(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('error sending:');
        Log::shouldReceive('info')
            ->once()
            ->with(Mockery::on(function ($arg): bool {
                $this->assertInstanceOf(\RuntimeException::class, $arg);
                $this->assertSame('channel boom', $arg->getMessage());

                return true;
            }));

        $svc = new NotificationServices;
        $user = User::factory()->create();

        $svc->send(new DummyNotification, $user, ThrowingNotificationChannel::class);
    }
}
