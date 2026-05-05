<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use STS\Console\Commands\EmailMessageNotification;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\User;
use Tests\TestCase;

class EmailMessageNotificationTest extends TestCase
{
    public function test_command_is_resolvable_and_exposes_expected_contract(): void
    {
        /** @var EmailMessageNotification $command */
        $command = app(EmailMessageNotification::class);

        $this->assertSame('messages:email', $command->getName());
        $this->assertStringContainsString('Notify by email pending messages', $command->getDescription());
    }

    public function test_handle_marks_only_pending_messages_as_notified(): void
    {
        Event::fake([MessageLogged::class]);

        $author = User::factory()->create();
        $recipientA = User::factory()->create();
        $recipientB = User::factory()->create();

        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
            'title' => 'Conversation',
            'trip_id' => null,
        ]);

        $pendingOne = Message::query()->create([
            'text' => 'first',
            'estado' => Message::STATE_NOLEIDO,
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
        ]);
        $pendingTwo = Message::query()->create([
            'text' => 'second',
            'estado' => Message::STATE_NOLEIDO,
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
        ]);
        $alreadyNotified = Message::query()->create([
            'text' => 'third',
            'estado' => Message::STATE_NOLEIDO,
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
        ]);

        $pendingOne->users()->attach($recipientA->id, ['read' => false]);
        $pendingOne->users()->attach($recipientB->id, ['read' => false]);
        $pendingTwo->users()->attach($recipientA->id, ['read' => false]);
        $alreadyNotified->users()->attach($recipientA->id, ['read' => false]);

        $pendingOne->forceFill(['already_notified' => 0])->saveQuietly();
        $pendingTwo->forceFill(['already_notified' => 0])->saveQuietly();
        $alreadyNotified->forceFill(['already_notified' => 1])->saveQuietly();

        $this->artisan('messages:email')->assertExitCode(0);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $e): bool {
            return $e->level === 'info' && $e->message === 'COMMAND EmailMessageNotification';
        });

        $this->assertSame(1, (int) $pendingOne->fresh()->already_notified);
        $this->assertSame(1, (int) $pendingTwo->fresh()->already_notified);
        $this->assertSame(1, (int) $alreadyNotified->fresh()->already_notified);
    }

    public function test_handle_marks_pending_messages_across_multiple_conversations_and_authors(): void
    {
        Event::fake([MessageLogged::class]);

        $authorA = User::factory()->create();
        $authorB = User::factory()->create();
        $recipient = User::factory()->create();

        $conversationOne = Conversation::query()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
            'title' => 'Conversation one',
            'trip_id' => null,
        ]);
        $conversationTwo = Conversation::query()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
            'title' => 'Conversation two',
            'trip_id' => null,
        ]);

        $messageOne = Message::query()->create([
            'text' => 'one',
            'estado' => Message::STATE_NOLEIDO,
            'user_id' => $authorA->id,
            'conversation_id' => $conversationOne->id,
        ]);
        $messageTwo = Message::query()->create([
            'text' => 'two',
            'estado' => Message::STATE_NOLEIDO,
            'user_id' => $authorA->id,
            'conversation_id' => $conversationTwo->id,
        ]);
        $messageThree = Message::query()->create([
            'text' => 'three',
            'estado' => Message::STATE_NOLEIDO,
            'user_id' => $authorB->id,
            'conversation_id' => $conversationOne->id,
        ]);

        $messageOne->users()->attach($recipient->id, ['read' => false]);
        $messageTwo->users()->attach($recipient->id, ['read' => false]);
        $messageThree->users()->attach($recipient->id, ['read' => false]);

        $messageOne->forceFill(['already_notified' => 0])->saveQuietly();
        $messageTwo->forceFill(['already_notified' => 0])->saveQuietly();
        $messageThree->forceFill(['already_notified' => 0])->saveQuietly();

        $this->artisan('messages:email')->assertExitCode(0);

        $this->assertSame(1, (int) $messageOne->fresh()->already_notified);
        $this->assertSame(1, (int) $messageTwo->fresh()->already_notified);
        $this->assertSame(1, (int) $messageThree->fresh()->already_notified);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new EmailMessageNotification;

        $this->assertSame('messages:email', $command->getName());
        $this->assertStringContainsString('Notify by email pending messages', $command->getDescription());
    }
}
