<?php

namespace Tests\Unit\Notifications;

use STS\Models\User;
use STS\Notifications\NewMessageNotification;
use Tests\TestCase;

class NewMessageNotificationTest extends TestCase
{
    public function test_to_email_uses_sender_and_conversation_id_when_message_exists(): void
    {
        $from = User::factory()->create(['name' => 'Inbox Sender']);
        $message = (object) [
            'conversation_id' => 456,
            'text' => 'Hello inbox',
        ];
        $notification = new NewMessageNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('messages', $message);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.new_message.title', ['name' => 'Inbox Sender']), $email['title']);
        $this->assertSame('new_message', $email['email_view']);
        $this->assertSame(config('app.url').'/app/conversations/456', $email['url']);
    }

    public function test_to_string_and_to_push_fallback_to_someone_when_sender_is_missing(): void
    {
        $notification = new NewMessageNotification;

        $this->assertSame(
            __('notifications.new_message.title', ['name' => __('notifications.someone')]),
            $notification->toString()
        );

        $push = $notification->toPush(null, null);
        $this->assertSame(
            __('notifications.new_message.message', ['name' => __('notifications.someone')]),
            $push['message']
        );
        $this->assertSame('/conversations/', $push['url']);
        $this->assertNull($push['extras']['id']);
    }

    public function test_get_extras_and_push_include_conversation_id_when_message_exists(): void
    {
        $message = (object) [
            'conversation_id' => 999,
            'text' => 'Ping',
        ];
        $from = User::factory()->create(['name' => 'Ana']);
        $notification = new NewMessageNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('messages', $message);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('conversation', $extras['type']);
        $this->assertSame(999, $extras['conversation_id']);
        $this->assertSame('/conversations/999', $push['url']);
        $this->assertSame(999, $push['extras']['id']);
    }
}
