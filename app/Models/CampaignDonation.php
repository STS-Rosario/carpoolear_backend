<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDonation extends Model
{
    protected $table = 'campaign_donations';

    protected static function booted(): void
    {
        static::creating(function (CampaignDonation $donation): void {
            if (! array_key_exists('status', $donation->getAttributes())) {
                throw new \InvalidArgumentException('CampaignDonation must be created with an explicit status.');
            }
        });
    }

    protected $fillable = [
        'campaign_id',
        'campaign_reward_id',
        'payment_id',
        'amount_cents',
        'name',
        'comment',
        'user_id',
        'status',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    /**
     * Get the campaign that owns the donation.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the user that made the donation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the campaign reward associated with this donation.
     */
    public function campaignReward(): BelongsTo
    {
        return $this->belongsTo(CampaignReward::class);
    }

    /**
     * Get the amount in dollars.
     */
    public function getAmountAttribute(): float
    {
        return $this->amount_cents / 100;
    }

    /**
     * Scope a query to only include paid donations.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope a query to only include pending donations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include failed donations.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
