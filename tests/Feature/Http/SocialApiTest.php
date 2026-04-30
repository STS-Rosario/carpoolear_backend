<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use STS\Models\SocialAccount;
use STS\Models\User;
use Tests\TestCase;

class SocialApiTest extends TestCase
{
    use DatabaseTransactions;

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
