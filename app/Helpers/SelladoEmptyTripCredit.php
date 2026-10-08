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
            ->whereDoesntHave('passengerAccepted');
    }
}
