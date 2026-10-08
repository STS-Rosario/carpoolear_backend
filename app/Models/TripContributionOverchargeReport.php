<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripContributionOverchargeReport extends Model
{
    protected $table = 'trip_contribution_overcharge_reports';

    protected $fillable = [
        'user_id',
        'trip_id',
        'paid_more',
    ];

    protected function casts(): array
    {
        return [
            'paid_more' => 'boolean',
        ];
    }

    public static function shouldAsk(Rating $rate, ?Trip $trip): bool
    {
        if ($trip === null) {
            return false;
        }

        return (int) $rate->user_to_type === Passenger::TYPE_CONDUCTOR
            && (int) $trip->seat_price_cents > 0;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
