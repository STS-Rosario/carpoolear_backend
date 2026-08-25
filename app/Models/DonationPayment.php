<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationPayment extends Model
{
    protected $fillable = [
        'user_id',
        'donation_tier_id',
        'amount_cents',
        'currency',
        'status',
        'mp_payment_id',
        'mp_preference_id',
        'external_reference',
        'source',
        'trip_id',
        'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(DonationTier::class, 'donation_tier_id');
    }

    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }
}
