<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use STS\Models\User;
use STS\Services\Admin\ImpersonationService;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ImpersonationRetokenTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_retoken_preserves_impersonation_claims_while_session_active(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);

        $service = app(ImpersonationService::class);
        $handoff = $service->start($admin, $target);
        $consumed = $service->consume($handoff['handoff_token']);
        $token = $consumed['token'];

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('api/retoken');

        $response->assertOk();
        $newToken = $response->json('token');

        $payload = JWTAuth::setToken($newToken)->getPayload();
        $this->assertTrue($payload->get('imp'));
        $this->assertSame($admin->id, $payload->get('actor_id'));
        $this->assertSame($handoff['session']->id, $payload->get('session_id'));
    }

    public function test_retoken_fails_when_impersonation_session_ended(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);

        $service = app(ImpersonationService::class);
        $handoff = $service->start($admin, $target);
        $consumed = $service->consume($handoff['handoff_token']);
        $service->stopSession($handoff['session']->fresh(), $admin);

        $this->withHeader('Authorization', 'Bearer '.$consumed['token'])
            ->postJson('api/retoken')
            ->assertForbidden();
    }
}
