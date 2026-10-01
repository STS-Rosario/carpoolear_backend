<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationSubscriptionCharge extends Model
{
    protected $fillable = [
        'donation_subscription_id',
        'mp_payment_id',
        'amount_cents',
        'status',
        'charged_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'charged_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(DonationSubscription::class, 'donation_subscription_id');
    }
}
