<?php

namespace STS\Services\ContributionCheck;

use STS\Helpers\TripPriceHelper;
use STS\Models\Trip;

/**
 * Maximum allowed contribution per seat for a saved trip: the same cap
 * TripRepository applies to seat_price_cents on create/update (trip info
 * maximum_trip_price_cents split by comfort occupants). Null means the trip has
 * no maximum: max price module disabled, maximum never computed, or a
 * voluntary / missing contribution.
 */
class TripMaximumSeatPrice
{
    public static function centsFor(Trip $trip): ?int
    {
        if (! config('carpoolear.module_max_price_enabled')) {
            return null;
        }

        if ((int) $trip->seat_price_cents <= 0) {
            return null;
        }

        $maximumTripPriceCents = (int) $trip->maximum_trip_price_cents;

        if ($maximumTripPriceCents <= 0) {
            return null;
        }

        return TripPriceHelper::seatPriceCentsFromTripPriceCents(
            $maximumTripPriceCents,
            $trip->rear_max_two_passengers
        );
    }
}
