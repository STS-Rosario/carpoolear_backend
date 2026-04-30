<?php

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\User;
use STS\Transformers\MessageTransformer;
use Tests\TestCase;

class MessageTransformerTest extends TestCase
{
    public function test_transform_includes_base_message_fields(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'Hello there',
            'estado' => Message::STATE_NOLEIDO,
        ]);
        $message->forceFill(['created_at' => Carbon::parse('2026-04-30 14:00:00')])->saveQuietly();

        $payload = (new MessageTransformer($viewer))->transform($message->fresh());

        $this->assertSame([
            'id',
            'text',
            'created_at',
            'user_id',
            'conversation_id',
        ], array_keys($payload));
        $this->assertSame($message->id, $payload['id']);
        $this->assertSame('Hello there', $payload['text']);
        $this->assertSame('2026-04-30 14:00:00', $payload['created_at']);
        $this->assertSame($author->id, $payload['user_id']);
        $this->assertSame($conversation->id, $payload['conversation_id']);
        $this->assertArrayNotHasKey('no_of_read', $payload);
    }

    public function test_transform_adds_no_of_read_for_message_author(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'Author message',
            'estado' => Message::STATE_NOLEIDO,
        ]);
        $message->users()->attach($reader->id, ['read' => true]);

        $payload = (new MessageTransformer($author))->transform($message->fresh());

        $this->assertArrayHasKey('no_of_read', $payload);
        $this->assertSame(2, $payload['no_of_read']);
    }

    public function test_transform_treats_numeric_string_message_user_id_as_author_for_no_of_read(): void
    {
        $author = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $message = Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'Loose id match',
            'estado' => Message::STATE_NOLEIDO,
        ]);
        $message->users()->attach($author->id, ['read' => false]);

        $message = $message->fresh();
        $message->mergeCasts(['user_id' => 'string']);
        $message->forceFill(['user_id' => (string) $author->id])->saveQuietly();

        $payload = (new MessageTransformer($author))->transform($message->fresh());

        $this->assertArrayHasKey('no_of_read', $payload);
        $this->assertSame(1, $payload['no_of_read']);
    }
}
