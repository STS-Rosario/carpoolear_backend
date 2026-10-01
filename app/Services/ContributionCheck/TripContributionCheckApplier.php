<?php

namespace STS\Services\ContributionCheck;

use STS\Helpers\TripDescriptionContributionHelper;
use STS\Models\Trip;
use STS\Support\TripExcessContributionStatus;

/**
 * Stores an LLM contribution check result on the trip. A trip shows up in the
 * admin "exceso de contribución" list (has_potential_excess_contribution)
 * when the description asks for more than the maximum allowed contribution
 * per seat (TripMaximumSeatPrice, not the price the driver chose) OR contains
 * a phone number.
 */
class TripContributionCheckApplier
{
    private const MAX_UNSIGNED_INT = 4294967295;

    private const MAX_UNSIGNED_SMALLINT = 65535;

    public function apply(Trip $trip, ContributionCheckResult $result): void
    {
        $maxSeatPriceCents = TripMaximumSeatPrice::centsFor($trip);
        $suspectedCents = $result->suspectedContribution === null
            ? null
            : (int) round($result->suspectedContribution * 100);

        $exceedsMax = $result->exceedsMax
            && $maxSeatPriceCents !== null
            && ($suspectedCents === null || $suspectedCents > $maxSeatPriceCents);
        $flagged = $exceedsMax || $result->phoneInDescription;

        $potentialSeatPriceCents = $exceedsMax && $suspectedCents !== null && $suspectedCents <= self::MAX_UNSIGNED_INT
            ? $suspectedCents
            : null;

        $trip->suspected_contribution = $result->suspectedContribution;
        $trip->phone_in_description = $result->phoneInDescription;
        $trip->has_potential_excess_contribution = $flagged;
        $trip->description_potential_seat_price_cents = $potentialSeatPriceCents;

        if ($flagged) {
            $trip->average_contribution_cents = TripDescriptionContributionHelper::averageContributionCents($trip);
            $trip->excess_contribution_percentage = $this->clampPercentage(
                TripDescriptionContributionHelper::excessContributionPercentage(
                    $potentialSeatPriceCents,
                    $trip->average_contribution_cents
                )
            );

            if ($trip->exceso_contribucion_status === null) {
                $trip->exceso_contribucion_status = TripExcessContributionStatus::PENDIENTE;
            }
        } else {
            $trip->average_contribution_cents = null;
            $trip->excess_contribution_percentage = null;
            $trip->exceso_contribucion_status = null;
        }

        $trip->saveQuietly();
    }

    private function clampPercentage(?int $percentage): ?int
    {
        if ($percentage === null) {
            return null;
        }

        return max(0, min(self::MAX_UNSIGNED_SMALLINT, $percentage));
    }
}
