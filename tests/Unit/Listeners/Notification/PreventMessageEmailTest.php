<?php

namespace Tests\Unit\Listeners\Notification;

use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use STS\Events\Notification\NotificationSending;
use STS\Listeners\Notification\PreventMessageEmail;
use STS\Models\Conversation;
use STS\Models\User;
use STS\Notifications\FriendAcceptNotification;
use STS\Notifications\NewMessageNotification;
use STS\Repository\ConversationRepository;
use STS\Services\Notifications\Channels\DatabaseChannel;
use STS\Services\Notifications\Channels\MailChannel;
use STS\Services\Notifications\Channels\PushChannel;
use STS\Services\Notifications\NotificationServices;
use Tests\TestCase;

class PreventMessageEmailTest extends TestCase
{
    private function bindNotificationServicesForBaseNotification(): void
    {
        $this->mock(NotificationServices::class)->shouldIgnoreMissing();
    }

    private function newNewMessageNotification(object $messages): NewMessageNotification
    {
        $this->bindNotificationServicesForBaseNotification();
        $notification = new NewMessageNotification;
        $notification->setAttribute('messages', $messages);

        return $notification;
    }

    public function test_handle_does_not_query_read_state_for_push_channel(): void
    {
        $repo = Mockery::mock(ConversationRepository::class);
        $repo->shouldNotReceive('getConversationReadState');

        $conversation = Mockery::mock(Conversation::class);
        $messages = (object) ['conversation' => $conversation];
        $notification = $this->newNewMessageNotification($messages);

        $listener = new PreventMessageEmail($repo);
        $result = $listener->handle(new NotificationSending(
            $notification,
            Mockery::mock(User::class),
            new PushChannel
        ));

        $this->assertNull($result);
    }

    public function test_handle_does_not_query_read_state_for_non_new_message_notifications(): void
    {
        $repo = Mockery::mock(ConversationRepository::class);
        $repo->shouldNotReceive('getConversationReadState');

        $this->bindNotificationServicesForBaseNotification();
        $notification = new FriendAcceptNotification;

        $listener = new PreventMessageEmail($repo);
        $result = $listener->handle(new NotificationSending(
            $notification,
            Mockery::mock(User::class),
            new MailChannel
        ));

        $this->assertNull($result);
    }

    public static function mailOrDatabaseChannelFactoryProvider(): array
    {
        return [
            'mail' => [fn () => new MailChannel],
            'database' => [fn () => new DatabaseChannel],
        ];
    }

    #[DataProvider('mailOrDatabaseChannelFactoryProvider')]
    public function test_handle_returns_false_when_conversation_read_state_is_one(
        callable $channelFactory,
    ): void {
        $conversation = Mockery::mock(Conversation::class);
        $user = Mockery::mock(User::class);

        $repo = Mockery::mock(ConversationRepository::class);
        $repo->shouldReceive('getConversationReadState')
            ->once()
            ->with($conversation, $user)
            ->andReturn(1);

        $messages = (object) ['conversation' => $conversation];
        $notification = $this->newNewMessageNotification($messages);

        $listener = new PreventMessageEmail($repo);
        $result = $listener->handle(new NotificationSending(
            $notification,
            $user,
            $channelFactory()
        ));

        $this->assertFalse($result);
    }

    public function test_handle_returns_false_when_read_state_is_string_one_under_loose_comparison(): void
    {
        $conversation = Mockery::mock(Conversation::class);
        $user = Mockery::mock(User::class);

        $repo = Mockery::mock(ConversationRepository::class);
        $repo->shouldReceive('getConversationReadState')
            ->once()
            ->with($conversation, $user)
            ->andReturn('1');

        $messages = (object) ['conversation' => $conversation];
        $notification = $this->newNewMessageNotification($messages);

        $listener = new PreventMessageEmail($repo);
        $result = $listener->handle(new NotificationSending(
            $notification,
            $user,
            new MailChannel
        ));

        $this->assertFalse($result, 'Loose != must treat string "1" like integer 1 so email stays suppressed');
    }

    public static function channelWithNonOneReadStateProvider(): array
    {
        return [
            'mail_read_0' => [fn () => new MailChannel, 0],
            'mail_read_2' => [fn () => new MailChannel, 2],
            'database_read_0' => [fn () => new DatabaseChannel, 0],
            'database_read_2' => [fn () => new DatabaseChannel, 2],
        ];
    }

    #[DataProvider('channelWithNonOneReadStateProvider')]
    public function test_handle_returns_true_when_conversation_read_state_is_not_one(
        callable $channelFactory,
        int $readState,
    ): void {
        $conversation = Mockery::mock(Conversation::class);
        $user = Mockery::mock(User::class);

        $repo = Mockery::mock(ConversationRepository::class);
        $repo->shouldReceive('getConversationReadState')
            ->once()
            ->with($conversation, $user)
            ->andReturn($readState);

        $messages = (object) ['conversation' => $conversation];
        $notification = $this->newNewMessageNotification($messages);

        $listener = new PreventMessageEmail($repo);
        $result = $listener->handle(new NotificationSending(
            $notification,
            $user,
            $channelFactory()
        ));

        $this->assertTrue($result);
    }
}
