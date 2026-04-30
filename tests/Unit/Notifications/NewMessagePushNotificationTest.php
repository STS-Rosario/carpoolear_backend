<?php

namespace Tests\Unit\Notifications;

use STS\Models\User;
use STS\Notifications\NewMessagePushNotification;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\PushChannel;
use Tests\TestCase;

class NewMessagePushNotificationTest extends TestCase
{
    public function test_via_contains_push_and_database_channels(): void
    {
        $notification = new NewMessagePushNotification;

        $this->assertSame([
            PushChannel::class,
            DatabaseChannel::class,
        ], $notification->getVia());
    }

    public function test_to_email_uses_sender_and_conversation_id_when_message_exists(): void
    {
        config([
            'carpoolear.name_app' => 'Carpoolear Test',
            'app.url' => 'https://app.test',
        ]);

        $from = User::factory()->create(['name' => 'Chat Sender']);
        $message = (object) [
            'conversation_id' => 321,
            'text' => 'Hello there',
        ];
        $notification = new NewMessagePushNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('messages', $message);

        $email = $notification->toEmail(null);

        $this->assertSame(__('notifications.new_message.title', ['name' => 'Chat Sender']), $email['title']);
        $this->assertSame('new_message', $email['email_view']);
        $this->assertSame('https://app.test/app/conversations/321', $email['url']);
        $this->assertSame('Carpoolear Test', $email['name_app']);
        $this->assertSame('https://app.test', $email['domain']);
    }

    public function test_to_string_and_to_push_fallbacks_when_sender_and_message_are_missing(): void
    {
        $notification = new NewMessagePushNotification;

        $this->assertSame(
            __('notifications.new_message.title', ['name' => __('notifications.someone')]),
            $notification->toString()
        );

        $push = $notification->toPush(null, null);
        $this->assertSame(__('notifications.new_message.new_message').' @ ', $push['message']);
        $this->assertSame('/conversations/', $push['url']);
        $this->assertSame('', $push['extras']['id']);
        $this->assertSame('conversation', $push['type']);
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $push['image']);
    }

    public function test_get_extras_and_push_include_conversation_and_text_when_available(): void
    {
        $message = (object) [
            'conversation_id' => 777,
            'text' => 'Ping',
        ];
        $from = User::factory()->create(['name' => 'Ana']);
        $notification = new NewMessagePushNotification;
        $notification->setAttribute('from', $from);
        $notification->setAttribute('messages', $message);

        $extras = $notification->getExtras();
        $push = $notification->toPush(null, null);

        $this->assertSame('conversation', $extras['type']);
        $this->assertSame(777, $extras['conversation_id']);
        $this->assertSame('Ana @ Ping', $push['message']);
        $this->assertSame('/conversations/777', $push['url']);
        $this->assertSame(777, $push['extras']['id']);
        $this->assertSame('conversation', $push['type']);
        $this->assertSame('https://carpoolear.com.ar/app/static/img/carpoolear_logo.png', $push['image']);
    }
}
