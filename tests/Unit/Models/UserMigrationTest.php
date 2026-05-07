<?php

namespace Tests\Unit\Models;

use STS\Models\User;
use STS\Models\UserMigration;
use Tests\TestCase;

class UserMigrationTest extends TestCase
{
    public function test_persists_admin_kept_and_removed_user_ids(): void
    {
        $admin = User::factory()->create();
        $kept = User::factory()->create();
        $removed = User::factory()->create();

        $row = UserMigration::query()->create([
            'admin_user_id' => $admin->id,
            'user_id_kept' => $kept->id,
            'user_id_removed' => $removed->id,
        ]);

        $row = $row->fresh();
        $this->assertSame($admin->id, (int) $row->admin_user_id);
        $this->assertSame($kept->id, (int) $row->user_id_kept);
        $this->assertSame($removed->id, (int) $row->user_id_removed);
    }

    public function test_admin_relation_loads_admin_user(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Person']);
        $kept = User::factory()->create();
        $removed = User::factory()->create();

        $row = UserMigration::query()->create([
            'admin_user_id' => $admin->id,
            'user_id_kept' => $kept->id,
            'user_id_removed' => $removed->id,
        ]);

        $row->load('admin');
        $this->assertNotNull($row->admin);
        $this->assertSame('Admin Person', $row->admin->name);
    }

    public function test_table_name_is_user_migrations(): void
    {
        $this->assertSame('user_migrations', (new UserMigration)->getTable());
    }
}
