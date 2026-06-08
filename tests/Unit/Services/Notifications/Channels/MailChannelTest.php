<?php

namespace Tests\Unit\Services\Notifications\Channels;

use Illuminate\Support\Facades\Log;
use Mockery;
use STS\Models\Trip;
use STS\Models\User;
use STS\Notifications\DummyNotification;
use STS\Services\Notifications\Channels\MailChannel;
use Tests\TestCase;

class MailChannelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_does_nothing_when_user_email_missing(): void
    {
        $infoCalls = 0;
        Log::shouldReceive('info')->andReturnUsing(function () use (&$infoCalls): void {
            $infoCalls++;
        });

        $user = User::factory()->make(['email' => '']);

        (new MailChannel)->send(new DummyNotification, $user);

        $this->assertSame(0, $infoCalls);
    }

    public function test_send_returns_early_when_mail_disabled_without_logging(): void
    {
        config(['mail.enabled' => false]);

        Log::shouldReceive('info')->never();

        $user = User::factory()->create(['email' => 'mail-channel-test@example.com']);
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $notification = new DummyNotification;
        $notification->setAttribute('trip', $trip);

        (new MailChannel)->send($notification, $user);

        $this->assertTrue(true);
    }

    public function test_send_renders_email_view_when_mail_enabled_without_logging(): void
    {
        config(['mail.enabled' => true]);

        Log::shouldReceive('info')->never();

        $user = User::factory()->create(['email' => 'mail-channel-test@example.com', 'name' => 'Pat']);
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'from_town' => 'Rosario',
        ]);
        $notification = new DummyNotification;
        $notification->setAttribute('trip', $trip);

        (new MailChannel)->send($notification, $user);

        $this->assertTrue(true);
    }

    public function test_get_data_throws_when_notification_has_no_to_email(): void
    {
        $notification = new class {};

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('toEmail');

        (new MailChannel)->getData($notification, User::factory()->make());
    }
}
