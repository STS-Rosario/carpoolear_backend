<?php

namespace Tests\Unit\Notifications;

use STS\Notifications\DummyNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use Tests\TestCase;

class DummyNotificationTest extends TestCase
{
    public function test_via_contains_database_and_mail_channels(): void
    {
        $notification = new DummyNotification;

        $this->assertSame([
            DatabaseChannel::class,
            MailChannel::class,
        ], $notification->getVia());
    }

    public function test_to_email_returns_expected_static_payload(): void
    {
        $notification = new DummyNotification;

        $email = $notification->toEmail(null);

        $this->assertSame('Dummy Title', $email['title']);
        $this->assertSame('dummy', $email['email_view']);
    }

    public function test_to_string_concatenates_dummy_attribute_value(): void
    {
        $notification = new DummyNotification;
        $notification->setAttribute('dummy', 'abc');

        $this->assertSame('Dummy Notification abc', $notification->toString());
    }

    public function test_get_extras_returns_empty_array(): void
    {
        $notification = new DummyNotification;

        $this->assertSame([], $notification->getExtras());
    }
}
