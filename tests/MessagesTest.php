<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\Paginator;

class MessagesTest extends TestCase { 
    
    use DatabaseTransactions;
      
    protected $userManager;

    public function test_createPrivateConversation()
    {
        $conversationManager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $user1 = factory(\STS\User::class)->create();
        $user2 = factory(\STS\User::class)->create();
        $user3 = factory(\STS\User::class)->create();
        $user1->is_admin = true;

        // admin can chat with everybody
        $conversation = $conversationManager->findOrCreatePrivateConversation($user1, $user2); 
        $this->assertTrue($conversation != null);

        $conversation = $conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertTrue($conversation != null);
        
        // two users thar are not admin, and not friends, they cannot chat
        $conversationFail = $conversationManager->findOrCreatePrivateConversation($user2, $user3);
        $this->assertTrue($conversationFail == null);

        // if a conversation exist in database, it must not create a new conversation.
        $conversation2 = $conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertTrue($conversation2->id == $conversation->id);
    }

    public function test_addUserToConversation_Success()
    {
        $conversationManager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $user1 = factory(\STS\User::class)->create();
        $user2 = factory(\STS\User::class)->create();
        $user3 = factory(\STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user1->id]);
        $conversation = factory(STS\Entities\Conversation::class)->create();
        $mustBeTrue = false;

        $conversationManager->addUserToConversation($conversation->id, $user1);
        $conversationManager->addUserToConversation($conversation->id, $user2);
        $isUser1 = $conversation->users()->where('id', $user1->id)->count();
        $mustBeTrue = ($isUser1 == 1);

        $isUser2 = $conversation->users()->where('id', $user2->id)->count();
        $mustBeTrue = $mustBeTrue && ($isUser2 == 1);
        
        $isUser3 = $conversation->users()->where('id', $user3->id)->count();
        $mustBeTrue = $mustBeTrue && ($isUser3 == 0);

        $this->assertTrue($mustBeTrue);

    }

    public function test_TripConversationCreate_Success()
    {
        /* Create a conversation */
        $conversationManager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $user = factory(\STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user->id]);

        $conversation = $conversationManager->createTripConversation ( $trip->id );
        $this->assertTrue($conversation->type == STS\Entities\Conversation::TYPE_TRIP_CONVERSATION && $conversation->tripId = $trip->id);
    }

    public function test_TripConversationCreate_RepeatTrip_Fail()
    {
        /* Creating a conversation two times - ERROR */
        $conversationManager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $user = factory(\STS\User::class)->create();
        $trip = factory(STS\Entities\Trip::class)->create(['user_id' => $user->id]);

        $conversation = $conversationManager->createTripConversation ( $trip->id );
        $conversation2 = $conversationManager->createTripConversation ( $trip->id );
        $this->assertFalse($conversation->type == STS\Entities\Conversation::TYPE_TRIP_CONVERSATION && $conversation->tripId = $trip->id && $conversation2 == null);
    }

    public function test_TripConversationCreate_ValidateTripId_Fail()
    {
        /* Creating a conversation validation. If not a positive integer throw error */
        $conversationManager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $user = factory(\STS\User::class)->create();

        $conversation = $conversationManager->createTripConversation ( 'asdd' ) || $conversationManager->createTripConversation ( '-1' ) || $conversationManager->createTripConversation ( '' ) || $conversationManager->createTripConversation ( null ) || $conversationManager->createTripConversation ( false ) || $conversationManager->createTripConversation ( true );
        $this->assertFalse($conversation);
    }

    public function testMatchSuccess()
	  {
        $repo = $this->app->make('\STS\Contracts\Repository\Conversations');
        $c = factory(STS\Entities\Conversation::class)->create();

        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();

        $repo->addUser($c, $u1);
        $repo->addUser($c, $u2);

        $c2 = $repo->matchUser($u1, $u2);

        $this->assertTrue($c->id == $c2->id);
	  }
 
    public function testMatchFail()
	  {
        $repo = $this->app->make('\STS\Contracts\Repository\Conversations');
        $c = factory(STS\Entities\Conversation::class)->create();
        $c2 = factory(STS\Entities\Conversation::class)->create();

        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();
        $u3 = factory(STS\User::class)->create();

        $repo->addUser($c, $u1);
        $repo->addUser($c, $u2);

        $repo->addUser($c2, $u2);
        $repo->addUser($c2, $u3);

        $cc1 = $repo->matchUser($u1, $u2);
        $cc2 = $repo->matchUser($u2, $u3);

        $this->assertFalse($cc1->id == $cc2->id);
	  }

      public function testMessages()
      {
        $u3 = factory(STS\User::class, 15)->create();
        Paginator::currentPageResolver(function () {
            return 2;
        });
        $user = STS\User::paginate(5);
        $user->setPath('conversation/12/messages');
      }

    public function testSendMessage()
	  {
        $repoM = $this->app->make('\STS\Contracts\Repository\Messages');
        $repo = $this->app->make('\STS\Contracts\Repository\Conversations');
        $manager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $c = factory(STS\Entities\Conversation::class)->create();

        $u1 = factory(STS\User::class)->create();
        $u2 = factory(STS\User::class)->create();

        $repo->addUser($c, $u1);
        $manager->send($u1, $c->id, "test 1");
        $manager->send($u2, $c->id, "test 2");

        $messages = $repoM->getMessages($c, 1, 1);
        echo('HOLA!!:');
        var_dump($messages);
	  }
}
