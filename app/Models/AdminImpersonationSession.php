<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminImpersonationSession extends Model
{
    protected $table = 'admin_impersonation_sessions';

    protected $fillable = [
        'admin_user_id',
        'target_user_id',
        'token_hash',
        'expires_at',
        'consumed_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function isActive(): bool
    {
        if ($this->consumed_at !== null || $this->ended_at !== null) {
            return false;
        }

        return $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
