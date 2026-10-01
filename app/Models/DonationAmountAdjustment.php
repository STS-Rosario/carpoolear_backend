<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationAmountAdjustment extends Model
{
    protected $fillable = [
        'donation_tier_id',
        'old_amount_cents',
        'new_amount_cents',
        'admin_user_id',
        'subscriptions_updated',
        'subscriptions_failed',
        'applied_at',
    ];

    protected $casts = [
        'old_amount_cents' => 'integer',
        'new_amount_cents' => 'integer',
        'subscriptions_updated' => 'integer',
        'subscriptions_failed' => 'integer',
        'applied_at' => 'datetime',
    ];

    public function tier(): BelongsTo
    {
        return $this->belongsTo(DonationTier::class, 'donation_tier_id');
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
