<?php

namespace Tests\Unit\Console\Commands;

use STS\Console\Commands\EmailMessageNotification;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\User;
use Tests\TestCase;

class EmailMessageNotificationTest extends TestCase
{
    public function test_handle_marks_only_pending_messages_as_notified(): void
    {
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

        $this->assertSame(1, (int) $pendingOne->fresh()->already_notified);
        $this->assertSame(1, (int) $pendingTwo->fresh()->already_notified);
        $this->assertSame(1, (int) $alreadyNotified->fresh()->already_notified);
    }

    public function test_command_contract_is_defined(): void
    {
        $command = new EmailMessageNotification;

        $this->assertSame('messages:email', $command->getName());
        $this->assertStringContainsString('Notify by email pending messages', $command->getDescription());
    }
}
