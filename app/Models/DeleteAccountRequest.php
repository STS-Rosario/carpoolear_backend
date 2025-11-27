<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;

class DeleteAccountRequest extends Model
{
    const ACTION_REQUESTED = 0;
    const ACTION_DELETED = 1;
    const ACTION_REJECTED = 2;

    protected $table = 'delete_account_requests';

    protected $fillable = [
        'user_id',
        'date_requested',
        'action_taken',
        'action_taken_date',
    ];

    protected function casts(): array
    {
        return [
            'date_requested' => 'datetime',
            'action_taken_date' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo('STS\Models\User', 'user_id');
    }
}
