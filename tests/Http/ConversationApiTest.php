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

    public function testPaginator()
    {
        $conversationManager = $this->app->make('\STS\Contracts\Logic\Conversation');
        $user1 = factory(\STS\User::class)->create();
        $user2 = factory(\STS\User::class)->create();
        $user3 = factory(\STS\User::class)->create();
        $user1->is_admin = true;

        $conversation = $conversationManager->findOrCreatePrivateConversation($user1, $user2); 
        $this->assertTrue($conversation != null);

        $conversation = $conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertTrue($conversation != null);

        $this->actingAsApiUser($user1);
        $response = $this->call('GET', 'api/conversations/'); 
    }
}