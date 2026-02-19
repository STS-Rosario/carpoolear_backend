<?php

namespace Tests\Unit;

use Tests\TestCase;
use STS\Models\User;
use Carbon\Carbon;
use STS\Models\Trip;
use STS\Models\Passenger;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MessagesTest extends TestCase
{
    use DatabaseTransactions;

    protected $userManager;

    protected $conversationManager;

    protected $messageRepository;

    protected $conversationRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->conversationManager = $this->app->make(\STS\Services\Logic\ConversationsManager::class);
        $this->messageRepository = $this->app->make(\STS\Repository\MessageRepository::class);
        $this->conversationRepository = $this->app->make(\STS\Repository\ConversationRepository::class);
    }

    public function test_findOrCreatePrivateConversation()
    {
        $user1 = \STS\Models\User::factory()->create(['is_admin' => true]);
        $user2 = \STS\Models\User::factory()->create();
        $user3 = \STS\Models\User::factory()->create();

        // admin can chat with everybody
        $conversation = $this->conversationManager->findOrCreatePrivateConversation($user1, $user2);
        $this->assertTrue($conversation != null);

        $conversation = $this->conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertTrue($conversation != null);

        // two users thar are not admin, and not friends, they cannot chat
        $conversationFail = $this->conversationManager->findOrCreatePrivateConversation($user2, $user3);
        $this->assertTrue($conversationFail == null);

        // if a conversation exist in database, it must not create a new conversation.
        $conversation2 = $this->conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertTrue($conversation2->id == $conversation->id);
    }

    public function test_addUserToConversation_and_removeUserFromConversation_Success()
    {
        $user = \STS\Models\User::factory()->create(['is_admin' => 1]);
        $user1 = \STS\Models\User::factory()->create();
        $user2 = \STS\Models\User::factory()->create();
        $user3 = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $user1->id]);
        $conversation = \STS\Models\Conversation::factory()->create();

        $this->conversationRepository->addUser($conversation, $user);
        $this->conversationManager->addUserToConversation($user, $conversation->id, $user1->id);
        $this->conversationManager->addUserToConversation($user, $conversation->id, $user2->id);

        $isUser1 = $conversation->users()->where('id', $user1->id)->count();
        $this->assertTrue($isUser1 == 1);

        $isUser2 = $conversation->users()->where('id', $user2->id)->count();
        $this->assertTrue($isUser2 == 1);
        $isUser3 = $conversation->users()->where('id', $user3->id)->count();
        $this->assertTrue($isUser3 == 0);

        $value = $this->conversationManager->removeUserFromConversation($user2, $conversation->id, $user1);
        $isUser1 = $conversation->users()->where('id', $user1->id)->count();
        $this->assertTrue($isUser1 == 0);

        $isUser2 = $conversation->users()->where('id', $user2->id)->count();
        $this->assertTrue($isUser2 == 1);

        $conversation = $this->conversationManager->getConversation($user2, $conversation->id);
        $this->assertNotNull($conversation);
    }

    public function test_TripConversationCreate_Success()
    {
        /* Create a conversation */
        $user = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $user->id]);

        $conversation = $this->conversationManager->createTripConversation($trip->id);
        $this->assertTrue($conversation->type == \STS\Models\Conversation::TYPE_TRIP_CONVERSATION && $conversation->tripId = $trip->id);
    }

    public function test_TripConversationCreate_RepeatTrip_Fail()
    {
        /* Creating a conversation two times - ERROR */
        $user = \STS\Models\User::factory()->create();
        $trip = \STS\Models\Trip::factory()->create(['user_id' => $user->id]);

        $conversation = $this->conversationManager->createTripConversation($trip->id);
        $conversation2 = $this->conversationManager->createTripConversation($trip->id);
        $this->assertFalse($conversation->type == \STS\Models\Conversation::TYPE_TRIP_CONVERSATION && $conversation->tripId = $trip->id && $conversation2 == null);
    }

    public function test_Match_Success()
    {
        $c = \STS\Models\Conversation::factory()->create();

        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();

        $this->conversationRepository->addUser($c, $u1->id);
        $this->conversationRepository->addUser($c, $u2->id);

        $c2 = $this->conversationRepository->matchUser($u1->id, $u2->id);

        $this->assertTrue($c->id == $c2->id);
    }

    public function test_Match_Fail()
    {
        $c = \STS\Models\Conversation::factory()->create();
        $c2 = \STS\Models\Conversation::factory()->create();

        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $u3 = \STS\Models\User::factory()->create();

        $this->conversationRepository->addUser($c, $u1->id);
        $this->conversationRepository->addUser($c, $u2->id);

        $this->conversationRepository->addUser($c2, $u2->id);
        $this->conversationRepository->addUser($c2, $u3->id);

        $cc1 = $this->conversationRepository->matchUser($u1->id, $u2->id);
        $cc2 = $this->conversationRepository->matchUser($u2->id, $u3->id);

        $this->assertFalse($cc1->id == $cc2->id);
    }

    public function test_Send_Message()
    {
        $c = \STS\Models\Conversation::factory()->create();

        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $u3 = \STS\Models\User::factory()->create();

        $m1 = 'test 1';
        $m2 = 'test 2';
        $m3 = 'test 3';

        $this->conversationRepository->addUser($c, $u1->id);
        $this->conversationRepository->addUser($c, $u2->id);
        $this->conversationManager->send($u1, $c->id, $m1);
        $this->conversationManager->send($u2, $c->id, $m2);
        $this->conversationManager->send($u1, $c->id, $m3);
        $this->conversationManager->send($u3, $c->id, $m3);

        $messages = $this->messageRepository->getMessages($c, null, 20);
        $this->assertTrue(count($messages) == 3);
        $this->assertTrue($messages[0]->user_id == $u1->id);
        $this->assertTrue($messages[0]->text == $m1);
        $this->assertTrue($messages[0]->conversation_id == $c->id);
        $this->assertTrue($messages[1]->user_id == $u2->id && $messages[1]->text == $m2 && $messages[1]->conversation_id == $c->id);
        $this->assertTrue($messages[2]->user_id == $u1->id && $messages[2]->text == $m3 && $messages[2]->conversation_id == $c->id);
    }

    public function test_Get_User_Conversations()
    {
        $u1 = \STS\Models\User::factory()->create();

        $c = \STS\Models\Conversation::factory()->count(20)->create();

        for ($i = 0; $i < 20; $i++) {
            $this->conversationRepository->addUser($c[$i], $u1->id);
            $this->conversationManager->send($u1, $c[$i]->id, 'test1');
        }

        $userConversations = $this->conversationManager->getUserConversations($u1);
        $userConversations = json_decode(json_encode($userConversations));
        $this->assertTrue($userConversations->total >= 20);
    }

    public function test_Get_Conversation()
    {
        $u = \STS\Models\User::factory()->create();
        $c = \STS\Models\Conversation::factory()->create();
        $this->conversationRepository->addUser($c, $u->id);

        $this->conversation = $this->conversationManager->getConversation($u, $c->id);
        $this->assertTrue($this->conversation->id == $c->id);

        //invalid user - never have'benn tested'
    }

    public function test_conversation_entity_unread()
    {
        $users = \STS\Models\User::factory()->count(4)->create();
        $c = \STS\Models\Conversation::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->conversationRepository->addUser($c, $users[$i]->id);
        }
        $this->conversationManager->send($users[1], $c->id, 'test1');
        $this->conversationManager->send($users[1], $c->id, 'test2');
        $this->assertTrue($c->read($users[0]) == false && $c->read($users[2]) == false && $c->read($users[3]) == false);
        $this->assertTrue($c->read($users[1]) == true);
        $this->assertTrue($c->users()->wherePivot('read', true)->count() == 1);
        $this->assertTrue($c->users()->wherePivot('read', false)->count() == 3);
    }

    public function test_touching()
    {
        $u = \STS\Models\User::factory()->create();
        $c1 = \STS\Models\Conversation::factory()->create();
        $c2 = \STS\Models\Conversation::factory()->create();
        $c3 = \STS\Models\Conversation::factory()->create();

        $c1->updated_at = Carbon::create(1999, 1, 1, 0, 0, 0);
        $c2->updated_at = Carbon::create(2000, 1, 1, 0, 0, 0);
        $c3->updated_at = Carbon::create(2001, 1, 1, 0, 0, 0);
        $c1->save(['timestamps' => false]);
        $c2->save(['timestamps' => false]);
        $c3->save(['timestamps' => false]);

        $this->conversationRepository->addUser($c1, $u->id);
        $this->conversationRepository->addUser($c2, $u->id);
        $this->conversationRepository->addUser($c3, $u->id);

        $this->conversationManager->send($u, $c1->id, 'test1');
        $this->conversationManager->send($u, $c2->id, 'test1');
        $this->conversationManager->send($u, $c3->id, 'test1');

        $userConversations = $this->conversationManager->getUserConversations($u);
        $userConversations = json_decode(json_encode($userConversations));

        $this->assertTrue($userConversations->total === 3);

        // $this->conversationManager->send($u, $c2->id, 'test');
        // $userConversations = $this->conversationManager->getUserConversations($u);

        // $this->assertTrue($userConversations[0]->id == $c2->id &&
        // $userConversations[1]->id == $c3->id &&
        // $userConversations[2]->id == $c1->id);
    }

    public function test_get_all_messages_from_conversation()
    {
        $u1 = \STS\Models\User::factory()->create();
        $u2 = \STS\Models\User::factory()->create();
        $c = \STS\Models\Conversation::factory()->create();
        $this->conversationRepository->addUser($c, $u1->id);
        $this->conversationRepository->addUser($c, $u2->id);
        for ($i = 0; $i < 3; $i++) {
            $m = 'text'.$i;
            $this->conversationManager->send($u1, $c->id, $m);
        }
        $this->conversationManager->send($u2, $c->id, 'new');

        $messages = $this->conversationManager->getAllMessagesFromConversation($c->id, $u2, false);

        $this->assertTrue(count($messages) == 4);

        $this->conversationManager->send($u1, $c->id, 'new');

        $messages = $this->conversationManager->getUnreadMessagesFromConversation($c->id, $u2, false);

        $this->assertTrue(count($messages) == 4);

        $this->conversationManager->send($u1, $c->id, 'new');
        $this->conversationManager->send($u2, $c->id, 'new');

        $messages = $this->conversationManager->getUnreadMessagesFromConversation($c->id, $u2, true);
        $messages = json_decode(json_encode($messages));
        $this->assertTrue(count($messages) == 5);

        $this->conversationManager->send($u1, $c->id, 'new');

        $messages = $this->conversationManager->getUnreadMessagesFromConversation($c->id, $u2, false);
        $this->assertTrue(count($messages) == 1);
    }

    public function test_getConversationByTrip_and_delete_Success()
    {
        $u = \STS\Models\User::factory()->create();
        $t = \STS\Models\Trip::factory()->create(['user_id' => $u->id]);

        $c = $this->conversationManager->createTripConversation($t->id);

        $conversation = $this->conversationManager->getConversationByTrip($u, $t->id);
        $this->assertTrue($conversation->id == $c->id);

        $this->conversationManager->delete($c->id);

        $conversation = $this->conversationManager->getConversation($u, $c->id);

        $this->assertTrue($conversation == null);

        $conversationResult = $this->conversationManager->delete($c->id);

        $this->assertTrue($conversationResult == null);
    }

    public function test_getUsers()
    {
        $u = \STS\Models\User::factory()->count(24)->create();
        $c = \STS\Models\Conversation::factory()->create();

        for ($i = 0; $i < 22; $i++) {
            $this->conversationRepository->addUser($c, $u[$i]->id);
        }
        $this->assertTrue(count($this->conversationManager->getUsersFromConversation($u[0], $c->id)) == 22);
    }

    public function test_get_conversation_trip()
    {
        $u = \STS\Models\User::factory()->create();
        $t = \STS\Models\Trip::factory()->create(['user_id' => $u->id]);
        $c = \STS\Models\Conversation::factory()->create(['trip_id' => $t->id]);

        $this->assertTrue($t->conversation->id == $c->id);
    }

    public function test_create_conversation_listeners()
    {
        $u = \STS\Models\User::factory()->create();
        $t = \STS\Models\Trip::factory()->create(['user_id' => $u->id]);

        $event = new \STS\Events\Trip\Create($t);

        $listener = new \STS\Listeners\Conversation\createConversation($this->conversationManager, $this->conversationRepository);

        $listener->handle($event);

        $this->assertNotNull($t->conversation);
    }

    public function test_add_remove_user_conversation_trip()
    {
        $u = \STS\Models\User::factory()->create();
        $accepted = \STS\Models\User::factory()->create();
        $t = \STS\Models\Trip::factory()->create(['user_id' => $u->id]);
        $c = \STS\Models\Conversation::factory()->create(['trip_id' => $t->id]);

        $event = new \STS\Events\Passenger\Accept($t, $u, $accepted);

        $listener = new \STS\Listeners\Conversation\addUserConversation($this->conversationRepository);

        $listener->handle($event);

        $this->assertTrue($c->users()->count() == 1);

        $event = new \STS\Events\Passenger\Cancel($t, $u, $accepted, 0);

        $listener = new \STS\Listeners\Conversation\removeUserConversation($this->conversationRepository);

        $listener->handle($event);

        $this->assertTrue($c->users()->count() == 0);
    }

    public function test_user_list()
    {
        $driver = \STS\Models\User::factory()->create();
        $passengerA = \STS\Models\User::factory()->create();
        $passengerB = \STS\Models\User::factory()->create();

        $trip = \STS\Models\Trip::factory()->create(['user_id' => $driver->id]);

        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        \STS\Models\Passenger::factory()->aceptado()->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $users = $this->conversationRepository->userList($driver);
        $this->assertCount(0, $users, 'No conversations with messages exist yet');

        $users = $this->conversationRepository->userList($passengerA);
        $this->assertCount(0, $users, 'No conversations with messages exist yet');
    }
}
