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
use STS\Support\UserLocale;
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

final class LocaleRecordingChannel
{
    public static string $locale = '';

    public function send($notification, $user): void
    {
        self::$locale = app()->getLocale();
    }

    public static function reset(): void
    {
        self::$locale = '';
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
        LocaleRecordingChannel::reset();
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
        Log::shouldReceive('warning')
            ->once()
            ->with('Notification send failed', Mockery::on(function (array $context): bool {
                return ($context['message'] ?? null) === 'channel boom';
            }));

        $svc = new NotificationServices;
        $user = User::factory()->create();

        $svc->send(new DummyNotification, $user, ThrowingNotificationChannel::class);
    }

    public function test_send_uses_recipient_locale_when_delivering_notification(): void
    {
        config(['app.locale' => 'arg']);
        app()->setLocale('arg');

        $user = User::factory()->create(['locale' => 'en']);
        $svc = new NotificationServices;

        $svc->send(new DummyNotification, $user, LocaleRecordingChannel::class);

        $this->assertSame('en', LocaleRecordingChannel::$locale);
        $this->assertSame('arg', app()->getLocale());
    }

    public function test_send_falls_back_to_app_locale_when_recipient_locale_is_missing(): void
    {
        config(['app.locale' => 'arg']);
        app()->setLocale('en');
        config(['app.locale' => 'arg']);

        $user = User::factory()->create();
        $user->forceFill(['locale' => null])->saveQuietly();
        $user->refresh();

        $this->assertNull($user->locale);
        $this->assertSame('arg', UserLocale::resolve($user, 'arg'));

        $svc = new NotificationServices;

        $svc->send(new DummyNotification, $user, LocaleRecordingChannel::class);

        $this->assertSame('arg', LocaleRecordingChannel::$locale);
        $this->assertSame('arg', app()->getLocale());
    }
}
