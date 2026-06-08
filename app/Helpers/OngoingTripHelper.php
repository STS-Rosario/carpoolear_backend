<?php

namespace STS\Helpers;

use Carbon\Carbon;

class OngoingTripHelper
{
    public const LEAD_MINUTES = 60;

    public const GRACE_MINUTES = 30;

    public static function estimatedTimeToMinutes(?string $estimatedTime): int
    {
        if ($estimatedTime === null || $estimatedTime === '') {
            return 0;
        }

        $parts = explode(':', $estimatedTime);
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);

        return ($hours * 60) + $minutes;
    }

    public static function isWithinOngoingTripWindow(
        Carbon $now,
        Carbon $tripStart,
        ?string $estimatedTime
    ): bool {
        $durationMinutes = self::estimatedTimeToMinutes($estimatedTime);
        $windowStart = $tripStart->copy()->subMinutes(self::LEAD_MINUTES);
        $windowEnd = $tripStart->copy()->addMinutes($durationMinutes + self::GRACE_MINUTES);

        return $now->greaterThanOrEqualTo($windowStart)
            && $now->lessThanOrEqualTo($windowEnd);
    }
}
