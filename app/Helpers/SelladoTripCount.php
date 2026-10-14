<?php

namespace STS\Helpers;

use STS\Models\Passenger;
use STS\Models\Trip;

class SelladoTripCount
{
    /**
     * Count trips that consume the sellado free-trip quota.
     *
     * Canceled driver trips without accepted passengers at cancel time are
     * skipped. Passenger-seeking trips and driver trips that still have
     * accepted passengers keep counting after cancel.
     */
    public static function forUser(int $userId): int
    {
        return Trip::withTrashed()
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->where('is_passenger', true)
                    ->orWhereHas('passenger', function ($passengerQuery) {
                        $passengerQuery->where('request_state', Passenger::STATE_ACCEPTED)
                            ->whereColumn('trip_passengers.user_id', '<>', 'trips.user_id');
                    })
                    ->orWhere(function ($active) {
                        $active->whereNull('deleted_at')
                            ->where(function ($state) {
                                $state->whereNull('state')
                                    ->orWhere('state', '!=', Trip::STATE_CANCELED);
                            });
                    });
            })
            ->count();
    }
}
