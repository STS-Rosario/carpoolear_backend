<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\User;
use Tests\TestCase;

class AdminImpersonationTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_admin_can_start_impersonation_for_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/users/'.$target->id.'/impersonate');

        $response->assertCreated();
        $response->assertJsonStructure([
            'handoff_token',
            'session_id',
            'expires_at',
            'target_user_id',
        ]);
        $this->assertSame($target->id, $response->json('target_user_id'));
        $this->assertSame(64, strlen($response->json('handoff_token')));
    }

    public function test_non_admin_cannot_start_impersonation(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create(['active' => true, 'banned' => false]);

        $this->actingAs($user, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/impersonate')
            ->assertForbidden()
            ->assertJson(['message' => 'impersonation_forbidden']);
    }

    public function test_cannot_impersonate_admin_user(): void
    {
        $admin = $this->admin();
        $otherAdmin = User::factory()->create(['active' => true, 'banned' => false]);
        $otherAdmin->forceFill(['is_admin' => true])->saveQuietly();

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$otherAdmin->id.'/impersonate')
            ->assertStatus(422)
            ->assertJson(['message' => 'cannot_impersonate_admin']);
    }

    public function test_cannot_impersonate_banned_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['active' => true, 'banned' => true, 'is_admin' => false]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/impersonate')
            ->assertStatus(422)
            ->assertJson(['message' => 'cannot_impersonate_banned_user']);
    }

    public function test_cannot_impersonate_inactive_user(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['active' => false, 'banned' => false, 'is_admin' => false]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/impersonate')
            ->assertStatus(422)
            ->assertJson(['message' => 'cannot_impersonate_inactive_user']);
    }

    public function test_impersonation_disabled_returns_forbidden(): void
    {
        config(['carpoolear.impersonation_enabled' => false]);

        $admin = $this->admin();
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/users/'.$target->id.'/impersonate')
            ->assertForbidden()
            ->assertJson(['message' => 'impersonation_disabled']);
    }
}
