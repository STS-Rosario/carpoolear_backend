<?php

namespace Tests\Feature\Http;

use Illuminate\Http\Request;
use Mockery;
use STS\Helpers\IdentityValidationHelper;
use STS\Http\Controllers\Api\v1\ConversationController;
use STS\Models\Conversation;
use STS\Models\Message;
use STS\Models\User;
use STS\Services\Logic\ConversationsManager;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    protected $conversationManager;

    protected $messageRepository;

    protected $conversationRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conversationManager = $this->app->make(\STS\Services\Logic\ConversationsManager::class);
        $this->messageRepository = $this->app->make(\STS\Repository\MessageRepository::class);
        $this->conversationRepository = $this->app->make(\STS\Repository\ConversationRepository::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_constructor_registers_logged_middleware(): void
    {
        $controller = new ConversationController(
            Mockery::mock(Request::class),
            Mockery::mock(ConversationsManager::class),
            Mockery::mock(UsersManager::class),
        );

        $logged = collect($controller->getMiddleware())->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });

        $this->assertNotNull($logged);
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
        $user1 = \STS\Models\User::factory()->create(['is_admin' => true, 'identity_validated' => true]);
        $user2 = \STS\Models\User::factory()->create();

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

    public function test_conversation_endpoints_require_authentication(): void
    {
        $unauthorized = ['message' => 'Unauthorized.'];

        $this->getJson('api/conversations')->assertUnauthorized()->assertJson($unauthorized);
        $this->postJson('api/conversations', ['to' => 1])->assertUnauthorized()->assertJson($unauthorized);
        $this->getJson('api/conversations/show/1')->assertUnauthorized()->assertJson($unauthorized);
        $this->getJson('api/conversations/1')->assertUnauthorized()->assertJson($unauthorized);
        $this->getJson('api/conversations/user-list')->assertUnauthorized()->assertJson($unauthorized);
        $this->getJson('api/conversations/unread')->assertUnauthorized()->assertJson($unauthorized);
        $this->postJson('api/conversations/1/send', ['message' => 'hi'])->assertUnauthorized()->assertJson($unauthorized);
        $this->postJson('api/conversations/multi-send', ['message' => 'x', 'users' => [1]])->assertUnauthorized()->assertJson($unauthorized);
    }

    public function test_index_respects_page_size_query_parameter(): void
    {
        $user = User::factory()->create();
        $peerA = User::factory()->create();
        $peerB = User::factory()->create();

        $convA = Conversation::factory()->create();
        $this->conversationRepository->addUser($convA, $user);
        $this->conversationRepository->addUser($convA, $peerA);
        $this->conversationManager->send($user, $convA->id, 'First thread');

        $convB = Conversation::factory()->create();
        $this->conversationRepository->addUser($convB, $user);
        $this->conversationRepository->addUser($convB, $peerB);
        $this->conversationManager->send($user, $convB->id, 'Second thread');

        $this->actingAs($user, 'api')
            ->getJson('api/conversations?page=1&page_size=1')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.current_page', 1);
    }

    public function test_show_returns_unprocessable_when_conversation_missing_or_inaccessible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('api/conversations/show/'.((int) (Conversation::query()->max('id') ?? 0) + 99_999))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bad request exceptions');
    }

    private function enableStrictNewUserIdentityEnforcement(): void
    {
        config([
            'carpoolear.identity_validation_enabled' => true,
            'carpoolear.identity_validation_optional' => false,
            'carpoolear.identity_validation_required_new_users' => true,
            'carpoolear.identity_validation_new_users_date' => '2000-01-01',
        ]);
    }

    public function test_create_returns_unprocessable_when_identity_required_and_user_not_validated(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $from = User::factory()->create([
            'is_admin' => false,
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        $to = User::factory()->create();

        $this->actingAs($from, 'api')
            ->postJson('api/conversations', ['to' => $to->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', IdentityValidationHelper::identityValidationRequiredMessage())
            ->assertJsonPath('errors.error.0', 'identity_validation_required');
    }

    public function test_send_returns_unprocessable_when_identity_required_and_user_not_validated(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $from = User::factory()->create([
            'is_admin' => false,
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        $other = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $this->conversationRepository->addUser($conversation, $from);
        $this->conversationRepository->addUser($conversation, $other);

        $this->actingAs($from, 'api')
            ->postJson('api/conversations/'.$conversation->id.'/send', ['message' => 'Hello'])
            ->assertUnprocessable()
            ->assertJsonPath('message', IdentityValidationHelper::identityValidationRequiredMessage());
    }

    public function test_multi_send_returns_unprocessable_for_admin_without_identity_when_enforcement_requires_it(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $admin = User::factory()->create([
            'is_admin' => true,
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        $recipient = User::factory()->create();

        $this->actingAs($admin, 'api')
            ->postJson('api/conversations/multi-send', [
                'message' => 'Broadcast',
                'users' => [$recipient->id],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', IdentityValidationHelper::identityValidationRequiredMessage());
    }

    public function test_multi_send_succeeds_when_identity_validation_allows_user(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $from = User::factory()->create(['identity_validated' => true]);
        $to = User::factory()->create();

        $this->actingAs($from, 'api')
            ->postJson('api/conversations/multi-send', [
                'message' => 'Hello everyone',
                'users' => [$to->id],
            ])
            ->assertOk()
            ->assertExactJson(['message' => true]);
    }

    public function test_user_list_accepts_optional_value_query_without_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('api/conversations/user-list')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->actingAs($user, 'api')
            ->getJson('api/conversations/user-list?value='.rawurlencode('any-search-text'))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_unread_messages_endpoint_accepts_conversation_id_and_timestamp_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('api/conversations/unread?conversation_id=1&timestamp=2020-01-01T00:00:00Z')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_index_uses_default_page_and_page_size_and_accepts_explicit_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->getJson('api/conversations')
            ->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 20)
            ->assertJsonPath('meta.pagination.current_page', 1);

        $this->actingAs($user, 'api')
            ->getJson('api/conversations?page=3')
            ->assertOk()
            ->assertJsonPath('meta.pagination.current_page', 3);
    }

    public function test_show_returns_ok_when_conversation_is_accessible(): void
    {
        $user = User::factory()->create();
        $peer = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $this->conversationRepository->addUser($conversation, $user);
        $this->conversationRepository->addUser($conversation, $peer);

        $this->actingAs($user, 'api')
            ->getJson('api/conversations/show/'.$conversation->id)
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_admin_without_validated_identity_can_still_open_conversation_when_enforcement_is_strict(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $admin = User::factory()->create([
            'is_admin' => true,
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        $peer = User::factory()->create();

        $this->actingAs($admin, 'api')
            ->postJson('api/conversations', ['to' => $peer->id])
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_admin_without_validated_identity_can_send_messages_when_enforcement_is_strict(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $admin = User::factory()->create([
            'is_admin' => true,
            'identity_validated' => false,
            'validate_by_date' => null,
        ]);
        $peer = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $this->conversationRepository->addUser($conversation, $admin);
        $this->conversationRepository->addUser($conversation, $peer);

        $this->actingAs($admin, 'api')
            ->postJson('api/conversations/'.$conversation->id.'/send', ['message' => 'Hello peer'])
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_identity_validated_user_can_send_messages_via_http_api(): void
    {
        $this->enableStrictNewUserIdentityEnforcement();

        $sender = User::factory()->create([
            'is_admin' => false,
            'identity_validated' => true,
        ]);
        $peer = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $this->conversationRepository->addUser($conversation, $sender);
        $this->conversationRepository->addUser($conversation, $peer);

        $this->actingAs($sender, 'api')
            ->postJson('api/conversations/'.$conversation->id.'/send', ['message' => 'Validated send'])
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_delete_user_returns_plain_ok_payload_on_success(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $this->conversationRepository->addUser($conversation, $owner);
        $this->conversationRepository->addUser($conversation, $member);

        $response = $this->actingAs($owner, 'api')
            ->deleteJson('api/conversations/'.$conversation->id.'/users/'.$member->id);

        $response->assertOk();
        $this->assertSame('OK', $response->json());
    }

    public function test_user_list_search_value_filters_peers_by_name(): void
    {
        $needle = 'RareSearchToken314';

        $viewer = User::factory()->create();
        $matchingPeer = User::factory()->create(['name' => 'Alice '.$needle]);
        $otherPeer = User::factory()->create(['name' => 'Bob Someone Else']);

        $convA = Conversation::factory()->create();
        $this->conversationRepository->addUser($convA, $viewer);
        $this->conversationRepository->addUser($convA, $matchingPeer);
        $this->conversationManager->send($viewer, $convA->id, 'seed thread a');

        $convB = Conversation::factory()->create();
        $this->conversationRepository->addUser($convB, $viewer);
        $this->conversationRepository->addUser($convB, $otherPeer);
        $this->conversationManager->send($viewer, $convB->id, 'seed thread b');

        $allPeers = $this->actingAs($viewer, 'api')
            ->getJson('api/conversations/user-list')
            ->assertOk()
            ->json('data');

        $this->assertGreaterThanOrEqual(2, count($allPeers));

        $filtered = $this->actingAs($viewer, 'api')
            ->getJson('api/conversations/user-list?value='.rawurlencode($needle))
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $filtered);
        $this->assertSame($matchingPeer->id, $filtered[0]['id']);
    }

    public function test_unread_messages_respects_timestamp_query_boundary(): void
    {
        $reader = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $this->conversationRepository->addUser($conversation, $reader);
        $this->conversationRepository->addUser($conversation, $sender);
        $reader->conversations()->updateExistingPivot($conversation->id, ['read' => false]);

        $this->conversationManager->send($sender, $conversation->id, 'Unread ping');

        Message::query()->where('conversation_id', $conversation->id)->update([
            'created_at' => '2020-06-15 12:00:00',
        ]);

        $included = $this->actingAs($reader, 'api')
            ->getJson('api/conversations/unread?timestamp=2020-06-01%2000:00:00')
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($included);

        $excluded = $this->actingAs($reader, 'api')
            ->getJson('api/conversations/unread?timestamp=2021-01-01%2000:00:00')
            ->assertOk()
            ->json('data');
        $this->assertSame([], $excluded);
    }

    public function test_unread_messages_with_conversation_id_marks_conversation_as_read_for_user(): void
    {
        $reader = User::factory()->create();
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $this->conversationRepository->addUser($conversation, $reader);
        $this->conversationRepository->addUser($conversation, $sender);
        $reader->conversations()->updateExistingPivot($conversation->id, ['read' => false]);

        $this->conversationManager->send($sender, $conversation->id, 'Ping');

        $this->actingAs($reader, 'api')
            ->getJson('api/conversations/unread?conversation_id='.$conversation->id)
            ->assertOk();

        $reader->load('conversations');
        $pivotRead = $reader->conversations->firstWhere('id', $conversation->id)?->pivot?->read;
        $this->assertNotNull($pivotRead);
        $this->assertTrue((bool) $pivotRead);
    }
}
