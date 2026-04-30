<?php

namespace Tests\Unit\Services\Logic;

use Illuminate\Support\Facades\Event;
use STS\Events\MessageSend;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\Trip;
use STS\Models\User;
use STS\Services\Logic\ConversationsManager;
use Tests\TestCase;

class ConversationsManagerTest extends TestCase
{
    private function manager(): ConversationsManager
    {
        return $this->app->make(ConversationsManager::class);
    }

    public function test_create_trip_conversation_sets_type_and_trip_id(): void
    {
        $trip = Trip::factory()->create();
        $conv = $this->manager()->createTripConversation($trip->id);

        $this->assertNotNull($conv->id);
        $this->assertSame(Conversation::TYPE_TRIP_CONVERSATION, (int) $conv->type);
        $this->assertSame($trip->id, (int) $conv->trip_id);
        $this->assertSame('', $conv->title);
    }

    public function test_find_or_create_private_conversation_creates_row_for_admin_pair(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();

        $conv = $this->manager()->findOrCreatePrivateConversation($admin, $other);
        $this->assertNotNull($conv);
        $this->assertSame(Conversation::TYPE_PRIVATE_CONVERSATION, (int) $conv->type);
        $this->assertCount(2, $conv->fresh()->users);

        $again = $this->manager()->findOrCreatePrivateConversation($other, $admin);
        $this->assertTrue($again->is($conv));
    }

    public function test_find_or_create_private_conversation_drops_invalid_trip_id_when_creating(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();

        $conversation = $this->manager()->findOrCreatePrivateConversation($admin, $other, 999999999);

        $this->assertNotNull($conversation);
        $this->assertNull($conversation->trip_id);
        $this->assertSame(Conversation::TYPE_PRIVATE_CONVERSATION, (int) $conversation->type);
    }

    public function test_find_or_create_private_conversation_updates_trip_id_on_existing_conversation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $trip = Trip::factory()->create();

        $conversation = $this->manager()->findOrCreatePrivateConversation($admin, $other);
        $this->assertNotNull($conversation);
        $this->assertNull($conversation->trip_id);

        $updated = $this->manager()->findOrCreatePrivateConversation($admin, $other, $trip->id);

        $this->assertTrue($updated->is($conversation));
        $this->assertSame($trip->id, (int) $updated->fresh()->trip_id);
    }

    public function test_show_delegates_to_repository_membership(): void
    {
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($member->id, ['read' => false]);

        $this->assertNotNull($this->manager()->show($member, $conversation->id));
        $this->assertNull($this->manager()->show($stranger, $conversation->id));
    }

    public function test_send_persists_message_and_dispatches_message_send(): void
    {
        Event::fake([MessageSend::class]);
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conversation = Conversation::factory()->create(['type' => Conversation::TYPE_PRIVATE_CONVERSATION]);
        $conversation->users()->attach($u1->id, ['read' => true]);
        $conversation->users()->attach($u2->id, ['read' => true]);

        $msg = $this->manager()->send($u1, $conversation->id, 'Hello from test');

        $this->assertInstanceOf(Message::class, $msg);
        $this->assertSame('Hello from test', $msg->fresh()->text);
        Event::assertDispatched(MessageSend::class);
    }

    public function test_send_validation_failure_sets_errors(): void
    {
        Event::fake([MessageSend::class]);
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->users()->attach($user->id, ['read' => true]);

        $manager = $this->manager();
        $this->assertNull($manager->send($user, $conversation->id, ''));
        $this->assertTrue($manager->getErrors()->has('text'));
        Event::assertNotDispatched(MessageSend::class);
    }

    public function test_send_sets_conversation_error_when_user_has_no_access(): void
    {
        Event::fake([MessageSend::class]);
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $conversation = Conversation::factory()->create(['type' => Conversation::TYPE_PRIVATE_CONVERSATION]);
        $conversation->users()->attach($member->id, ['read' => true]);

        $manager = $this->manager();
        $result = $manager->send($stranger, $conversation->id, 'Hi');

        $this->assertNull($result);
        $this->assertSame('conversation_does_not_exist', $manager->getErrors()['conversation_id']);
        Event::assertNotDispatched(MessageSend::class);
    }

    public function test_delete_soft_deletes_existing_conversation(): void
    {
        $conversation = Conversation::factory()->create();
        $this->manager()->delete($conversation->id);
        $this->assertTrue($conversation->fresh()->trashed());
    }

    public function test_delete_sets_error_when_missing(): void
    {
        $manager = $this->manager();
        $manager->delete(8_888_888_888);

        $this->assertSame('conversation_does_not_exist', $manager->getErrors()['conversation_id']);
    }

    public function test_add_user_to_conversation_adds_participant_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();

        $conversation = Conversation::factory()->create(['type' => Conversation::TYPE_PRIVATE_CONVERSATION]);
        $conversation->users()->attach($admin->id, ['read' => true]);
        $conversation->users()->attach($u2->id, ['read' => true]);

        $this->assertTrue($this->manager()->addUserToConversation($admin, $conversation->id, [$u3->id]));
        $this->assertCount(3, $conversation->fresh()->users);
    }

    public function test_remove_user_from_conversation_removes_member(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $u2 = User::factory()->create();
        $conversation = Conversation::factory()->create(['type' => Conversation::TYPE_PRIVATE_CONVERSATION]);
        $conversation->users()->attach($admin->id, ['read' => true]);
        $conversation->users()->attach($u2->id, ['read' => true]);

        $this->assertTrue($this->manager()->removeUserFromConversation($admin, $conversation->id, $u2));
        $this->assertCount(1, $conversation->fresh()->users);
    }

    public function test_add_user_to_trip_conversation_is_denied(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $u2 = User::factory()->create();
        $tripConversation = Conversation::factory()->create(['type' => Conversation::TYPE_TRIP_CONVERSATION]);
        $tripConversation->users()->attach($admin->id, ['read' => true]);

        $manager = $this->manager();
        $result = $manager->addUserToConversation($admin, $tripConversation->id, [$u2->id]);

        $this->assertNull($result);
        $this->assertSame('user_does_not_have_access_to_conversation', $manager->getErrors()['conversation_id']);
    }

    public function test_get_conversation_returns_trip_conversation_for_admin_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $trip = Trip::factory()->create();
        $conversation = Conversation::factory()->create([
            'trip_id' => $trip->id,
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
        ]);
        $conversation->users()->attach($member->id, ['read' => true]);

        $found = $this->manager()->getConversation($admin, $conversation->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($conversation));
    }

    public function test_get_conversation_by_trip_returns_trip_thread(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'trip_id' => $trip->id,
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
        ]);
        $conversation->users()->attach($user->id, ['read' => true]);

        $found = $this->manager()->getConversationByTrip($user, $trip->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->is($conversation));
    }

    public function test_get_conversation_by_trip_returns_trip_thread_for_admin_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $owner->id]);
        $conversation = Conversation::factory()->create([
            'trip_id' => $trip->id,
            'type' => Conversation::TYPE_TRIP_CONVERSATION,
        ]);
        $conversation->users()->attach($owner->id, ['read' => true]);

        $found = $this->manager()->getConversationByTrip($admin, $trip->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->is($conversation));
    }
}
