<?php
use Illuminate\Foundation\Testing\DatabaseTransactions;
use \STS\Contracts\Repository\Devices as DeviceRepository;

use Mockery as m;

class ConversationApiTest extends TestCase
{
    use DatabaseTransactions;
    
    protected $friendsLogic;
    public function __construct()
    {
    }
    
    public function setUp()
    {
        parent::setUp();
        /*$this->friendsLogic = $this->mock('STS\Contracts\Logic\Friends');*/
    }
    
    public function tearDown()
    {
        /*m::close();*/
    }
    
    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }
    
    public function test_api_conversations_get()
    {
        $conversationManager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $user1 = factory(\STS\User::class)->create();
        $user2 = factory(\STS\User::class)->create();
        $user3 = factory(\STS\User::class)->create();
        $user1->is_admin = true;
        
        $this->actingAsApiUser($user1);
        $response = transform($this->call('GET', 'api/conversations/'));
        $this->assertTrue($response->original->total == 0);
        
        $conversation = $conversationManager->findOrCreatePrivateConversation($user1, $user2);
        $this->assertTrue($conversation != null);
        
        $conversation = $conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertTrue($conversation != null);
        
        $response = transform($this->call('GET', 'api/conversations/'));
        
        $this->assertTrue($response->original->total == 2);
    }
    
    public function test_api_conversations_post()
    {
        $conversationManager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $user1 = factory(\STS\User::class)->create();
        $user2 = factory(\STS\User::class)->create();
        $user1->is_admin = true;
        
        $this->actingAsApiUser($user1);
        $response = $this->call('POST', 'api/conversations/', ['to' => $user2->id]);
        $this->assertTrue($response->status() == 200);
        
        $response = $this->call('POST', 'api/conversations/', ['to' => $user2->id]);
        
        $response = $this->call('POST', 'api/conversations/', ['to' => 1]);
        $this->assertTrue($response->status() == 400);
        
        $response = $this->call('POST', 'api/conversations/');
        $this->assertTrue($response->status() == 400);
    }
    
    public function test_api_conversation_get()
    {
        $user1 = factory(\STS\User::class)->create();
        $this->actingAsApiUser($user1);
        
        $response = $this->call('GET', 'api/conversations/1');
        console_log($response);
    }
}