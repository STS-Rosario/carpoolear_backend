<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActionLog extends Model
{
    const ACTION_USER_DELETE = 'user_delete';

    const ACTION_USER_ANONYMIZE = 'user_anonymize';

    const ACTION_USER_BAN_AND_ANONYMIZE = 'user_ban_and_anonymize';

    const ACTION_RATING_UPDATE = 'rating_update';

    const ACTION_REFERENCE_UPDATE = 'reference_update';

    const ACTION_USER_IMPERSONATE_START = 'user_impersonate_start';

    const ACTION_USER_IMPERSONATE_STOP = 'user_impersonate_stop';

    const ACTION_USER_UPDATE = 'user_update';

    const ACTION_IDENTITY_REVIEW = 'identity_review';

    const ACTION_ACCOUNT_DELETE_REQUEST_UPDATE = 'account_delete_request_update';

    const ACTION_SUPPORT_TICKET_UPDATE = 'support_ticket_update';

    const ACTION_MAINTENANCE_UPDATE = 'maintenance_update';

    const ACTION_USER_MIGRATE = 'user_migrate';

    protected $table = 'admin_action_logs';

    protected $fillable = [
        'admin_user_id',
        'action',
        'target_user_id',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function adminUser()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
