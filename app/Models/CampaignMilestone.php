<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMilestone extends Model
{
    protected $fillable = [
        'campaign_id',
        'title',
        'description',
        'image_path',
        'amount_cents',
    ];

    /**
     * Get the campaign that owns the milestone.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Check if the milestone has been reached.
     */
    public function isReached(): bool
    {
        return $this->campaign->total_donated >= $this->amount_cents;
    }

    /**
     * Get the progress percentage towards this milestone.
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->amount_cents === 0) {
            return 0;
        }

        return min(100, (int) (($this->campaign->total_donated / $this->amount_cents) * 100));
    }
}
