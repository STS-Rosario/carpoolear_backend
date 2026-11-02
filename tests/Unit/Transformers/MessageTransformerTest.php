<?php

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\Passenger;
use STS\Models\Trip;
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
            'is_system',
            'used_for_seat_request',
        ], array_keys($payload));
        $this->assertFalse($payload['used_for_seat_request']);
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

    public function test_transform_marks_message_used_for_seat_request_when_linked(): void
    {
        $author = User::factory()->create();
        $driver = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $message = Message::query()->create([
            'user_id' => $author->id,
            'conversation_id' => $conversation->id,
            'text' => 'Quiero ir',
            'estado' => Message::STATE_NOLEIDO,
        ]);
        $trip = Trip::factory()->create(['user_id' => $driver->id]);
        Passenger::factory()->create([
            'trip_id' => $trip->id,
            'user_id' => $author->id,
            'message_id' => $message->id,
        ]);

        $payload = (new MessageTransformer($driver))->transform($message->fresh());

        $this->assertTrue($payload['used_for_seat_request']);
    }
}
