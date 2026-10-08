<?php

namespace STS\Helpers;

use Carbon\Carbon;
use STS\Models\Trip;

class SelladoEmptyTripCredit
{
    public static function userHasUnusedCredit(int $userId): bool
    {
        return static::unusedCreditQuery($userId)->exists();
    }

    public static function redeemOldestUnusedCredit(int $userId): ?Trip
    {
        $trip = static::unusedCreditQuery($userId)
            ->orderBy('trip_date')
            ->orderBy('id')
            ->first();

        if (! $trip) {
            return null;
        }

        $trip->sellado_empty_trip_credit_redeemed_at = Carbon::now();
        $trip->save();

        return $trip;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Trip>
     */
    private static function unusedCreditQuery(int $userId)
    {
        return Trip::query()
            ->where('user_id', $userId)
            ->where('is_passenger', false)
            ->where('needs_sellado', true)
            ->where('state', Trip::STATE_READY)
            ->where('trip_date', '<', Carbon::now())
            ->whereNull('sellado_empty_trip_credit_redeemed_at')
            ->whereDoesntHave('passengerAccepted');
    }
}
