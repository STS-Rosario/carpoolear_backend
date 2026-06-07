<?php

namespace STS\Helpers;

class TripPriceHelper
{
    public static function occupantsForPriceCalculation($rearMaxTwoPassengers): int
    {
        return (int) $rearMaxTwoPassengers > 0 ? 4 : 5;
    }

    public static function seatPriceCentsFromTripPriceCents(
        int $tripPriceCents,
        $rearMaxTwoPassengers
    ): int {
        return (int) round(
            $tripPriceCents / self::occupantsForPriceCalculation($rearMaxTwoPassengers)
        );
    }
}
