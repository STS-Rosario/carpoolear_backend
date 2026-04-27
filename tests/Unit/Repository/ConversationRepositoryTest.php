<?php

namespace Tests\Unit\Repository;

use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\ConversationRepository;
use Tests\TestCase;

class ConversationRepositoryTest extends TestCase
{
    public function test_store_and_delete(): void
    {
        $repo = new ConversationRepository;
        $conversation = Conversation::factory()->create(['type' => Conversation::TYPE_PRIVATE_CONVERSATION]);

        $conversation->title = 'Updated title';
        $this->assertTrue($repo->store($conversation));
        $this->assertSame('Updated title', $conversation->fresh()->title);

        $this->assertTrue((bool) $repo->delete($conversation));
        $this->assertTrue($conversation->fresh()->trashed());
    }

    public function test_get_conversation_from_id_without_user_returns_model(): void
    {
        $conversation = Conversation::factory()->create();
        $repo = new ConversationRepository;

        $found = $repo->getConversationFromId($conversation->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->is($conversation));
    }

    public function test_get_conversation_from_id_with_user_requires_membership(): void
    {
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($member->id, ['read' => false]);

        $repo = new ConversationRepository;
        $this->assertNotNull($repo->getConversationFromId($conversation->id, $member));
        $this->assertNull($repo->getConversationFromId($conversation->id, $stranger));
    }

    public function test_get_conversations_by_trip_accepts_trip_instance_or_int_id(): void
    {
        $trip = Trip::factory()->create();
        $a = Conversation::factory()->create(['trip_id' => $trip->id, 'type' => Conversation::TYPE_TRIP_CONVERSATION]);
        $b = Conversation::factory()->create(['trip_id' => $trip->id, 'type' => Conversation::TYPE_TRIP_CONVERSATION]);

        $repo = new ConversationRepository;
        $byModel = $repo->getConversationsByTrip($trip);
        $byId = $repo->getConversationsByTrip($trip->id);

        $this->assertCount(2, $byModel);
        $this->assertCount(2, $byId);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $byModel->pluck('id')->all());
    }

    public function test_users_add_user_remove_user(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($u1->id, ['read' => true]);

        $repo = new ConversationRepository;
        $repo->addUser($conversation, $u2->id);

        $conversation = $conversation->fresh();
        $this->assertCount(2, $repo->users($conversation));

        $repo->removeUser($conversation, $u2);
        $this->assertCount(1, $repo->users($conversation->fresh()));
    }

    public function test_change_and_get_conversation_read_state(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($user->id, ['read' => false]);

        $repo = new ConversationRepository;
        $repo->changeConversationReadState($conversation, $user, true);

        $this->assertTrue((bool) $repo->getConversationReadState($conversation->fresh(), $user));
    }

    public function test_match_user_finds_private_conversation_for_both_users(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conversation = Conversation::factory()->create(['type' => Conversation::TYPE_PRIVATE_CONVERSATION]);
        $conversation->users()->attach($u1->id, ['read' => true]);
        $conversation->users()->attach($u2->id, ['read' => true]);

        $repo = new ConversationRepository;
        $found = $repo->matchUser($u1->id, $u2->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($conversation));
    }

    public function test_update_trip_id(): void
    {
        $trip = Trip::factory()->create();
        $conversation = Conversation::factory()->create(['trip_id' => null]);

        $repo = new ConversationRepository;
        $updated = $repo->updateTripId($conversation, $trip->id);

        $this->assertSame($trip->id, $updated->fresh()->trip_id);
    }

    public function test_get_conversations_from_user_requires_messages_and_paginates(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($user->id, ['read' => false]);

        Message::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'text' => 'First message',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $c2 = Conversation::factory()->create();
        $c2->users()->attach($user->id, ['read' => false]);
        Message::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $c2->id,
            'text' => 'Second thread',
            'estado' => Message::STATE_NOLEIDO,
        ]);

        $conversation->touch();
        $c2->touch();

        $repo = new ConversationRepository;
        $page = $repo->getConversationsFromUser($user, 1, 1);
        $this->assertCount(1, $page);
    }
}
