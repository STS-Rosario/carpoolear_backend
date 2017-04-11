<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

class ConversationApiTest extends TestCase
{
    use DatabaseTransactions;

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

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function test_api_conversations_get()
    {
        $user1 = factory(\STS\User::class)->create();
        $user2 = factory(\STS\User::class)->create();
        $user3 = factory(\STS\User::class)->create();
        $user1->is_admin = true;

        $this->actingAsApiUser($user1);
        $response = transform($this->call('GET', 'api/conversations/'));
        $this->assertTrue($response->original->total == 0);

        $conversation = $this->conversationManager->findOrCreatePrivateConversation($user1, $user2);
        $this->assertTrue($conversation != null);

        $conversation = $this->conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertTrue($conversation != null);

        $response = transform($this->call('GET', 'api/conversations/'));

        $this->assertTrue($response->original->total == 2);
    }

    public function test_api_conversations_post()
    {
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
        $user2 = factory(\STS\User::class)->create();
        $this->actingAsApiUser($user1);
        $c = factory(STS\Entities\Conversation::class)->create();

        $response = $this->call('GET', 'api/conversations/1');
        $this->assertTrue($response->status() == 422);

        $this->conversationRepository->addUser($c, $user1);
        $this->conversationRepository->addUser($c, $user2);
        $response = $this->call('GET', 'api/conversations/'.$c->id);
        $this->assertTrue($response->status() == 200);

        for ($i = 0; $i < 27; $i++) {
            $m = 'text'.$i;
            $this->conversationManager->send($user1, $c->id, $m);
        }

        $response = $this->call('GET', 'api/conversations/'.$c->id);

        $this->assertTrue($response->status() == 200);
        $this->assertTrue(count($response->original) == 27);

        for ($i = 0; $i < 3; $i++) {
            $m = 'text'.$i;
            $this->conversationManager->send($user2, $c->id, $m);
        }
        $response = $this->call('GET', 'api/conversations/'.$c->id, ['unread' => true, 'read' => true]);

        $this->assertTrue($response->status() == 200);
        $this->assertTrue(count($response->original) == 3);

        $response = $this->call('GET', 'api/conversations/'.$c->id, ['unread' => true, 'read' => true]);

        $this->assertTrue($response->status() == 200);
        $this->assertTrue(count($response->original) == 0);
    }

    public function test_users()
    {
        $u = factory(\STS\User::class, 10)->create();
        $this->actingAsApiUser($u[0]);
        $c = factory(STS\Entities\Conversation::class)->create();
        for ($i = 0; $i < 10; $i++) {
            $this->conversationRepository->addUser($c, $u[$i]);
        }
        $response = $this->call('GET', 'api/conversations/'.$c->id.'/users');
        $this->assertTrue($response->status() == 200);
        $this->assertTrue(count($response->original) == 10);
    }

    public function test_add_user_and_delete_user()
    {
        $u = factory(\STS\User::class, 10)->create();
        $this->actingAsApiUser($u[0]);
        $c = factory(STS\Entities\Conversation::class)->create();
        $this->conversationRepository->addUser($c, $u[0]);
        for ($i = 1; $i < 4; $i++) {
            $response = $this->call('POST', 'api/conversations/'.$c->id.'/users', ['users' => $u[$i]->id]);
            $this->assertTrue($response->status() == 200);
        }
        $response = $this->call('POST', 'api/conversations/'.$c->id.'/users', ['users' => [$u[4]->id, $u[5]->id, $u[6]->id]]);
        $this->assertTrue($response->status() == 200);

        $response = $this->call('POST', 'api/conversations/'.$c->id.'/users', ['users' => 12]);
        $this->assertTrue($response->status() == 422);

        $response = $this->call('GET', 'api/conversations/'.$c->id.'/users');
        $this->assertTrue($response->status() == 200);
        $this->assertTrue(count($response->original) == 7);

        $response = $this->call('DELETE', 'api/conversations/'.$c->id.'/users/'.$u[2]->id);
        $response = $this->call('DELETE', 'api/conversations/'.$c->id.'/users/'.$u[4]->id);

        $response = $this->call('GET', 'api/conversations/'.$c->id.'/users');
        $this->assertTrue($response->status() == 200);
        $this->assertTrue(count($response->original) == 5);
    }
}
