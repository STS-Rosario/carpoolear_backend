<?php

namespace Tests\Feature\Http;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ConversationApiTest extends TestCase
{
    use DatabaseTransactions;

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

    protected function parseJson($response)
    {
        return json_decode($response->getContent());
    }

    public function test_api_conversations_get()
    {
        $friends = \App::make(\STS\Services\Logic\FriendsManager::class);

        $user1 = \STS\Models\User::factory()->create();
        $user2 = \STS\Models\User::factory()->create(['is_admin' => true]);
        $user3 = \STS\Models\User::factory()->create();

        $friends->make($user1, $user3);

        $this->actingAs($user1, 'api');
        $response = $this->call('GET', 'api/conversations/');
        $json = json_decode($response->getContent());
        $this->assertTrue($json->meta->pagination->total == 0);

        $conversation = $this->conversationManager->findOrCreatePrivateConversation($user1, $user2);
        $this->assertTrue($conversation != null);
        $this->conversationManager->send($user1, $conversation->id, 'Hola');

        $conversation = $this->conversationManager->findOrCreatePrivateConversation($user3, $user1);
        $this->assertTrue($conversation != null);
        $this->conversationManager->send($user1, $conversation->id, 'Hola');

        $response = $this->call('GET', 'api/conversations/');
        $json = json_decode($response->getContent());
        $this->assertTrue($json->meta->pagination->total == 2);
    }

    public function test_api_conversations_post()
    {
        $user1 = \STS\Models\User::factory()->create();
        $user2 = \STS\Models\User::factory()->create();
        $user1->is_admin = true;

        $this->actingAs($user1, 'api');
        $response = $this->call('POST', 'api/conversations/', ['to' => $user2->id]);
        $this->assertTrue($response->status() == 200);

        $response = $this->call('POST', 'api/conversations/', ['to' => $user2->id]);

        $response = $this->call('POST', 'api/conversations/', ['to' => 999999]);
        $this->assertTrue($response->status() == 422);

        $response = $this->call('POST', 'api/conversations/');
        $this->assertTrue($response->status() == 422);
    }

    public function test_api_conversation_get()
    {
        $user1 = \STS\Models\User::factory()->create();
        $user2 = \STS\Models\User::factory()->create();
        $this->actingAs($user1, 'api');
        $c = \STS\Models\Conversation::factory()->create();

        $response = $this->call('GET', 'api/conversations/1');
        $this->assertTrue($response->status() == 422);

        $this->conversationRepository->addUser($c, $user1);
        $this->conversationRepository->addUser($c, $user2);
        $response = $this->call('GET', 'api/conversations/'.$c->id);
        $this->assertTrue($response->status() == 200);

        for ($i = 0; $i < 5; $i++) {
            $m = 'text'.$i;
            $this->conversationManager->send($user1, $c->id, $m);
        }

        $response = $this->call('GET', 'api/conversations/'.$c->id);

        $this->assertTrue($response->status() == 200);
        $json = json_decode($response->getContent());
        $this->assertTrue(count($json->data) == 5);

        for ($i = 0; $i < 3; $i++) {
            $m = 'text'.$i;
            $this->conversationManager->send($user2, $c->id, $m);
        }
        $response = $this->call('GET', 'api/conversations/'.$c->id, ['unread' => true, 'read' => true]);

        $this->assertTrue($response->status() == 200);
        $json = json_decode($response->getContent());
        $this->assertTrue(count($json->data) == 3);

        $response = $this->call('GET', 'api/conversations/'.$c->id, ['unread' => true, 'read' => true]);

        $this->assertTrue($response->status() == 200);
        $json = json_decode($response->getContent());
        $this->assertTrue(count($json->data) == 0);
    }

    public function test_users()
    {
        $u = \STS\Models\User::factory()->count(3)->create();
        $this->actingAs($u[0], 'api');
        $c = \STS\Models\Conversation::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $this->conversationRepository->addUser($c, $u[$i]);
        }
        $response = $this->call('GET', 'api/conversations/'.$c->id.'/users');
        $this->assertTrue($response->status() == 200);
        $this->assertTrue(count($response->original) == 3);
    }

    public function test_add_user_and_delete_user()
    {
        $u = \STS\Models\User::factory()->count(7)->create(['is_admin' => true]);
        $this->actingAs($u[0], 'api');
        $c = \STS\Models\Conversation::factory()->create();
        $this->conversationRepository->addUser($c, $u[0]);
        for ($i = 1; $i < 4; $i++) {
            $response = $this->call('POST', 'api/conversations/'.$c->id.'/users', ['users' => $u[$i]->id]);
            $this->assertTrue($response->status() == 200);
        }
        $response = $this->call('POST', 'api/conversations/'.$c->id.'/users', ['users' => [$u[4]->id, $u[5]->id, $u[6]->id]]);
        $this->assertTrue($response->status() == 200);

        $response = $this->call('POST', 'api/conversations/'.$c->id.'/users', ['users' => 999999]);
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

    public function test_api_conversations_user_list()
    {
        $friends = \App::make(\STS\Services\Logic\FriendsManager::class);

        $user1 = \STS\Models\User::factory()->create();
        $user2 = \STS\Models\User::factory()->create(['is_admin' => true]);
        $user3 = \STS\Models\User::factory()->create();

        $friends->make($user1, $user3);

        $this->actingAs($user1, 'api');
        $response = $this->call('GET', 'api/conversations/user-list');
        $this->assertTrue($response->status() == 200);
    }
}
