<?php

namespace Tests\Unit\Models;

use STS\Admin\AdminPermission;
use STS\Models\User;
use Tests\TestCase;

class UserAdminPermissionTest extends TestCase
{
    private function staff(string $role): User
    {
        $user = User::factory()->make(['is_admin' => true]);
        $user->admin_role = $role;

        return $user;
    }

    public function test_non_admin_has_no_permissions_even_with_a_role_attribute(): void
    {
        $user = User::factory()->make(['is_admin' => false]);
        $user->admin_role = 'superadmin';

        $this->assertFalse($user->hasAdminPermission(AdminPermission::UsersEdit));
        $this->assertSame([], $user->adminPermissionValues());
    }

    public function test_helpdesk_can_edit_users_but_not_suspend_them(): void
    {
        $user = $this->staff('helpdesk');

        $this->assertTrue($user->hasAdminPermission(AdminPermission::UsersEdit));
        $this->assertTrue($user->hasAdminPermission(AdminPermission::UsersEdit->value));
        $this->assertFalse($user->hasAdminPermission(AdminPermission::UsersSuspend));
    }

    public function test_superadmin_has_every_permission(): void
    {
        $user = $this->staff('superadmin');

        foreach (AdminPermission::cases() as $permission) {
            $this->assertTrue($user->hasAdminPermission($permission), $permission->value);
        }
    }

    public function test_is_admin_without_role_is_treated_as_superadmin(): void
    {
        $user = User::factory()->make(['is_admin' => true]);
        $user->admin_role = null;

        $this->assertTrue($user->hasAdminPermission(AdminPermission::UsersSuspend));
        $this->assertTrue($user->hasAdminPermission(AdminPermission::PulseView));
    }

    public function test_unknown_permission_string_is_denied(): void
    {
        $user = $this->staff('superadmin');

        $this->assertFalse($user->hasAdminPermission('admin.does.not.exist'));
    }
}
