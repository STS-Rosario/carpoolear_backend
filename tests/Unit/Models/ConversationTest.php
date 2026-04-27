<?php

namespace Tests\Unit\Models;

use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\Trip;
use STS\Models\User;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    public function test_users_pivot_and_read_helper(): void
    {
        $conversation = Conversation::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $stranger = User::factory()->create();

        $conversation->users()->attach($alice->id, ['read' => true]);
        $conversation->users()->attach($bob->id, ['read' => false]);

        $conversation = $conversation->fresh();
        $this->assertTrue((bool) $conversation->read($alice));
        $this->assertFalse((bool) $conversation->read($bob));
        $this->assertFalse($conversation->read($stranger));
    }

    public function test_messages_has_many(): void
    {
        $author = User::factory()->create();
        $conversation = Conversation::factory()->create();

        foreach (['one', 'two', 'three'] as $suffix) {
            Message::query()->create([
                'user_id' => $author->id,
                'conversation_id' => $conversation->id,
                'text' => 'Message '.$suffix,
                'estado' => Message::STATE_NOLEIDO,
            ]);
        }

        $conversation = $conversation->fresh();
        $this->assertSame(3, $conversation->messages()->count());
        $this->assertSame(3, $conversation->messages->count());
    }

    public function test_persists_type_title_and_trip_id(): void
    {
        $driver = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $conversation = Conversation::factory()->create([
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
            'title' => 'Trip thread',
            'trip_id' => $trip->id,
        ]);

        $conversation = $conversation->fresh();
        $this->assertSame(Conversation::TYPE_TRIP_CONVERSATION, $conversation->type);
        $this->assertSame('Trip thread', $conversation->title);
        $this->assertSame($trip->id, $conversation->trip_id);
    }

    public function test_soft_delete_excludes_from_default_query(): void
    {
        $conversation = Conversation::factory()->create();
        $id = $conversation->id;

        $conversation->delete();

        $this->assertNull(Conversation::query()->find($id));
        $restored = Conversation::withTrashed()->find($id);
        $this->assertNotNull($restored);
        $this->assertTrue($restored->trashed());
    }

    public function test_type_constants(): void
    {
        $this->assertSame(0, Conversation::TYPE_PRIVATE_CONVERSATION);
        $this->assertSame(1, Conversation::TYPE_TRIP_CONVERSATION);
    }

    public function test_table_name_is_conversations(): void
    {
        $this->assertSame('conversations', (new Conversation)->getTable());
    }
}
