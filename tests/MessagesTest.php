<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\Paginator;

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

    public function test_addUserToConversation_Success()
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

    public function testMatchSuccess()
	  {
        $c = factory(STS\Entities\Conversation::class)->create();

        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();

        $this->conversationRepository->addUser($c, $u1);
        $this->conversationRepository->addUser($c, $u2);

        $c2 = $this->conversationRepository->matchUser($u1, $u2);

        $this->assertTrue($c->id == $c2->id);
	  }
 
    public function testMatchFail()
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

    public function testSendMessage()
	  {
        $c = factory(STS\Entities\Conversation::class)->create();

        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();

        $m1 = "test 1";
        $m2 = "test 2";
        $m3 = "test 3";

        $this->conversationRepository->addUser($c, $u1);
        $this->conversationManager->send($u1, $c->id, $m1);
        $this->conversationManager->send($u2, $c->id, $m2);
        $this->conversationManager->send($u1, $c->id, $m3);

        $messages = $this->messageRepository->getMessages($c, 1);
        $messagesDecode = json_decode(json_encode($messages));
        $this->assertTrue($messagesDecode->total == 3 && $messagesDecode->data[0]->user_id == $u1->id && $messagesDecode->data[0]->text == $m1 && $messagesDecode->data[0]->conversation_id == $c->id  );
        $this->assertTrue($messagesDecode->data[1]->user_id == $u2->id && $messagesDecode->data[1]->text == $m2 && $messagesDecode->data[1]->conversation_id == $c->id  );
        $this->assertTrue($messagesDecode->data[2]->user_id == $u1->id && $messagesDecode->data[2]->text == $m3 && $messagesDecode->data[2]->conversation_id == $c->id  );
      }

      public function testGetUserConversations()
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

      public function testGetConversation()
      {
          $u = factory(STS\User::class)->create();
          $c = factory(STS\Entities\Conversation::class)->create();

            for ($i = 0; $i <20; $i++) {
                $m = 'text' - $i;
                $this->conversationManager->send($u, $c->id, $m);
            }

            $this->conversationManager->getConversation($u);

            //invalid user
      }
}
