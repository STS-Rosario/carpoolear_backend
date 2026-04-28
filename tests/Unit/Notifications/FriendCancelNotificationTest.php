<?php

namespace Tests\Unit\Notifications;

use STS\Models\User;
use STS\Notifications\FriendCancelNotification;
use Tests\TestCase;

class FriendCancelNotificationTest extends TestCase
{
    public function test_to_email_uses_sender_data_when_from_is_present(): void
    {
        $from = User::factory()->create(['name' => 'Carla Sender']);
        $notification = new FriendCancelNotification;
        $notification->setAttribute('from', $from);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.friend_cancel.title', ['name' => 'Carla Sender']), $email['title']);
        $this->assertSame('friends_cancel_email', $email['email_view']);
        $this->assertSame('cancel', $email['type']);
    }

    public function test_to_string_and_push_fallback_to_someone_when_from_is_missing(): void
    {
        $notification = new FriendCancelNotification;

        $expectedMessage = __('notifications.friend_cancel.message', ['name' => __('notifications.someone')]);
        $this->assertSame($expectedMessage, $notification->toString());

        $push = $notification->toPush(null, null);
        $this->assertSame($expectedMessage, $push['message']);
        $this->assertSame('/setting/friends', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_sender_id_when_available(): void
    {
        $from = User::factory()->create();
        $notification = new FriendCancelNotification;
        $notification->setAttribute('from', $from);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('friends', $extras['type']);
        $this->assertSame($from->id, $push['extras']['id']);
    }
}
