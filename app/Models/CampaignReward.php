<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignReward extends Model
{
    protected $fillable = [
        'campaign_id',
        'title',
        'description',
        'donation_amount_cents',
        'quantity_available',
        'is_active'
    ];

    protected $casts = [
        'donation_amount_cents' => 'integer',
        'quantity_available' => 'integer',
        'is_active' => 'boolean'
    ];

    protected $appends = [
        'donation_amount',
        'is_sold_out'
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(CampaignDonation::class);
    }

    public function getDonationAmountAttribute(): float
    {
        return $this->donation_amount_cents / 100;
    }

    public function getIsSoldOutAttribute(): bool
    {
        if ($this->quantity_available === null) {
            return false;
        }

        $soldQuantity = $this->donations()->where('status', 'paid')->count();
        return $soldQuantity >= $this->quantity_available;
    }

    public function getRemainingQuantityAttribute(): ?int
    {
        if ($this->quantity_available === null) {
            return null;
        }

        $soldQuantity = $this->donations()->where('status', 'paid')->count();
        return max(0, $this->quantity_available - $soldQuantity);
    }
} 