<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignReward extends Model
{
    /**
     * @return list<string>
     */
    public function getFillable(): array
    {
        return [
            'campaign_id',
            'title',
            'description',
            'donation_amount_cents',
            'quantity_available',
            'is_active',
        ];
    }

    protected function casts(): array
    {
        return [
            'donation_amount_cents' => 'integer',
            'quantity_available' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getAppends(): array
    {
        return [
            'donation_amount',
            'is_sold_out',
            'quantity_remaining',
        ];
    }

    public function hasAppended($attribute): bool
    {
        return in_array($attribute, $this->getAppends(), true);
    }

    protected function getArrayableAppends()
    {
        $appends = $this->getAppends();

        $keys = count($appends) === 0
            ? []
            : array_combine($appends, $appends);

        return $this->getArrayableItems($keys);
    }

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

    public function getQuantityRemainingAttribute(): ?int
    {
        if ($this->quantity_available === null) {
            return null;
        }

        $soldQuantity = $this->donations()->where('status', 'paid')->count();

        return max(0, $this->quantity_available - $soldQuantity);
    }
}
