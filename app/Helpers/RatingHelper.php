<?php

namespace STS\Helpers;

use Carbon\Carbon;
use STS\Models\Rating;

class RatingHelper
{
    public const AVAILABLE_DURATION_FACTOR = 0.8;

    public static function ratingRequiresComment(?int $rating): bool
    {
        if ($rating === null) {
            return false;
        }

        return in_array($rating, [Rating::STATE_NEGATIVO, Rating::STATE_NEUTRAL], true);
    }

    public static function hasRequiredRatingComment(?int $rating, mixed $comment): bool
    {
        if (! self::ratingRequiresComment($rating)) {
            return true;
        }

        return trim((string) ($comment ?? '')) !== '';
    }

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
