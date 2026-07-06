<?php

namespace Tests\Unit\Models;

use Carbon\Carbon;
use STS\Models\AdminImpersonationSession;
use STS\Models\User;
use Tests\TestCase;

class AdminImpersonationSessionTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_is_active_when_not_expired_consumed_or_ended(): void
    {
        Carbon::setTestNow('2026-07-05 12:00:00');

        $admin = User::factory()->create();
        $target = User::factory()->create();

        $session = AdminImpersonationSession::query()->create([
            'admin_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'token_hash' => hash('sha256', 'active-token'),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertTrue($session->isActive());
    }

    public function test_is_not_active_when_expired(): void
    {
        Carbon::setTestNow('2026-07-05 12:00:00');

        $admin = User::factory()->create();
        $target = User::factory()->create();

        $session = AdminImpersonationSession::query()->create([
            'admin_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'token_hash' => hash('sha256', 'expired-token'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse($session->isActive());
    }

    public function test_is_not_active_when_consumed(): void
    {
        Carbon::setTestNow('2026-07-05 12:00:00');

        $admin = User::factory()->create();
        $target = User::factory()->create();

        $session = AdminImpersonationSession::query()->create([
            'admin_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'token_hash' => hash('sha256', 'consumed-token'),
            'expires_at' => now()->addHour(),
            'consumed_at' => now(),
        ]);

        $this->assertFalse($session->isActive());
    }

    public function test_is_not_active_when_ended(): void
    {
        Carbon::setTestNow('2026-07-05 12:00:00');

        $admin = User::factory()->create();
        $target = User::factory()->create();

        $session = AdminImpersonationSession::query()->create([
            'admin_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'token_hash' => hash('sha256', 'ended-token'),
            'expires_at' => now()->addHour(),
            'ended_at' => now(),
        ]);

        $this->assertFalse($session->isActive());
    }
}
