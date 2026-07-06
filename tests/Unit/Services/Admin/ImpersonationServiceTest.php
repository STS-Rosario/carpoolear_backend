<?php

namespace Tests\Unit\Services\Admin;

use Carbon\Carbon;
use STS\Models\AdminImpersonationSession;
use STS\Models\User;
use STS\Services\Admin\ImpersonationService;
use Tests\TestCase;

class ImpersonationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_start_creates_session_with_sixty_minute_expiry_and_handoff_token(): void
    {
        Carbon::setTestNow('2026-07-05 12:00:00');

        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['active' => true, 'banned' => false]);

        $service = app(ImpersonationService::class);
        $result = $service->start($admin, $target);

        $this->assertArrayHasKey('session', $result);
        $this->assertArrayHasKey('handoff_token', $result);
        $this->assertSame(64, strlen($result['handoff_token']));

        /** @var AdminImpersonationSession $session */
        $session = $result['session'];
        $this->assertSame($admin->id, $session->admin_user_id);
        $this->assertSame($target->id, $session->target_user_id);
        $this->assertTrue($session->expires_at->equalTo(now()->addMinutes(60)));
    }

    public function test_start_stores_only_sha256_hash_of_handoff_token(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['active' => true, 'banned' => false]);

        $service = app(ImpersonationService::class);
        $result = $service->start($admin, $target);

        /** @var AdminImpersonationSession $session */
        $session = $result['session']->fresh();
        $this->assertSame(hash('sha256', $result['handoff_token']), $session->token_hash);
        $this->assertNotSame($result['handoff_token'], $session->token_hash);
    }
}
