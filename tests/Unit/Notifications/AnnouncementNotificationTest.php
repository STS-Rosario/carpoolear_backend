<?php

namespace Tests\Unit\Notifications;

use STS\Notifications\AnnouncementNotification;
use Tests\TestCase;

class AnnouncementNotificationTest extends TestCase
{
    public function test_to_email_uses_defaults_when_optional_fields_are_missing(): void
    {
        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'System maintenance tonight');

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.announcement.default_title'), $email['title']);
        $this->assertSame('announcement', $email['email_view']);
        $this->assertSame(config('app.url'), $email['url']);
        $this->assertSame('System maintenance tonight', $email['message']);
    }

    public function test_to_email_uses_external_url_when_provided(): void
    {
        $notification = new AnnouncementNotification;
        $notification->setAttribute('title', 'Important update');
        $notification->setAttribute('message', 'Read full details');
        $notification->setAttribute('external_url', 'https://example.org/announcement');

        $email = $notification->toEmail(null);

        $this->assertSame('Important update', $email['title']);
        $this->assertSame('https://example.org/announcement', $email['url']);
    }

    public function test_to_string_and_push_fallback_to_default_message(): void
    {
        $notification = new AnnouncementNotification;

        $this->assertSame(
            __('notifications.announcement.default_message'),
            $notification->toString()
        );

        $push = $notification->toPush(null, null);
        $this->assertSame(__('notifications.announcement.default_message'), $push['message']);
        $this->assertSame(config('carpoolear.name_app'), $push['title']);
        $this->assertSame('/app/home', $push['url']);
    }

    public function test_get_extras_and_push_include_announcement_fields(): void
    {
        $notification = new AnnouncementNotification;
        $notification->setAttribute('message', 'Hello community');
        $notification->setAttribute('announcement_id', 42);
        $notification->setAttribute('external_url', 'https://example.org/post/42');

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('announcement', $extras['type']);
        $this->assertSame('https://example.org/post/42', $extras['external_url']);
        $this->assertSame(42, $extras['announcement_id']);
        $this->assertSame(42, $push['extras']['announcement_id']);
    }
}
