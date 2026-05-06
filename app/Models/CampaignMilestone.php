<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMilestone extends Model
{
    protected $table = 'campaign_milestones';

    /**
     * @return list<string>
     */
    public function getFillable(): array
    {
        return [
            'campaign_id',
            'title',
            'description',
            'image_path',
            'amount_cents',
        ];
    }

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

        $donated = $this->campaign->total_donated;
        $pct = intdiv($donated * 100, $this->amount_cents);

        return min(100, $pct);
    }
}
