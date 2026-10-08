<?php

namespace STS\Services\ContributionCheck;

/**
 * Amounts that look like a missing thousands separator ($16 instead of $16000).
 * Compared in currency units (pesos), the same unit as suspected_contribution.
 */
final class ImplausiblyLowContribution
{
    public const MIN_EXCLUSIVE = 1.0;

    public const MAX_EXCLUSIVE = 2000.0;

    public static function matches(?float $amount): bool
    {
        return $amount !== null
            && $amount > self::MIN_EXCLUSIVE
            && $amount < self::MAX_EXCLUSIVE;
    }

    public static function matchesSeatPriceCents(?int $cents): bool
    {
        if ($cents === null || $cents <= 0) {
            return false;
        }

        return self::matches($cents / 100);
    }
}
