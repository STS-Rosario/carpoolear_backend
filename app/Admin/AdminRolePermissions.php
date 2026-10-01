<?php

namespace STS\Admin;

class AdminRolePermissions
{
    /**
     * @return list<AdminPermission>
     */
    public static function for(?AdminRole $role): array
    {
        return match ($role) {
            AdminRole::Superadmin => AdminPermission::cases(),
            AdminRole::Helpdesk => [
                AdminPermission::DashboardView,
                AdminPermission::GraphsView,
                AdminPermission::UsersSearch,
                AdminPermission::UsersEdit,
                AdminPermission::UsersImpersonate,
                AdminPermission::UsersMigrate,
                AdminPermission::UsersDeleteRequests,
                AdminPermission::IdentityManualReview,
                AdminPermission::IdentityMpReview,
                AdminPermission::TripsView,
                AdminPermission::SupportTickets,
                AdminPermission::AuditView,
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function valuesFor(?string $role): array
    {
        $enum = $role === null ? null : AdminRole::tryFrom($role);

        return array_map(
            fn (AdminPermission $permission) => $permission->value,
            self::for($enum)
        );
    }
}
