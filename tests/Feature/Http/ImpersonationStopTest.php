<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\AdminImpersonationSession;
use STS\Models\User;
use STS\Services\Admin\ImpersonationService;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ImpersonationStopTest extends TestCase
{
    /**
     * @return array{token: string, session: AdminImpersonationSession}
     */
    private function impersonationToken(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);

        $service = app(ImpersonationService::class);
        $handoff = $service->start($admin, $target);
        $consumed = $service->consume($handoff['handoff_token']);

        return [
            'token' => $consumed['token'],
            'session' => $handoff['session']->fresh(),
        ];
    }

    public function test_impersonation_jwt_can_stop_session(): void
    {
        $result = $this->impersonationToken();

        $this->withHeader('Authorization', 'Bearer '.$result['token'])
            ->postJson('api/impersonate/stop')
            ->assertOk()
            ->assertJson(['message' => 'impersonation_stopped']);

        $this->assertNotNull($result['session']->fresh()->ended_at);
    }

    public function test_impersonation_jwt_is_invalidated_after_stop(): void
    {
        $result = $this->impersonationToken();

        $this->withHeader('Authorization', 'Bearer '.$result['token'])
            ->postJson('api/impersonate/stop')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$result['token'])
            ->postJson('api/retoken')
            ->assertForbidden();
    }

    public function test_normal_jwt_cannot_stop_impersonation(): void
    {
        $user = User::factory()->create(['active' => true, 'banned' => false]);
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('api/impersonate/stop')
            ->assertForbidden();
    }

    public function test_admin_can_stop_impersonation_session_by_id(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);

        $handoff = app(ImpersonationService::class)->start($admin, $target);
        $sessionId = $handoff['session']->id;

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $this->postJson('api/admin/impersonations/'.$sessionId.'/stop')
            ->assertOk()
            ->assertJson(['message' => 'impersonation_stopped']);

        $this->assertNotNull($handoff['session']->fresh()->ended_at);
    }
}
