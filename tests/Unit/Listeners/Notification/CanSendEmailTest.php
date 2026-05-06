<?php

namespace Tests\Unit\Listeners\Notification;

use STS\Events\Notification\NotificationSending;
use STS\Listeners\Notification\CanSendEmail;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use Tests\TestCase;

class CanSendEmailTest extends TestCase
{
    public function test_handle_returns_false_for_mail_channel_when_user_opted_out_and_not_forced(): void
    {
        $listener = new CanSendEmail;
        $user = (object) ['emails_notifications' => false];
        $notification = (object) ['force_email' => false];
        $event = new NotificationSending($notification, $user, new MailChannel);

        $this->assertFalse($listener->handle($event));
    }

    public function test_handle_allows_mail_when_forced_or_user_opted_in(): void
    {
        $listener = new CanSendEmail;

        $optedOutButForced = new NotificationSending(
            (object) ['force_email' => true],
            (object) ['emails_notifications' => false],
            new MailChannel
        );
        $this->assertNull($listener->handle($optedOutButForced));

        $optedIn = new NotificationSending(
            (object) ['force_email' => false],
            (object) ['emails_notifications' => true],
            new MailChannel
        );
        $this->assertNull($listener->handle($optedIn));
    }

    public function test_handle_returns_null_for_non_mail_channels_without_touching_user_preferences(): void
    {
        $listener = new CanSendEmail;
        $user = (object) []; // no emails_notifications property — must not be read for non-mail channels
        $notification = (object) ['force_email' => false];
        $event = new NotificationSending($notification, $user, new DatabaseChannel);

        $this->assertNull($listener->handle($event));
    }
}
