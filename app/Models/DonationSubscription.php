<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonationSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'donation_tier_id',
        'mp_preapproval_id',
        'mp_preapproval_plan_id',
        'status',
        'transaction_amount_cents',
        'next_payment_date',
        'last_charged_at',
        'external_reference',
        'source',
        'trip_id',
    ];

    protected $casts = [
        'transaction_amount_cents' => 'integer',
        'next_payment_date' => 'date',
        'last_charged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(DonationTier::class, 'donation_tier_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(DonationSubscriptionCharge::class);
    }
}
