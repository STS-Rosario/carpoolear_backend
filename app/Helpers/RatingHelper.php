<?php

namespace STS\Helpers;

use Carbon\Carbon;

class RatingHelper
{
    public const AVAILABLE_DURATION_FACTOR = 0.8;

    public static function getRatingAvailableAt(Carbon $tripStart, ?string $estimatedTime): Carbon
    {
        $durationMinutes = OngoingTripHelper::estimatedTimeToMinutes($estimatedTime);
        $availableAfterMinutes = (int) round($durationMinutes * self::AVAILABLE_DURATION_FACTOR);

        return $tripStart->copy()->addMinutes($availableAfterMinutes);
    }

    public static function isRatingAvailable(
        Carbon $now,
        Carbon $tripStart,
        ?string $estimatedTime
    ): bool {
        return $now->greaterThanOrEqualTo(self::getRatingAvailableAt($tripStart, $estimatedTime));
    }
}
