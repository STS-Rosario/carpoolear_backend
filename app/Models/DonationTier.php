<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonationTier extends Model
{
    protected $fillable = [
        'slug',
        'label_key',
        'amount_cents',
        'icon',
        'mp_preapproval_plan_id',
        'is_active',
        'sort_order',
        'effective_from',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(DonationPayment::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(DonationSubscription::class);
    }

    public function amountAdjustments(): HasMany
    {
        return $this->hasMany(DonationAmountAdjustment::class);
    }

    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
