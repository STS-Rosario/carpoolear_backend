<?php

namespace STS\Helpers;

use STS\Models\Trip;

class TripDescriptionContributionHelper
{
    /**
     * @return list<int>
     */
    public static function extractContributionAmountsCents(string $description): array
    {
        $amounts = [];

        if (preg_match_all(
            '/\$\s*(\d+(?:[.,]\d+)*)\s*([kK])?/u',
            $description,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $amounts[] = self::parseNumericAmountToCents(
                    $match[1],
                    ($match[2] ?? '') !== ''
                );
            }
        }

        if (preg_match_all(
            '/(\d+(?:[.,]\d+)*)\s*lucas?/iu',
            $description,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $amounts[] = self::parseNumericAmountToCents($match[1], true);
            }
        }

        return array_values(array_unique($amounts));
    }

    public static function maxContributionAmountCents(string $description): ?int
    {
        $amounts = self::extractContributionAmountsCents($description);

        if ($amounts === []) {
            return null;
        }

        return max($amounts);
    }

    public static function potentialExcessContributionCents(
        string $description,
        int $seatPriceCents
    ): ?int {
        if ($seatPriceCents <= 0) {
            return null;
        }

        $maxAmountCents = self::maxContributionAmountCents($description);

        if ($maxAmountCents === null || $maxAmountCents <= $seatPriceCents) {
            return null;
        }

        return $maxAmountCents;
    }

    public static function hasPotentialExcessContribution(
        string $description,
        int $seatPriceCents
    ): bool {
        return self::potentialExcessContributionCents($description, $seatPriceCents) !== null;
    }

    public static function syncPotentialExcessContributionAttributes(Trip $trip): void
    {
        $potentialSeatPriceCents = self::potentialExcessContributionCents(
            $trip->description ?? '',
            (int) $trip->seat_price_cents
        );

        $trip->has_potential_excess_contribution = $potentialSeatPriceCents !== null;
        $trip->description_potential_seat_price_cents = $potentialSeatPriceCents;
    }

    private static function parseNumericAmountToCents(
        string $raw,
        bool $multiplyByThousands
    ): int {
        $pesos = (float) self::normalizeAmountString($raw);

        if ($multiplyByThousands) {
            $pesos *= 1000;
        }

        return (int) round($pesos * 100);
    }

    private static function normalizeAmountString(string $raw): string
    {
        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($raw, ',');
            $lastDot = strrpos($raw, '.');

            if ($lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasComma) {
            if (preg_match('/,\d{3}$/', $raw)) {
                $raw = str_replace(',', '', $raw);
            } else {
                $raw = str_replace(',', '.', $raw);
            }
        } elseif ($hasDot && preg_match('/\.\d{3}$/', $raw)) {
            $raw = str_replace('.', '', $raw);
        }

        return $raw;
    }
}
