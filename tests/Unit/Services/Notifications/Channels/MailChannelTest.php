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

    public function test_send_logs_notification_info_when_mail_disabled(): void
    {
        config(['mail.enabled' => false]);

        Log::shouldReceive('info')
            ->once()
            ->with(Mockery::on(function ($line): bool {
                $this->assertSame('notification info:', $line);

                return true;
            }));

        $user = User::factory()->create(['email' => 'mail-channel-test@example.com']);
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $notification = new DummyNotification;
        $notification->setAttribute('trip', $trip);

        (new MailChannel)->send($notification, $user);
    }

    public function test_send_renders_email_view_and_logs_sending_mail_when_mail_enabled(): void
    {
        config(['mail.enabled' => true]);

        $lines = [];
        Log::shouldReceive('info')
            ->times(4)
            ->andReturnUsing(function ($message) use (&$lines) {
                $lines[] = $message;

                return null;
            });

        $user = User::factory()->create(['email' => 'mail-channel-test@example.com', 'name' => 'Pat']);
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'from_town' => 'Rosario',
        ]);
        $notification = new DummyNotification;
        $notification->setAttribute('trip', $trip);

        (new MailChannel)->send($notification, $user);

        $joined = implode("\n", $lines);
        $this->assertStringContainsString('sending_mail:', $joined);
        $this->assertStringContainsString('Dummy Title', $joined);
        $this->assertStringContainsString('mail-channel-test@example.com', $joined);
        $this->assertStringContainsString('estoy aca:', $joined);
        $this->assertStringContainsString('estoy alla:', $joined);
        $this->assertStringContainsString('ssmtp_send_mail: START', $joined);
    }

    public function test_get_data_throws_when_notification_has_no_to_email(): void
    {
        $notification = new class {};

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('toEmail');

        (new MailChannel)->getData($notification, User::factory()->make());
    }
}
