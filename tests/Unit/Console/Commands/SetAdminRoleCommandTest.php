<?php

namespace Tests\Unit\Console\Commands;

use STS\Console\Commands\SetAdminRole;
use STS\Models\User;
use Tests\TestCase;

class SetAdminRoleCommandTest extends TestCase
{
    public function test_command_contract_is_defined(): void
    {
        $command = new SetAdminRole;

        $this->assertSame('admin:role', $command->getName());
        $this->assertTrue($command->getDefinition()->hasArgument('user'));
        $this->assertTrue($command->getDefinition()->hasArgument('role'));
    }

    public function test_assigns_helpdesk_role_and_marks_user_as_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->artisan('admin:role', [
            'user' => $user->id,
            'role' => 'helpdesk',
        ])->assertExitCode(0);

        $fresh = $user->fresh();
        $this->assertTrue((bool) $fresh->is_admin);
        $this->assertSame('helpdesk', $fresh->admin_role);
    }

    public function test_assigns_superadmin_role(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->artisan('admin:role', [
            'user' => $user->id,
            'role' => 'superadmin',
        ])->assertExitCode(0);

        $fresh = $user->fresh();
        $this->assertTrue((bool) $fresh->is_admin);
        $this->assertSame('superadmin', $fresh->admin_role);
    }

    public function test_none_clears_admin_access(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $user->forceFill(['admin_role' => 'superadmin'])->saveQuietly();

        $this->artisan('admin:role', [
            'user' => $user->id,
            'role' => 'none',
        ])->assertExitCode(0);

        $fresh = $user->fresh();
        $this->assertFalse((bool) $fresh->is_admin);
        $this->assertNull($fresh->admin_role);
    }

    public function test_rejects_unknown_role(): void
    {
        $user = User::factory()->create();

        $this->artisan('admin:role', [
            'user' => $user->id,
            'role' => 'wizard',
        ])->assertExitCode(1);

        $this->assertFalse((bool) $user->fresh()->is_admin);
    }

    public function test_rejects_missing_user(): void
    {
        $this->artisan('admin:role', [
            'user' => 999999,
            'role' => 'helpdesk',
        ])->assertExitCode(1);
    }
}
