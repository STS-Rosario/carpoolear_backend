<?php

namespace Tests\Unit\Models;

use STS\Models\AdminActionLog;
use STS\Models\User;
use Tests\TestCase;

class AdminActionLogTest extends TestCase
{
    public function test_persists_admin_target_and_action(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $row = AdminActionLog::query()->create([
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_DELETE,
            'target_user_id' => $target->id,
            'details' => null,
        ]);

        $row = $row->fresh();
        $this->assertSame($admin->id, (int) $row->admin_user_id);
        $this->assertSame($target->id, (int) $row->target_user_id);
        $this->assertSame(AdminActionLog::ACTION_USER_DELETE, $row->action);
    }

    public function test_details_casts_to_array(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $payload = ['ip' => '127.0.0.1', 'note' => 'test audit'];

        $row = AdminActionLog::query()->create([
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_BAN_AND_ANONYMIZE,
            'target_user_id' => $target->id,
            'details' => $payload,
        ]);

        $row = $row->fresh();
        $this->assertSame($payload, $row->details);
        $this->assertIsArray($row->details);
    }

    public function test_allows_null_details(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();

        $row = AdminActionLog::query()->create([
            'admin_user_id' => $admin->id,
            'action' => AdminActionLog::ACTION_USER_ANONYMIZE,
            'target_user_id' => $target->id,
            'details' => null,
        ]);

        $this->assertNull($row->fresh()->details);
    }

    public function test_action_string_constants(): void
    {
        $this->assertSame('user_delete', AdminActionLog::ACTION_USER_DELETE);
        $this->assertSame('user_anonymize', AdminActionLog::ACTION_USER_ANONYMIZE);
        $this->assertSame('user_ban_and_anonymize', AdminActionLog::ACTION_USER_BAN_AND_ANONYMIZE);
    }

    public function test_table_name_is_admin_action_logs(): void
    {
        $this->assertSame('admin_action_logs', (new AdminActionLog)->getTable());
    }
}
