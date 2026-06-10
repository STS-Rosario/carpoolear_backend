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

    public static function getAutoStopAt(Carbon $tripStart, ?string $estimatedTime): Carbon
    {
        $durationMinutes = self::estimatedTimeToMinutes($estimatedTime);

        return $tripStart->copy()->addMinutes($durationMinutes * 2);
    }

    public static function getSharingWindowEnd(Carbon $tripStart, ?string $estimatedTime): Carbon
    {
        return self::getAutoStopAt($tripStart, $estimatedTime);
    }

    public static function canStartSharing(
        Carbon $now,
        Carbon $tripStart,
        ?string $estimatedTime
    ): bool {
        $windowStart = $tripStart->copy()->subMinutes(self::LEAD_MINUTES);
        $windowEnd = self::getSharingWindowEnd($tripStart, $estimatedTime);

        return $now->greaterThanOrEqualTo($windowStart)
            && $now->lessThanOrEqualTo($windowEnd);
    }

    public static function getAutoStopAtForShare(Carbon $tripStart, ?string $estimatedTime, ?Carbon $shareStartedAt): Carbon
    {
        $reference = $shareStartedAt ?? $tripStart;

        return self::getAutoStopAt($reference, $estimatedTime);
    }

    public static function shouldSendStopReminder(
        Carbon $now,
        Carbon $tripStart,
        ?string $estimatedTime,
        float $sharerLat,
        float $sharerLng,
        float $destLat,
        float $destLng,
        float $radiusKm = 10.0
    ): bool {
        $durationMinutes = self::estimatedTimeToMinutes($estimatedTime);
        $eta = $tripStart->copy()->addMinutes($durationMinutes);

        if ($now->lessThan($eta)) {
            return false;
        }

        return GeoDistanceHelper::isWithinKm(
            $sharerLat,
            $sharerLng,
            $destLat,
            $destLng,
            $radiusKm
        );
    }
}
