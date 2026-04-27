<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\User;
use Tests\TestCase;

class MessageTest extends TestCase
{
    public function test_from_and_conversation_relationships(): void
    {
        $author = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'Hello from tests.',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $message = $message->fresh();
        $this->assertTrue($message->from->is($author));
        $this->assertTrue($message->conversation->is($conversation));
    }

    public function test_user_id_and_conversation_id_cast_to_integer(): void
    {
        $author = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'Ping',
            'estado' => Message::STATE_LEIDO,
        ]);

        $message = $message->fresh();
        $this->assertIsInt($message->user_id);
        $this->assertIsInt($message->conversation_id);
        $this->assertSame($author->id, $message->user_id);
        $this->assertSame($conversation->id, $message->conversation_id);
    }

    public function test_created_at_casts_to_carbon(): void
    {
        $author = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'Timestamp check',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $message = $message->fresh();
        $this->assertInstanceOf(Carbon::class, $message->created_at);
    }

    public function test_saving_message_touches_parent_conversation(): void
    {
        Carbon::setTestNow('2026-05-01 10:00:00');
        $author = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $before = $conversation->fresh()->updated_at;

        Carbon::setTestNow('2026-05-01 10:05:00');
        Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'This should bump the conversation.',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $conversation->refresh();
        $this->assertTrue(
            $conversation->updated_at->greaterThan($before),
            'Expected conversation updated_at to advance when a message is saved (touches).'
        );

        Carbon::setTestNow();
    }

    public function test_read_returns_pivot_read_flag_for_attached_user(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'Read me',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $message->users()->attach($reader->id, ['read' => true]);

        $this->assertTrue((bool) $message->fresh()->read($reader));
    }

    public function test_number_of_read_includes_base_one_and_pivot_reads(): void
    {
        $author = User::factory()->create();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'Broadcast',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $this->assertSame(1, $message->fresh()->numberOfRead());

        $message->users()->attach($u1->id, ['read' => true]);
        $message->users()->attach($u2->id, ['read' => false]);

        $this->assertSame(2, $message->fresh()->numberOfRead());
    }

    public function test_state_constants(): void
    {
        $this->assertSame(0, Message::STATE_NOLEIDO);
        $this->assertSame(1, Message::STATE_LEIDO);
    }

    public function test_table_name_is_messages(): void
    {
        $this->assertSame('messages', (new Message)->getTable());
    }
}
