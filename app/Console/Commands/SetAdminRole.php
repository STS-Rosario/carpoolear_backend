<?php

namespace STS\Console\Commands;

use Illuminate\Console\Command;
use STS\Admin\AdminRole;
use STS\Models\User;

class SetAdminRole extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:role {user} {role}';

    /**
     * @var string
     */
    protected $description = 'Assign or clear an admin role (superadmin, helpdesk, none)';

    public function handle(): int
    {
        $user = User::query()->find($this->argument('user'));
        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $role = (string) $this->argument('role');
        if ($role === 'none') {
            $user->forceFill([
                'is_admin' => false,
                'admin_role' => null,
            ])->save();

            $this->info("Cleared admin role for user {$user->id}.");

            return self::SUCCESS;
        }

        if (AdminRole::tryFrom($role) === null) {
            $this->error('Unknown role.');

            return self::FAILURE;
        }

        $user->forceFill([
            'is_admin' => true,
            'admin_role' => $role,
        ])->save();

        $this->info("Assigned {$role} to user {$user->id}.");

        return self::SUCCESS;
    }
}
