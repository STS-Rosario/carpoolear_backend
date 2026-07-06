<?php

namespace Tests\Feature\Http;

use STS\Http\Middleware\UserAdmin;
use STS\Models\AdminActionLog;
use STS\Models\User;
use Tests\TestCase;

class AdminImpersonationAuditTest extends TestCase
{
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->saveQuietly();

        return $user->fresh();
    }

    public function test_start_impersonation_writes_audit_log(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $response = $this->postJson('api/admin/users/'.$target->id.'/impersonate', [], [
            'User-Agent' => 'TestAgent/1.0',
        ]);

        $response->assertCreated();
        $sessionId = $response->json('session_id');

        $log = AdminActionLog::query()
            ->where('action', AdminActionLog::ACTION_USER_IMPERSONATE_START)
            ->where('target_user_id', $target->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_user_id);
        $this->assertSame($sessionId, $log->details['session_id']);
        $this->assertSame('TestAgent/1.0', $log->details['user_agent']);
    }

    public function test_stop_impersonation_writes_audit_log(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['active' => true, 'banned' => false, 'is_admin' => false]);

        $this->actingAs($admin, 'api');
        $this->withoutMiddleware(UserAdmin::class);

        $start = $this->postJson('api/admin/users/'.$target->id.'/impersonate')->assertCreated();
        $sessionId = $start->json('session_id');

        $this->postJson('api/admin/impersonations/'.$sessionId.'/stop')->assertOk();

        $log = AdminActionLog::query()
            ->where('action', AdminActionLog::ACTION_USER_IMPERSONATE_STOP)
            ->where('target_user_id', $target->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($sessionId, $log->details['session_id']);
    }
}
