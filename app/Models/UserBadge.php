<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserBadge extends Pivot
{
    protected $table = 'user_badges';

    protected $fillable = [
        'user_id',
        'badge_id',
        'awarded_at'
    ];

    protected $casts = [
        'awarded_at' => 'datetime'
    ];

    /**
     * Get the user that owns the badge.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the badge that belongs to the user.
     */
    public function badge()
    {
        return $this->belongsTo(Badge::class);
    }
} 