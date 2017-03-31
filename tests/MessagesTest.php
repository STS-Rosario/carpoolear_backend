<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\Paginator;
use Carbon\Carbon;

class MessagesTest extends TestCase {
    
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
        $user1 = factory(\STS\User::class)->create();
        $user2 = factory(\STS\User::class)->create();
        $user3 = factory(\STS\User::class)->create();
        $user1->is_admin = true;
        
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
        $user1 = factory(\STS\User::class)->create();
        $user2 = factory(\STS\User::class)->create();
        $user3 = factory(\STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user1->id]);
        $conversation = factory(STS\Entities\Conversation::class)->create();
        
        $this->conversationManager->addUserToConversation($conversation->id, $user1);
        $this->conversationManager->addUserToConversation($conversation->id, $user2);
        
        $isUser1 = $conversation->users()->where('id', $user1->id)->count();
        $this->assertTrue($isUser1 == 1);
        
        $isUser2 = $conversation->users()->where('id', $user2->id)->count();
        $this->assertTrue($isUser2 == 1);
        $isUser3 = $conversation->users()->where('id', $user3->id)->count();
        $this->assertTrue($isUser3 == 0);
        
        $this->conversationManager->removeUsertFromConversation($conversation->id, $user1);
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
        
        $conversation = $this->conversationManager->createTripConversation ( $trip->id );
        $this->assertTrue($conversation->type == STS\Entities\Conversation::TYPE_TRIP_CONVERSATION && $conversation->tripId = $trip->id);
    }
    
    public function test_TripConversationCreate_RepeatTrip_Fail()
    {
        /* Creating a conversation two times - ERROR */
        $user = factory(\STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user->id]);
        
        $conversation = $this->conversationManager->createTripConversation ( $trip->id );
        $conversation2 = $this->conversationManager->createTripConversation ( $trip->id );
        $this->assertFalse($conversation->type == STS\Entities\Conversation::TYPE_TRIP_CONVERSATION && $conversation->tripId = $trip->id && $conversation2 == null);
    }
    
    public function test_TripConversationCreate_ValidateTripId_Fail()
    {
        /* Creating a conversation validation. If not a positive integer throw error */
        $user = factory(\STS\User::class)->create();
        
        $conversation = $this->conversationManager->createTripConversation ( 'asdd' ) || $this->conversationManager->createTripConversation ( '-1' ) || $this->conversationManager->createTripConversation ( '' ) || $this->conversationManager->createTripConversation ( null ) || $this->conversationManager->createTripConversation ( false ) || $this->conversationManager->createTripConversation ( true );
        $this->assertFalse($conversation);
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
        
        $m1 = "test 1";
        $m2 = "test 2";
        $m3 = "test 3";
        
        $this->conversationRepository->addUser($c, $u1);
        $this->conversationRepository->addUser($c, $u2);
        $this->conversationManager->send($u1, $c->id, $m1);
        $this->conversationManager->send($u2, $c->id, $m2);
        $this->conversationManager->send($u1, $c->id, $m3);
        $this->conversationManager->send($u3, $c->id, $m3);
        
        $messages = $this->messageRepository->getMessages($c, 1, 20);
        $messagesDecode = json_decode(json_encode($messages));
        $this->assertTrue($messagesDecode->total == 3 && $messagesDecode->data[0]->user_id == $u1->id && $messagesDecode->data[0]->text == $m1 && $messagesDecode->data[0]->conversation_id == $c->id  );
        $this->assertTrue($messagesDecode->data[1]->user_id == $u2->id && $messagesDecode->data[1]->text == $m2 && $messagesDecode->data[1]->conversation_id == $c->id  );
        $this->assertTrue($messagesDecode->data[2]->user_id == $u1->id && $messagesDecode->data[2]->text == $m3 && $messagesDecode->data[2]->conversation_id == $c->id  );
    }
    
    public function test_Get_User_Conversations()
    {
        $u1 = factory(STS\User::class)->create();
        
        $c = factory(STS\Entities\Conversation::class, 20)->create();
        
        for ($i = 0; $i <20; $i++) {
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
        
        for ( $i = 0;  $i < 4;  $i++) {
            $this->conversationRepository->addUser($c, $users[$i]);
        }
        $this->conversationManager->send($users[1], $c->id, "test1");
        $this->conversationManager->send($users[1], $c->id, "test2");
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
        $c1->save(['timestamps' => FALSE]);
        $c2->save(['timestamps' => FALSE]);
        $c3->save(['timestamps' => FALSE]);
        
        $this->conversationManager->addUserToConversation($c1->id, $u);
        $this->conversationManager->addUserToConversation($c2->id, $u);
        $this->conversationManager->addUserToConversation($c3->id, $u);
        
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
    
    public function test_get_all_messages_from_conversation ()
    {
        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $c = factory(STS\Entities\Conversation::class)->create();
        $this->conversationManager->addUserToConversation($c->id, $u1);
        $this->conversationManager->addUserToConversation($c->id, $u2);
        for ($i = 0; $i <27; $i++) {
            $m = 'text' . $i;
            $this->conversationManager->send($u1, $c->id, $m);
        }
        $this->conversationManager->send($u2, $c->id, 'new');
        
        $messages = $this->conversationManager->getAllMessagesFromConversation($c->id, $u2, false);
        $messages = json_decode(json_encode($messages));
        $this->assertTrue($messages->total == 28);
        
        $this->conversationManager->send($u1, $c->id, 'new');
        
        $messages = $this->conversationManager->getUnreadMessagesFromConversation($c->id, $u2, false);
        $messages = json_decode(json_encode($messages));
        $this->assertTrue(count($messages) == 28);
        
        $this->conversationManager->send($u1, $c->id, 'new');
        $this->conversationManager->send($u2, $c->id, 'new');
        
        $messages = $this->conversationManager->getUnreadMessagesFromConversation($c->id, $u2, true);
        $messages = json_decode(json_encode($messages));
        $this->assertTrue(count($messages) == 29);
        
        $this->conversationManager->send($u1, $c->id, 'new');
        
        $messages = $this->conversationManager->getUnreadMessagesFromConversation($c->id, $u2, false);
        $messages = json_decode(json_encode($messages));
        $this->assertTrue(count($messages) == 1);
        
    }
    
    public function test_getConversationByTrip_and_delete_Success()
    {
        $u = factory(\STS\User::class)->create();
        $t = factory(STS\Entities\Trip::class)->create(['user_id' => $u->id]);
        
        $c = $this->conversationManager->createTripConversation ( $t->id );
        
        $conversation = $this->conversationManager->getConversationByTrip($u, $t->id);
        
        $this->assertTrue($conversation->id == $c->id);
        
        $this->conversationManager->delete ( $c->id);
        
        $conversation = $this->conversationManager->getConversation($u, $c->id);
        
        $this->assertTrue($conversation == null);
        
        $conversationResult = $this->conversationManager->delete ( $c->id);
        
        $this->assertTrue($conversationResult == null);
    }
    
}