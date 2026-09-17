<?php

namespace Tests\Unit\Admin;

use STS\Admin\AdminPermission;
use STS\Admin\AdminRole;
use STS\Admin\AdminRolePermissions;
use Tests\TestCase;

class AdminRolePermissionsTest extends TestCase
{
    public function test_superadmin_role_value_is_superadmin(): void
    {
        $this->assertSame('superadmin', AdminRole::Superadmin->value);
    }

    public function test_helpdesk_role_value_is_helpdesk(): void
    {
        $this->assertSame('helpdesk', AdminRole::Helpdesk->value);
    }

    public function test_superadmin_receives_every_permission(): void
    {
        $granted = AdminRolePermissions::for(AdminRole::Superadmin);

        $this->assertSame(AdminPermission::cases(), $granted);
    }

    public function test_unknown_role_receives_no_permissions(): void
    {
        $this->assertSame([], AdminRolePermissions::for(null));
    }

    public function test_helpdesk_allowlist_matches_mesa_de_ayuda_contract(): void
    {
        $granted = AdminRolePermissions::for(AdminRole::Helpdesk);
        $values = array_map(fn (AdminPermission $permission) => $permission->value, $granted);

        $this->assertEqualsCanonicalizing([
            AdminPermission::DashboardView->value,
            AdminPermission::GraphsView->value,
            AdminPermission::UsersSearch->value,
            AdminPermission::UsersEdit->value,
            AdminPermission::UsersImpersonate->value,
            AdminPermission::UsersMigrate->value,
            AdminPermission::UsersDeleteRequests->value,
            AdminPermission::IdentityManualReview->value,
            AdminPermission::IdentityMpReview->value,
            AdminPermission::TripsView->value,
            AdminPermission::SupportTickets->value,
            AdminPermission::AuditView->value,
        ], $values);
    }

    public function test_helpdesk_does_not_receive_destructive_or_unlisted_permissions(): void
    {
        $granted = AdminRolePermissions::for(AdminRole::Helpdesk);

        $this->assertNotContains(AdminPermission::UsersSuspend, $granted);
        $this->assertNotContains(AdminPermission::UsersSetActive, $granted);
        $this->assertNotContains(AdminPermission::UsersVerify, $granted);
        $this->assertNotContains(AdminPermission::UsersUnverify, $granted);
        $this->assertNotContains(AdminPermission::UsersDelete, $granted);
        $this->assertNotContains(AdminPermission::UsersAnonymize, $granted);
        $this->assertNotContains(AdminPermission::UsersBanAndAnonymize, $granted);
        $this->assertNotContains(AdminPermission::UsersDriverVerified, $granted);
        $this->assertNotContains(AdminPermission::UsersBannedList, $granted);
        $this->assertNotContains(AdminPermission::IdentityManualPurge, $granted);
        $this->assertNotContains(AdminPermission::IdentityStats, $granted);
        $this->assertNotContains(AdminPermission::TripsHide, $granted);
        $this->assertNotContains(AdminPermission::TripsExcessContribution, $granted);
        $this->assertNotContains(AdminPermission::RatingsEdit, $granted);
        $this->assertNotContains(AdminPermission::ReferencesEdit, $granted);
        $this->assertNotContains(AdminPermission::MaintenanceManage, $granted);
        $this->assertNotContains(AdminPermission::ChangelogsManage, $granted);
        $this->assertNotContains(AdminPermission::CarCatalog, $granted);
        $this->assertNotContains(AdminPermission::PulseView, $granted);
        $this->assertNotContains(AdminPermission::BadgesManage, $granted);
        $this->assertNotContains(AdminPermission::CampaignsManage, $granted);
    }

    public function test_values_for_returns_permission_strings_for_helpdesk(): void
    {
        $values = AdminRolePermissions::valuesFor(AdminRole::Helpdesk->value);

        $this->assertContains(AdminPermission::UsersEdit->value, $values);
        $this->assertNotContains(AdminPermission::UsersSuspend->value, $values);
    }

    public function test_values_for_unknown_role_is_empty(): void
    {
        $this->assertSame([], AdminRolePermissions::valuesFor(null));
        $this->assertSame([], AdminRolePermissions::valuesFor('not-a-role'));
    }
}
