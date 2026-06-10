<?php

namespace Tests\Unit\Listeners\Notification;

use Illuminate\Support\Facades\Log;
use STS\Events\MessageSend as SendEvent;
use STS\Listeners\Notification\MessageSend;
use STS\Models\Message;
use STS\Models\User;
use STS\Notifications\NewMessagePushNotification;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class MessageSendListenerTest extends TestCase
{
    public function test_handle_forwards_event_payload_into_notification_and_sends_via_each_channel(): void
    {
        $from = (object) ['id' => 10, 'name' => 'Sender'];
        $to = (object) ['id' => 20];
        $message = (object) ['id' => 30, 'text' => 'hello', 'conversation_id' => 7];

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->times(2)
            ->withArgs(function ($notification, $users, $channel) use ($from, $to, $message) {
                if (! $notification instanceof NewMessagePushNotification) {
                    return false;
                }

                return $notification->getAttribute('from') === $from
                    && $notification->getAttribute('messages') === $message
                    && $users === $to
                    && is_string($channel);
            });

        $listener = new MessageSend;
        $listener->handle(new SendEvent($from, $to, $message));
    }

    public function test_handle_logs_and_does_not_rethrow_when_notification_send_fails(): void
    {
        $from = (object) ['id' => 1, 'name' => 'A'];
        $to = (object) ['id' => 2];
        $message = (object) ['id' => 3, 'text' => 'x', 'conversation_id' => 1];

        $this->mock(NotificationServices::class)
            ->shouldReceive('send')
            ->andThrow(new \RuntimeException('push channel unavailable'));

        Log::spy();

        $listener = new MessageSend;
        $listener->handle(new SendEvent($from, $to, $message));

        Log::shouldHaveReceived('warning')
            ->with('Error on sending notification', ['message' => 'push channel unavailable'])
            ->once();
    }

    public function test_handle_skips_notification_when_recipient_muted_conversation(): void
    {
        $from = User::factory()->create(['name' => 'Sender']);
        $to = User::factory()->create();
        $conversation = \STS\Models\Conversation::factory()->create();
        $conversation->users()->attach($to->id, ['read' => true, 'notifications_enabled' => false]);
        $message = Message::query()->create([
            'user_id' => $from->id,
            'conversation_id' => $conversation->id,
            'text' => 'hello',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $this->mock(NotificationServices::class)->shouldNotReceive('send');

        $listener = new MessageSend;
        $listener->handle(new SendEvent($from, $to, $message));
    }
}
