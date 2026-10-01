<?php

namespace Tests\Unit\Services;

use STS\Models\AdminActionLog;
use STS\Models\User;
use STS\Services\AdminActionLogger;
use Tests\TestCase;

class AdminActionLoggerTest extends TestCase
{
    public function test_log_persists_nullable_target_user(): void
    {
        $admin = User::factory()->create();

        $row = AdminActionLogger::log(
            $admin,
            AdminActionLog::ACTION_USER_UPDATE,
            null,
            ['keys' => ['maintenance']]
        );

        $fresh = $row->fresh();
        $this->assertSame($admin->id, (int) $fresh->admin_user_id);
        $this->assertNull($fresh->target_user_id);
        $this->assertSame(AdminActionLog::ACTION_USER_UPDATE, $fresh->action);
        $this->assertSame(['keys' => ['maintenance']], $fresh->details);
    }
}
