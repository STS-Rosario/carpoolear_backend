<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $fillable = [
        'slug',
        'title',
        'description',
        'image_path',
        'start_date',
        'end_date',
        'mp_slug',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the milestones for the campaign.
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(CampaignMilestone::class);
    }

    /**
     * Get the donations for the campaign.
     */
    public function donations(): HasMany
    {
        return $this->hasMany(CampaignDonation::class);
    }

    /**
     * Get the total amount donated to the campaign.
     */
    public function getTotalDonatedAttribute(): int
    {
        return $this->donations()
            ->where('status', 'paid')
            ->sum('amount_cents');
    }

    /**
     * Get the next milestone to be reached.
     */
    public function getNextMilestoneAttribute(): ?CampaignMilestone
    {
        return $this->milestones()
            ->where('amount_cents', '>', $this->total_donated)
            ->orderBy('amount_cents')
            ->first();
    }
}
