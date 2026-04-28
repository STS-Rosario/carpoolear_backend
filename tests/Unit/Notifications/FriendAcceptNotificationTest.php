<?php

namespace Tests\Unit\Notifications;

use STS\Models\User;
use STS\Notifications\FriendAcceptNotification;
use Tests\TestCase;

class FriendAcceptNotificationTest extends TestCase
{
    public function test_to_email_uses_sender_data_and_profile_url(): void
    {
        $from = User::factory()->create(['name' => 'Pedro Accept']);
        $notification = new FriendAcceptNotification;
        $notification->setAttribute('from', $from);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.friend_accept.title', ['name' => 'Pedro Accept']), $email['title']);
        $this->assertSame('friends_accept', $email['email_view']);
        $this->assertSame(config('app.url').'/app/profile/'.$from->id, $email['url']);
    }

    public function test_to_string_and_push_fallback_to_someone_when_from_is_missing(): void
    {
        $notification = new FriendAcceptNotification;

        $expectedMessage = __('notifications.friend_accept.message', ['name' => __('notifications.someone')]);
        $this->assertSame($expectedMessage, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expectedMessage, $push['message']);
        $this->assertSame('/setting/friends', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_sender_id_when_available(): void
    {
        $from = User::factory()->create();
        $notification = new FriendAcceptNotification;
        $notification->setAttribute('from', $from);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('friend', $extras['type']);
        $this->assertSame($from->id, $extras['user_id']);
        $this->assertSame($from->id, $push['extras']['id']);
    }
}
