<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use STS\Events\MessageSend;
use STS\Models\Conversation;
use STS\Models\Trip;
use STS\Models\User;
use STS\Repository\ConversationRepository;
use STS\Repository\MessageRepository;
use STS\Services\Logic\ConversationsManager;
use Tests\TestCase;

class MessagesTest extends TestCase
{
    use DatabaseTransactions;

    private ConversationsManager $conversationManager;

    private MessageRepository $messageRepository;

    private ConversationRepository $conversationRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conversationManager = $this->app->make(ConversationsManager::class);
        $this->messageRepository = $this->app->make(MessageRepository::class);
        $this->conversationRepository = $this->app->make(ConversationRepository::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function privateConversationWithTwoUsers(): array
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'type' => Conversation::TYPE_PRIVATE_CONVERSATION,
        ]);
        $conversation->users()->attach($u1->id, ['read' => true]);
        $conversation->users()->attach($u2->id, ['read' => true]);

        return [$conversation, $u1, $u2];
    }

    public function test_find_or_create_private_conversation()
    {
        $user1 = User::factory()->create(['is_admin' => true]);
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // admin can chat with everybody
        $conversation = $this->conversationManager->findOrCreatePrivateConversation($user1, $user2);
        $this->assertNotNull($conversation);

        $conversation = $this->conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertNotNull($conversation);

        // two users thar are not admin, and not friends, they cannot chat
        $conversationFail = $this->conversationManager->findOrCreatePrivateConversation($user2, $user3);
        $this->assertNull($conversationFail);

        // if a conversation exist in database, it must not create a new conversation.
        $conversation2 = $this->conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertTrue($conversation2->is($conversation));
    }

    public function test_add_and_remove_user_from_conversation_success(): void
    {
        $user = User::factory()->create(['is_admin' => 1]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $conversation = Conversation::factory()->create();

        $this->conversationRepository->addUser($conversation, $user);
        $this->conversationManager->addUserToConversation($user, $conversation->id, $user1->id);
        $this->conversationManager->addUserToConversation($user, $conversation->id, $user2->id);

        $this->assertSame(1, $conversation->users()->where('id', $user1->id)->count());
        $this->assertSame(1, $conversation->users()->where('id', $user2->id)->count());
        $this->assertSame(0, $conversation->users()->where('id', $user3->id)->count());

        $this->assertTrue($this->conversationManager->removeUserFromConversation($user2, $conversation->id, $user1));
        $this->assertSame(0, $conversation->users()->where('id', $user1->id)->count());
        $this->assertSame(1, $conversation->users()->where('id', $user2->id)->count());

        $loaded = $this->conversationManager->getConversation($user2, $conversation->id);
        $this->assertNotNull($loaded);
    }

    public function test_create_trip_conversation_and_get_by_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $conversation = $this->conversationManager->createTripConversation($trip->id);
        $this->assertNotNull($conversation);
        $this->assertSame(Conversation::TYPE_TRIP_CONVERSATION, (int) $conversation->type);
        $this->assertSame($trip->id, (int) $conversation->trip_id);

        $found = $this->conversationManager->getConversationByTrip($user, $trip->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->is($conversation));
    }

    public function test_send_message_persists_order_and_dispatches_event(): void
    {
        Event::fake([MessageSend::class]);
        [$c, $u1, $u2] = $this->privateConversationWithTwoUsers();
        $u3 = User::factory()->create();

        $this->conversationManager->send($u1, $c->id, 'test 1');
        $this->conversationManager->send($u2, $c->id, 'test 2');
        $this->conversationManager->send($u1, $c->id, 'test 3');
        $this->assertNull($this->conversationManager->send($u3, $c->id, 'test 4'));

        $messages = $this->messageRepository->getMessages($c, null, 20);
        $this->assertCount(3, $messages);
        $this->assertSame('test 3', $messages[0]->text);
        $this->assertSame('test 2', $messages[1]->text);
        $this->assertSame('test 1', $messages[2]->text);
        Event::assertDispatched(MessageSend::class, 3);
    }

    public function test_conversation_unread_state_and_unread_fetch_flow(): void
    {
        Carbon::setTestNow('2028-01-01 12:00:00');
        [$c, $u1, $u2] = $this->privateConversationWithTwoUsers();

        $this->conversationManager->send($u1, $c->id, 'text0');
        $this->conversationManager->send($u1, $c->id, 'text1');
        $this->conversationManager->send($u1, $c->id, 'text2');
        $this->conversationManager->send($u2, $c->id, 'new');

        $all = $this->conversationManager->getAllMessagesFromConversation($c->id, $u2, false);
        $this->assertCount(4, $all);

        $this->conversationManager->send($u1, $c->id, 'new from u1');
        $unread = $this->conversationManager->getUnreadMessagesFromConversation($c->id, $u2, false);
        $this->assertCount(4, $unread);

        $this->conversationManager->send($u1, $c->id, 'another from u1');
        $this->conversationManager->send($u2, $c->id, 'self from u2');
        $afterRead = $this->conversationManager->getUnreadMessagesFromConversation($c->id, $u2, true);
        $this->assertCount(5, $afterRead);

        $this->conversationManager->send($u1, $c->id, 'last unread');
        $delta = $this->conversationManager->getUnreadMessagesFromConversation($c->id, $u2, false);
        $this->assertCount(1, $delta);
        $this->assertSame('last unread', $delta[0]->text);
    }

    public function test_get_user_conversations_and_get_users_from_conversation(): void
    {
        $user = User::factory()->create();
        $conversations = Conversation::factory()->count(8)->create();

        foreach ($conversations as $conversation) {
            $this->conversationRepository->addUser($conversation, $user->id);
            $this->conversationManager->send($user, $conversation->id, 'ping');
        }

        $page = $this->conversationManager->getUserConversations($user);
        $payload = json_decode(json_encode($page));
        $this->assertGreaterThanOrEqual(8, $payload->total);

        $members = User::factory()->count(10)->create();
        $group = Conversation::factory()->create();
        foreach ($members as $member) {
            $this->conversationRepository->addUser($group, $member->id);
        }

        $users = $this->conversationManager->getUsersFromConversation($members[0], $group->id);
        $this->assertCount(10, $users);
    }

    public function test_delete_conversation_soft_deletes_and_second_delete_returns_null(): void
    {
        [$conversation, $u1] = $this->privateConversationWithTwoUsers();
        $this->conversationManager->delete($conversation->id);

        $this->assertNull($this->conversationManager->getConversation($u1, $conversation->id));
        $this->assertNull($this->conversationManager->delete($conversation->id));
        $this->assertTrue($conversation->fresh()->trashed());
    }

    public function test_conversation_listeners_create_and_add_or_remove_trip_participant(): void
    {
        $driver = User::factory()->create();
        $accepted = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $driver->id]);

        $createListener = new \STS\Listeners\Conversation\createConversation(
            $this->conversationManager,
            $this->conversationRepository
        );
        $createListener->handle(new \STS\Events\Trip\Create($trip));
        $this->assertNotNull($trip->fresh()->conversation);

        $conversation = $trip->fresh()->conversation;
        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertSame(1, $conversation->users()->count(), 'trip creator is attached on conversation creation');

        $addListener = new \STS\Listeners\Conversation\addUserConversation($this->conversationRepository);
        $addListener->handle(new \STS\Events\Passenger\Accept($trip, $driver, $accepted));
        $this->assertSame(2, $conversation->fresh()->users()->count());

        $removeListener = new \STS\Listeners\Conversation\removeUserConversation($this->conversationRepository);
        $removeListener->handle(new \STS\Events\Passenger\Cancel($trip, $driver, $accepted, 0));
        $this->assertSame(1, $conversation->fresh()->users()->count());
    }
}
