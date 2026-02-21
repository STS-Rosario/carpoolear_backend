<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActionLog extends Model
{
    const ACTION_USER_DELETE = 'user_delete';
    const ACTION_USER_ANONYMIZE = 'user_anonymize';
    const ACTION_USER_BAN_AND_ANONYMIZE = 'user_ban_and_anonymize';

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
}
