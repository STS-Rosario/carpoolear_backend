<?php

namespace STS\Services;

use STS\Models\AdminActionLog;
use STS\Models\User;

class AdminActionLogger
{
    public static function log(User $admin, string $action, int $targetUserId, array $details): AdminActionLog
    {
        return AdminActionLog::create([
            'admin_user_id' => $admin->id,
            'action' => $action,
            'target_user_id' => $targetUserId,
            'details' => $details,
        ]);
    }
}
