<?php

namespace Tests\Unit\Notifications;

use STS\Notifications\NewUserNotification;
use Tests\TestCase;

class NewUserNotificationTest extends TestCase
{
    public function test_to_email_uses_activation_token_when_present(): void
    {
        $notification = new NewUserNotification;
        $notification->setAttribute('token', 'abc123token');

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.new_user.title'), $email['title']);
        $this->assertSame('create_account', $email['email_view']);
        $this->assertSame(config('app.url').'/app/activate/abc123token', $email['url']);
    }

    public function test_to_email_uses_empty_token_suffix_when_missing(): void
    {
        $notification = new NewUserNotification;

        $email = $notification->toEmail(null);

        $this->assertSame(config('app.url').'/app/activate/', $email['url']);
    }

    public function test_to_string_and_force_email_flag_match_expected_behavior(): void
    {
        $notification = new NewUserNotification;

        $this->assertTrue($notification->force_email);
        $this->assertSame(__('notifications.new_user.message'), $notification->toString());
    }
}
