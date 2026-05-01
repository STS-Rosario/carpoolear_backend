<?php

namespace Tests\Unit\Notifications;

use Illuminate\Support\Facades\Config;
use STS\Notifications\ResetPasswordNotification;
use STS\Services\Notifications\Channels\MailChannel;
use Tests\TestCase;

class ResetPasswordNotificationTest extends TestCase
{
    public function test_via_contains_mail_channel_only(): void
    {
        $notification = new ResetPasswordNotification;

        $this->assertSame([MailChannel::class], $notification->getVia());
    }

    public function test_to_email_includes_token_and_reset_url_when_present(): void
    {
        config([
            'carpoolear.name_app' => 'Carpoolear Test',
            'app.url' => 'https://app.test',
        ]);

        $notification = new ResetPasswordNotification;
        $notification->setAttribute('token', 'reset-token-123');

        $email = $notification->toEmail(null);

        $this->assertSame('reset_password', $email['email_view']);
        $this->assertSame('reset-token-123', $email['token']);
        $this->assertSame('https://app.test/app/password/reset/reset-token-123', $email['url']);
        $this->assertSame('Carpoolear Test', $email['name_app']);
        $this->assertSame('https://app.test', $email['domain']);
    }

    public function test_to_email_uses_fallback_app_name_and_empty_token_when_missing(): void
    {
        $original = config('carpoolear.name_app');
        Config::set('carpoolear.name_app', null);

        $notification = new ResetPasswordNotification;
        $email = $notification->toEmail(null);

        $this->assertSame(
            __('notifications.reset_password.title', ['app_name' => 'Carpoolear']),
            $email['title']
        );
        $this->assertSame('', $email['token']);
        $this->assertSame(config('app.url').'/app/password/reset/', $email['url']);

        Config::set('carpoolear.name_app', $original);
    }

    public function test_to_string_and_force_email_flag_match_expected_behavior(): void
    {
        $notification = new ResetPasswordNotification;

        $this->assertTrue($notification->force_email);
        $this->assertSame(__('notifications.reset_password.message'), $notification->toString());
    }
}
