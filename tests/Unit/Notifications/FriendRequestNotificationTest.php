<?php

namespace Tests\Unit\Notifications;

use STS\Models\User;
use STS\Notifications\FriendRequestNotification;
use Tests\TestCase;

class FriendRequestNotificationTest extends TestCase
{
    public function test_to_email_returns_expected_static_payload(): void
    {
        $notification = new FriendRequestNotification;

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.friend_request.title'), $email['title']);
        $this->assertSame('friends_request', $email['email_view']);
        $this->assertSame('request', $email['type']);
        $this->assertSame(config('app.url').'/app/setting/friends', $email['url']);
    }

    public function test_to_string_and_push_fallback_to_someone_when_from_is_missing(): void
    {
        $notification = new FriendRequestNotification;

        $expectedMessage = __('notifications.friend_request.message', ['name' => __('notifications.someone')]);
        $this->assertSame($expectedMessage, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expectedMessage, $push['message']);
        $this->assertSame('/setting/friends', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_sender_id_when_available(): void
    {
        $from = User::factory()->create();
        $notification = new FriendRequestNotification;
        $notification->setAttribute('from', $from);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('friends', $extras['type']);
        $this->assertSame($from->id, $extras['user_id']);
        $this->assertSame($from->id, $push['extras']['id']);
    }
}
