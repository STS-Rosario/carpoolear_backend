<?php

namespace Tests\Feature\Http;

use Carbon\Carbon;
use STS\Models\AdminImpersonationSession;
use STS\Models\User;
use STS\Services\Admin\ImpersonationService;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ImpersonationConsumeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{session: AdminImpersonationSession, handoff_token: string}
     */
    private function createHandoff(): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);

        return app(ImpersonationService::class)->start($admin, $target);
    }

    public function test_consume_handoff_returns_jwt_with_impersonation_metadata(): void
    {
        $result = $this->createHandoff();
        $target = $result['session']->targetUser;

        $response = $this->postJson('api/auth/impersonate/consume', [
            'token' => $result['handoff_token'],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'config',
            'impersonation' => ['session_id', 'actor_id', 'target_user_id', 'expires_at'],
        ]);
        $this->assertSame($target->id, $response->json('impersonation.target_user_id'));

        $payload = JWTAuth::setToken($response->json('token'))->getPayload();
        $this->assertTrue($payload->get('imp'));
        $this->assertSame($result['session']->admin_user_id, $payload->get('actor_id'));
        $this->assertSame($result['session']->id, $payload->get('session_id'));
    }

    public function test_expired_handoff_token_cannot_be_consumed(): void
    {
        Carbon::setTestNow('2026-07-05 12:00:00');
        $result = $this->createHandoff();

        Carbon::setTestNow('2026-07-05 14:00:00');

        $this->postJson('api/auth/impersonate/consume', [
            'token' => $result['handoff_token'],
        ])->assertUnauthorized();
    }

    public function test_handoff_token_cannot_be_consumed_twice(): void
    {
        $result = $this->createHandoff();

        $this->postJson('api/auth/impersonate/consume', [
            'token' => $result['handoff_token'],
        ])->assertOk();

        $this->postJson('api/auth/impersonate/consume', [
            'token' => $result['handoff_token'],
        ])->assertUnauthorized();
    }

    public function test_stopped_session_handoff_cannot_be_consumed(): void
    {
        $result = $this->createHandoff();
        $result['session']->forceFill(['ended_at' => now()])->save();

        $this->postJson('api/auth/impersonate/consume', [
            'token' => $result['handoff_token'],
        ])->assertUnauthorized();
    }
}
