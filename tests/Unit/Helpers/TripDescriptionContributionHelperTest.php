<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use STS\Helpers\TripDescriptionContributionHelper;

class TripDescriptionContributionHelperTest extends TestCase
{
    public function test_potential_excess_detects_dollar_amount_above_seat_price(): void
    {
        $seatPriceCents = 1500000; // $15.000

        $this->assertSame(
            2400000,
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'La contribución es de $24000 por persona',
                $seatPriceCents
            )
        );
    }

    public function test_potential_excess_detects_k_suffix_amounts(): void
    {
        $seatPriceCents = 1500000;

        $this->assertSame(
            2400000,
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'Pago $24K cada uno',
                $seatPriceCents
            )
        );
    }

    public function test_potential_excess_detects_lucas_amounts(): void
    {
        $seatPriceCents = 1500000;

        $this->assertSame(
            2400000,
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'Son 24 lucas por persona',
                $seatPriceCents
            )
        );
    }

    public function test_potential_excess_returns_null_when_description_amount_is_not_higher(): void
    {
        $seatPriceCents = 1500000;

        $this->assertNull(
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'Contribución $15000',
                $seatPriceCents
            )
        );
        $this->assertNull(
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                'Solo 10 lucas',
                $seatPriceCents
            )
        );
    }

    public function test_potential_excess_returns_null_for_voluntary_or_non_positive_seat_price(): void
    {
        $this->assertNull(
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                '$24000',
                -1
            )
        );
        $this->assertNull(
            TripDescriptionContributionHelper::potentialExcessContributionCents(
                '$24000',
                0
            )
        );
    }

    public function test_has_potential_excess_contribution_reflects_potential_amount(): void
    {
        $this->assertTrue(
            TripDescriptionContributionHelper::hasPotentialExcessContribution(
                '$24000',
                1500000
            )
        );
        $this->assertFalse(
            TripDescriptionContributionHelper::hasPotentialExcessContribution(
                '$15000',
                1500000
            )
        );
    }
}
