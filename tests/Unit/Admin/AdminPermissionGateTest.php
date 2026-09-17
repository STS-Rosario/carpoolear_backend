<?php

namespace Tests\Unit\Admin;

use STS\Admin\AdminPermission;
use STS\Models\User;
use Tests\TestCase;

class AdminPermissionGateTest extends TestCase
{
    private function staff(string $role): User
    {
        $user = User::factory()->make(['is_admin' => true]);
        $user->admin_role = $role;

        return $user;
    }

    public function test_gates_are_registered_for_each_admin_permission(): void
    {
        $user = $this->staff('superadmin');

        foreach (AdminPermission::cases() as $permission) {
            $this->assertTrue($user->can($permission->value), $permission->value);
        }
    }

    public function test_helpdesk_gate_allows_user_edit_and_denies_suspend(): void
    {
        $user = $this->staff('helpdesk');

        $this->assertTrue($user->can(AdminPermission::UsersEdit->value));
        $this->assertFalse($user->can(AdminPermission::UsersSuspend->value));
    }

    public function test_view_pulse_gate_is_superadmin_only(): void
    {
        $this->assertTrue($this->staff('superadmin')->can('viewPulse'));
        $this->assertFalse($this->staff('helpdesk')->can('viewPulse'));
        $this->assertFalse(User::factory()->make(['is_admin' => false])->can('viewPulse'));
    }
}
