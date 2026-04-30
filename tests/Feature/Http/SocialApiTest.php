<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use STS\Http\Controllers\Api\v1\SocialController;
use STS\Models\SocialAccount;
use STS\Models\User;
use STS\Services\Logic\DeviceManager;
use STS\Services\Logic\UsersManager;
use Tests\TestCase;

class SocialApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeTestAccessToken(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    public function test_social_update_and_friends_require_authentication(): void
    {
        $this->putJson('api/social/update/test', ['access_token' => '{}'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);

        $this->postJson('api/social/friends/test', ['access_token' => '{}'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_social_login_rejects_unknown_provider(): void
    {
        $this->postJson('api/social/login/not-a-real-provider-class-xyz', [
            'access_token' => '{}',
        ])
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'provider not supported']);
    }

    public function test_constructor_registers_expected_logged_middleware_scopes(): void
    {
        $controller = new SocialController(
            Mockery::mock(UsersManager::class),
            Mockery::mock(DeviceManager::class)
        );

        $middlewares = $controller->getMiddleware();
        $logged = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged';
        });
        $loggedOptional = collect($middlewares)->first(function ($entry) {
            return (is_array($entry) ? ($entry['middleware'] ?? null) : ($entry->middleware ?? null)) === 'logged.optional';
        });

        $this->assertNotNull($logged);
        $this->assertNotNull($loggedOptional);

        $loggedOptions = is_array($logged) ? ($logged['options'] ?? []) : ($logged->options ?? []);
        $optionalOptions = is_array($loggedOptional) ? ($loggedOptional['options'] ?? []) : ($loggedOptional->options ?? []);

        $this->assertSame(['login'], $loggedOptions['except'] ?? []);
        $this->assertSame(['login'], $optionalOptions['only'] ?? []);
    }

    public function test_social_login_returns_jwt_for_existing_linked_account(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
        ]);
        $pid = 'test-pid-'.substr(uniqid('', true), 0, 12);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'test',
        ]);

        $accessToken = $this->encodeTestAccessToken([
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $this->postJson('api/social/login/test', ['access_token' => $accessToken])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_social_endpoints_accept_mixed_case_provider_name(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'description' => 'Before mixed case',
        ]);
        $pid = 'mixed-pid-'.substr(uniqid('', true), 0, 12);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'test',
        ]);

        $accessToken = $this->encodeTestAccessToken([
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => $user->name,
            'description' => 'After mixed case',
            'friend_ids' => [],
        ]);
        $provider = 'TeSt';

        $this->postJson('api/social/login/'.$provider, ['access_token' => $accessToken])
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->actingAs($user, 'api');
        $update = $this->putJson('api/social/update/'.$provider, ['access_token' => $accessToken]);
        $update->assertOk();
        $this->assertSame('OK', $update->json());
        $this->assertSame('After mixed case', $user->fresh()->description);

        $friends = $this->postJson('api/social/friends/'.$provider, ['access_token' => $accessToken]);
        $friends->assertOk();
        $this->assertSame('OK', $friends->json());
    }

    public function test_social_login_rejects_banned_linked_account(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => true,
        ]);
        $pid = 'banned-pid-'.substr(uniqid('', true), 0, 12);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'test',
        ]);

        $accessToken = $this->encodeTestAccessToken([
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $this->postJson('api/social/login/test', ['access_token' => $accessToken])
            ->assertStatus(422)
            ->assertJsonPath('message', 'User banned.')
            ->assertJsonPath('errors.code', 'user_banned');
    }

    public function test_social_update_returns_ok_when_token_matches_linked_account(): void
    {
        $user = User::factory()->create([
            'active' => true,
            'banned' => false,
            'description' => 'Before',
        ]);
        $pid = 'upd-pid-'.substr(uniqid('', true), 0, 12);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'test',
        ]);

        $accessToken = $this->encodeTestAccessToken([
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => $user->name,
            'description' => 'After social sync',
        ]);

        $this->actingAs($user, 'api');

        $update = $this->putJson('api/social/update/test', ['access_token' => $accessToken]);
        $update->assertOk();
        $this->assertSame('OK', $update->json());

        $this->assertSame('After social sync', $user->fresh()->description);
    }

    public function test_social_friends_returns_ok_when_token_matches_linked_account(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $pid = 'fr-pid-'.substr(uniqid('', true), 0, 12);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider_user_id' => $pid,
            'provider' => 'test',
        ]);

        $accessToken = $this->encodeTestAccessToken([
            'provider_user_id' => $pid,
            'email' => $user->email,
            'name' => $user->name,
            'friend_ids' => [],
        ]);

        $this->actingAs($user, 'api');

        $friends = $this->postJson('api/social/friends/test', ['access_token' => $accessToken]);
        $friends->assertOk();
        $this->assertSame('OK', $friends->json());
    }
}
