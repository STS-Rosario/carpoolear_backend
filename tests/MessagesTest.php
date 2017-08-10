<?php

use STS\User;
use Carbon\Carbon;
use STS\Entities\Trip;
use STS\Entities\Passenger;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MessagesTest extends TestCase
{
    use DatabaseTransactions;

    protected $userManager;

    protected $conversationManager;
    protected $messageRepository;
    protected $conversationRepository;

    public function setUp()
    {
        parent::setUp();
        $this->conversationManager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $this->messageRepository = $this->app->make('\STS\Contracts\Repository\Messages');
        $this->conversationRepository = $this->app->make('\STS\Contracts\Repository\Conversations');
    }

    public function test_findOrCreatePrivateConversation()
    {
        $user1 = factory(\STS\User::class)->create(['is_admin' => true]);
        $user2 = factory(\STS\User::class)->create();
        $user3 = factory(\STS\User::class)->create();

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
        $user = factory(\STS\User::class)->create(['is_admin' => 1]);
        $user1 = factory(\STS\User::class)->create();
        $user2 = factory(\STS\User::class)->create();
        $user3 = factory(\STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user1->id]);
        $conversation = factory(STS\Entities\Conversation::class)->create();

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
        $this->assertTrue(count($conversation) == 1);
    }

    public function test_TripConversationCreate_Success()
    {
        /* Create a conversation */
        $user = factory(\STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user->id]);

        $conversation = $this->conversationManager->createTripConversation($trip->id);
        $this->assertTrue($conversation->type == STS\Entities\Conversation::TYPE_TRIP_CONVERSATION && $conversation->tripId = $trip->id);
    }

    public function test_TripConversationCreate_RepeatTrip_Fail()
    {
        /* Creating a conversation two times - ERROR */
        $user = factory(\STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user->id]);

        $conversation = $this->conversationManager->createTripConversation($trip->id);
        $conversation2 = $this->conversationManager->createTripConversation($trip->id);
        $this->assertFalse($conversation->type == STS\Entities\Conversation::TYPE_TRIP_CONVERSATION && $conversation->tripId = $trip->id && $conversation2 == null);
    }

    public function test_Match_Success()
    {
        $c = factory(STS\Entities\Conversation::class)->create();

        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();

        $this->conversationRepository->addUser($c, $u1);
        $this->conversationRepository->addUser($c, $u2);

        $c2 = $this->conversationRepository->matchUser($u1, $u2);

        $this->assertTrue($c->id == $c2->id);
    }

    public function test_Match_Fail()
    {
        $c = factory(STS\Entities\Conversation::class)->create();
        $c2 = factory(STS\Entities\Conversation::class)->create();

        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $u3 = factory(STS\User::class)->create();

        $this->conversationRepository->addUser($c, $u1);
        $this->conversationRepository->addUser($c, $u2);

        $this->conversationRepository->addUser($c2, $u2);
        $this->conversationRepository->addUser($c2, $u3);

        $cc1 = $this->conversationRepository->matchUser($u1, $u2);
        $cc2 = $this->conversationRepository->matchUser($u2, $u3);

        $this->assertFalse($cc1->id == $cc2->id);
    }

    public function test_Send_Message()
    {
        $c = factory(STS\Entities\Conversation::class)->create();

        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $u3 = factory(STS\User::class)->create();

        $m1 = 'test 1';
        $m2 = 'test 2';
        $m3 = 'test 3';

        $this->conversationRepository->addUser($c, $u1);
        $this->conversationRepository->addUser($c, $u2);
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
        $u1 = factory(STS\User::class)->create();

        $c = factory(STS\Entities\Conversation::class, 20)->create();

        for ($i = 0; $i < 20; $i++) {
            $this->conversationRepository->addUser($c[$i], $u1);
        }

        $userConversations = $this->conversationManager->getUserConversations($u1);
        $userConversations = json_decode(json_encode($userConversations));
        $this->assertTrue($userConversations->total == 20);
    }

    public function test_Get_Conversation()
    {
        $u = factory(STS\User::class)->create();
        $c = factory(STS\Entities\Conversation::class)->create();
        $this->conversationRepository->addUser($c, $u);

        $this->conversation = $this->conversationManager->getConversation($u, $c->id);
        $this->assertTrue($this->conversation->id == $c->id);

        //invalid user - never have'benn tested'
    }

    public function test_conversation_entity_unread()
    {
        $users = factory(STS\User::class, 4)->create();
        $c = factory(STS\Entities\Conversation::class)->create();

        for ($i = 0; $i < 4; $i++) {
            $this->conversationRepository->addUser($c, $users[$i]);
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
        $u = factory(STS\User::class)->create();
        $c1 = factory(STS\Entities\Conversation::class)->create();
        $c2 = factory(STS\Entities\Conversation::class)->create();
        $c3 = factory(STS\Entities\Conversation::class)->create();

        $c1->updated_at = Carbon::create(1999, 1, 1, 0, 0, 0);
        $c2->updated_at = Carbon::create(2000, 1, 1, 0, 0, 0);
        $c3->updated_at = Carbon::create(2001, 1, 1, 0, 0, 0);
        $c1->save(['timestamps' => false]);
        $c2->save(['timestamps' => false]);
        $c3->save(['timestamps' => false]);

        $this->conversationRepository->addUser($c1, $u);
        $this->conversationRepository->addUser($c2, $u);
        $this->conversationRepository->addUser($c3, $u);

        $userConversations = $this->conversationManager->getUserConversations($u);
        $this->assertTrue($userConversations[0]->id == $c3->id &&
        $userConversations[1]->id == $c2->id &&
        $userConversations[2]->id == $c1->id);

        $this->conversationManager->send($u, $c2->id, 'test');
        $userConversations = $this->conversationManager->getUserConversations($u);

        $this->assertTrue($userConversations[0]->id == $c2->id &&
        $userConversations[1]->id == $c3->id &&
        $userConversations[2]->id == $c1->id);
    }

    public function test_get_all_messages_from_conversation()
    {
        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $c = factory(STS\Entities\Conversation::class)->create();
        $this->conversationRepository->addUser($c, $u1);
        $this->conversationRepository->addUser($c, $u2);
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
        $u = factory(\STS\User::class)->create();
        $t = factory(STS\Entities\Trip::class)->create(['user_id' => $u->id]);

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
        $u = factory(STS\User::class, 24)->create();
        $c = factory(STS\Entities\Conversation::class)->create();

        for ($i = 0; $i < 22; $i++) {
            $this->conversationRepository->addUser($c, $u[$i]);
        }
        $this->assertTrue(count($this->conversationManager->getUsersFromConversation($u[0], $c->id)) == 22);
    }

    public function test_get_conversation_trip()
    {
        $u = factory(\STS\User::class)->create();
        $t = factory(STS\Entities\Trip::class)->create(['user_id' => $u->id]);
        $c = factory(STS\Entities\Conversation::class)->create(['trip_id' => $t->id]);

        $this->assertTrue($t->conversation->id == $c->id);
    }

    public function test_create_conversation_listeners()
    {
        $u = factory(\STS\User::class)->create();
        $t = factory(STS\Entities\Trip::class)->create(['user_id' => $u->id]);

        $event = new STS\Events\Trip\Create($t);

        $listener = new STS\Listeners\Conversation\createConversation($this->conversationManager, $this->conversationRepository);

        $listener->handle($event);

        $this->assertNotNull($t->conversation);
    }

    public function test_add_remove_user_conversation_trip()
    {
        $u = factory(\STS\User::class)->create();
        $accepted = factory(\STS\User::class)->create();
        $t = factory(STS\Entities\Trip::class)->create(['user_id' => $u->id]);
        $c = factory(STS\Entities\Conversation::class)->create(['trip_id' => $t->id]);

        $event = new STS\Events\Passenger\Accept($t, $u, $accepted);

        $listener = new STS\Listeners\Conversation\addUserConversation($this->conversationRepository);

        $listener->handle($event);

        $this->assertTrue($c->users()->count() == 1);

        $event = new STS\Events\Passenger\Cancel($t, $u, $accepted, 0);

        $listener = new STS\Listeners\Conversation\removeUserConversation($this->conversationRepository);

        $listener->handle($event);

        $this->assertTrue($c->users()->count() == 0);
    }

    public function test_user_list()
    {
        $driver = factory(User::class)->create();
        $passengerA = factory(User::class)->create();
        $passengerB = factory(User::class)->create();

        $trip = factory(Trip::class)->create(['user_id' => $driver->id]);

        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengerA->id, 'trip_id' => $trip->id]);
        factory(Passenger::class, 'aceptado')->create(['user_id' => $passengerB->id, 'trip_id' => $trip->id]);

        $users = $this->conversationRepository->userList($driver);
        $this->assertTrue($users->count() == 2);

        $users = $this->conversationRepository->userList($passengerA);
        $this->assertTrue($users->count() == 1);
    }
}
